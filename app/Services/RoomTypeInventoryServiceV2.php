<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use Illuminate\Validation\ValidationException;

final class RoomTypeInventoryServiceV2
{
    public function changeTotal(string $connection, int $hotelId, int $typeId, int $newTotal): array
    {
        $session = RoomTypeV2Inventory::open($connection);
        if ($session->db->transactionLevel() !== 0) throw new \RuntimeException('V2 total change requires its own transaction boundary.');
        if ($newTotal < 0 || $newTotal > 2147483647) throw ValidationException::withMessages(['total_rooms' => 'Tổng tồn phải là INT không âm.']);
        return $session->db->transaction(function () use ($session, $hotelId, $typeId, $newTotal) {
            $session->hotel($hotelId, true);
            $type = $session->types($hotelId, [$typeId], true)[$typeId];
            // No 90-day cutoff: sweep every ongoing and future commitment up to MySQL DATE's end.
            $inspection = $session->inspect($type, now()->toDateString(), '9999-12-31', null, true);
            if ($newTotal < $inspection['booked_rooms']) throw ValidationException::withMessages(['total_rooms' => 'Tổng tồn mới thấp hơn lượng đã cam kết.']);
            $session->db->table('room_type')->where('id', $typeId)->update(['total_rooms' => $newTotal]);
            return ['total_rooms' => $newTotal, 'peak_reserved_quantity' => $inspection['booked_rooms']];
        });
    }
}
