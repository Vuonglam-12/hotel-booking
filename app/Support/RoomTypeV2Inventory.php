<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Inventory session with separate test/deployment guards before opening a connection. */
final class RoomTypeV2Inventory
{
    private const HOLDING = ['pending', 'confirmed', 'completed'];
    private const RELEASED = ['cancelled', 'expired'];

    private function __construct(public readonly Connection $db) {}

    public static function open(string $connection): self
    {
        $db = RoomTypeV2Database::connection($connection);
        if (config('roomtype-v2.integration_complete', false)) RoomTypeV2WriteFence::install($db);
        return new self($db);
    }

    public static function dates(string $start, string $end): int
    {
        $a = \DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $b = \DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$a || !$b || $a->format('Y-m-d') !== $start || $b->format('Y-m-d') !== $end || $end <= $start) {
            throw ValidationException::withMessages(['check_out' => 'Khoảng lưu trú phải hợp lệ, checkout sau check-in.']);
        }
        return (int) $a->diff($b)->days;
    }

    public static function cents(string $value): int
    {
        if (!preg_match('/^\d{1,10}\.\d{2}$/D', $value)) throw new RuntimeException('Invalid DECIMAL(12,2) money snapshot.');
        return (int) str_replace('.', '', $value);
    }

    public static function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function holding(string $status): bool
    {
        if (!in_array($status, [...self::HOLDING, ...self::RELEASED], true)) {
            throw new RuntimeException('Unrecognized Booking status; inventory refused: ' . $status);
        }
        return in_array($status, self::HOLDING, true);
    }

    public function hotel(int $id, bool $lock = false): object
    {
        $q = $this->db->table('hotel')->where('id', $id);
        if ($lock) $q->lockForUpdate();
        return $q->first() ?? throw ValidationException::withMessages(['hotel_id' => 'Khách sạn không tồn tại.']);
    }

    /** Caller locks Hotel first; acquire all Type locks in ascending ID order. */
    public function types(int $hotelId, array $ids, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $types = [];
        foreach ($ids as $id) {
            $q = $this->db->table('room_type')->where('id', $id);
            if ($lock) $q->lockForUpdate();
            $type = $q->first();
            if (!$type || $type->hotel_id !== $hotelId) {
                throw ValidationException::withMessages(['room_type_id' => 'Loại phòng không thuộc khách sạn đã chọn hoặc chưa chuyển sang V2.']);
            }
            self::validateType($type);
            $types[$id] = $type;
        }
        return $types;
    }

    public static function validateType(object $type): void
    {
        if (!is_int($type->total_rooms) || $type->total_rooms < 0 || !is_int($type->capacity) || $type->capacity < 1
            || !in_array($type->status, ['active', 'inactive'], true)) throw new RuntimeException('Invalid RoomType V2 inventory fields.');
        self::cents($type->base_price);
    }

    /** SUM(quantity) event sweep; room_id and physical room status never determine V2 stock. */
    public function inspect(object $type, string $start, string $end, ?int $excludeBooking = null, bool $lock = false): array
    {
        self::dates($start, $end);
        // Fail closed during transition: legacy history must be remapped, never silently omitted.
        $unmapped = $this->db->table('booking_room as br')->join('booking as b', 'b.id', '=', 'br.booking_id')
            ->join('room_type as rt', 'rt.id', '=', 'br.room_type_id')->where('b.hotel_id', $type->hotel_id)
            ->where(fn($q) => $q->whereNull('rt.hotel_id')->orWhereColumn('rt.hotel_id', '!=', 'b.hotel_id'))->exists();
        if ($unmapped) throw new RuntimeException('Hotel has unmapped or mismatched BookingRoom history; complete verified remap first.');
        $q = $this->db->table('booking_room as br')->join('booking as b', 'b.id', '=', 'br.booking_id')
            ->where('br.room_type_id', $type->id)->orderBy('b.id')->orderBy('br.id');
        if ($lock) $q->lockForUpdate(); // current read after Hotel/Type locks, including REPEATABLE READ
        $rows = $q->get(['br.id', 'br.booking_id', 'br.quantity', 'br.nights', 'b.hotel_id', 'b.check_in', 'b.check_out', 'b.status']);
        $events = [$start => 0, $end => 0];
        foreach ($rows as $row) {
            if ($row->hotel_id !== $type->hotel_id) throw new RuntimeException('BookingRoom belongs to another Hotel.');
            $holds = self::holding($row->status);
            if (!$holds) continue;
            if (!is_int($row->quantity) || $row->quantity < 1 || $row->nights !== self::dates($row->check_in, $row->check_out)) {
                throw new RuntimeException('Invalid held BookingRoom quantity/dates/nights: ' . $row->id);
            }
            if ($row->booking_id === $excludeBooking || $row->check_out <= $start || $row->check_in >= $end) continue;
            $a = max($start, $row->check_in);
            $b = min($end, $row->check_out);
            $events[$a] = ($events[$a] ?? 0) + $row->quantity;
            $events[$b] = ($events[$b] ?? 0) - $row->quantity;
        }
        ksort($events, SORT_STRING);
        $used = $peak = 0;
        $segments = [];
        $dates = array_keys($events);
        foreach ($dates as $i => $date) {
            $used += $events[$date]; // arrivals and departures combined at the same midnight
            if ($used < 0) throw new RuntimeException('Invalid negative inventory sweep.');
            if ($date >= $end) break;
            $peak = max($peak, $used);
            if ($used > $type->total_rooms) throw new RuntimeException('Held inventory exceeds total_rooms for Type ' . $type->id . ' on ' . $date);
            $segments[] = ['from' => $date, 'until' => $dates[$i + 1], 'used' => $used, 'remaining' => $type->total_rooms - $used];
        }
        return ['total_rooms' => $type->total_rooms, 'booked_rooms' => $peak,
            'available_rooms' => $type->total_rooms - $peak, 'segments' => $segments];
    }
}
