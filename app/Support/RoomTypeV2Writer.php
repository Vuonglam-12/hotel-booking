<?php

namespace App\Support;

use App\Services\RoomAvailabilityService;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Shared isolated-test writer boundary, also supports verified legacy history during transition. */
final class RoomTypeV2Writer
{
    public static function run(string $connection, int $id, callable $callback): mixed
    {
        $session = RoomTypeV2Inventory::open($connection); $db = $session->db;
        if ($db->transactionLevel() !== 0) throw new RuntimeException('Writer requires its own transaction boundary.');
        $candidate = $db->table('booking')->where('id',$id)->first();
        if (!$candidate) throw ValidationException::withMessages(['booking'=>'Booking không tồn tại.']);
        $ids = $db->table('booking_room')->where('booking_id',$id)->pluck('room_type_id')->unique()->sort()->values()->all();
        return $db->transaction(function () use ($session,$db,$candidate,$ids,$id,$callback) {
            $session->hotel($candidate->hotel_id,true); $types=[];
            foreach ($ids as $typeId) {
                $type=$db->table('room_type')->where('id',$typeId)->lockForUpdate()->first();
                if (!$type || ($type->hotel_id !== null && $type->hotel_id !== $candidate->hotel_id)) throw new RuntimeException('Booking type ownership mismatch.');
                if ($type->hotel_id === null && !$db->table('room')->where('hotel_id',$candidate->hotel_id)->where('room_type_id',$typeId)->exists()) throw new RuntimeException('Unverifiable legacy type ownership.');
                $types[$typeId]=$type;
            }
            $booking=$db->table('booking')->where('id',$id)->lockForUpdate()->first();
            $lines=$db->table('booking_room')->where('booking_id',$id)->orderBy('id')->lockForUpdate()->get();
            if (!$booking || $booking->hotel_id !== $candidate->hotel_id || $lines->isEmpty() || $lines->pluck('room_type_id')->unique()->diff(array_keys($types))->isNotEmpty()) throw new RuntimeException('Booking history changed after lock discovery.');
            foreach ($lines as $line) if ($line->quantity < 1 || $line->nights !== RoomTypeV2Inventory::dates($booking->check_in,$booking->check_out)) throw new RuntimeException('Invalid BookingRoom history.');
            RoomTypeV2Inventory::holding($booking->status);
            return $callback($session,$booking,$lines,$types);
        },3); // Bounded InnoDB deadlock retry; the callback contains database work only.
    }

    public static function duplicate($db, object $booking, string $target): void
    {
        if (!in_array($target,['pending','confirmed'],true)) return;
        $q=$db->table('booking')->where('customer_id',$booking->customer_id)->where('hotel_id',$booking->hotel_id)
            ->where('check_in',$booking->check_in)->where('check_out',$booking->check_out)->where('id','!=',$booking->id)->whereIn('status',['pending','confirmed'])->lockForUpdate();
        if ($q->first()) throw ValidationException::withMessages(['status'=>'Đã có Booking đang hoạt động cùng khách sạn/ngày.']);
    }

    public static function commitment($session, object $booking, $lines, array $types, bool $restoring): void
    {
        if ($restoring && $session->hotel($booking->hotel_id)->status !== 'active') throw ValidationException::withMessages(['status'=>'Khách sạn đã ngừng bán.']);
        foreach ($lines->groupBy('room_type_id') as $typeId=>$group) {
            $type=$types[$typeId];
            if ($type->hotel_id !== null) {
                $session->types($booking->hotel_id,[$typeId]);
                if ($restoring && $type->status !== 'active') throw ValidationException::withMessages(['status'=>'Loại phòng đã ngừng bán.']);
                $a=$session->inspect($type,$booking->check_in,$booking->check_out,$booking->id,true);
            } else {
                if ($session->db->getName() !== config('database.default')) throw new RuntimeException('Legacy adapter requires the guarded default connection.');
                // A holding order already occupies legacy stock; a released order does not.
                $a=app(RoomAvailabilityService::class)->forType($booking->hotel_id,$typeId,$booking->check_in,$booking->check_out);
                $stock=$session->db->table('room')->where('hotel_id',$booking->hotel_id)->where('room_type_id',$typeId)->where('status','available')->count();
                if ($a['booked_rooms']>$stock) throw new RuntimeException('Legacy commitments already exceed bookable stock; reconciliation required.');
                if (RoomTypeV2Inventory::holding($booking->status)) $a['available_rooms'] += $group->sum('quantity');
            }
            if ($a['available_rooms'] < $group->sum('quantity')) throw ValidationException::withMessages(['status'=>'Không đủ tồn để giữ/khôi phục Booking.']);
        }
    }
}
