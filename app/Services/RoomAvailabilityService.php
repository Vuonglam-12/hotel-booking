<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoomAvailabilityService
{
    public function validateCapacity(int $hotelId, int $roomTypeId, int $quantity, int $guests, ?int $roomId = null): void
    {
        $room = Room::where('hotel_id', $hotelId)->where('room_type_id', $roomTypeId)->offered()
            ->when($roomId !== null, fn($query) => $query->where('id', $roomId))->first();
        if (!$room) {
            throw ValidationException::withMessages(['room_type_id' => 'Khách sạn không có loại phòng hoặc phòng đã chọn.']);
        }
        $capacity = $roomId === null ? app(HotelRoomTypeService::class)->effective($hotelId,$roomTypeId)->capacity : $room->capacity;
        if ($quantity < 1 || $guests < 1 || $capacity < 1 || $guests > $capacity * $quantity) {
            throw ValidationException::withMessages([
                'num_guests' => "Số khách vượt sức chứa: {$quantity} phòng đã chọn phục vụ tối đa " . ($capacity * $quantity) . ' khách.',
            ]);
        }
    }

    public function forType(int $hotelId, int $roomTypeId, string $checkIn, string $checkOut): array
    {
        return $this->forTypeWithRooms($hotelId, $roomTypeId, $checkIn, $checkOut)['availability'];
    }

    /** Inventory is shared by room type; an unassigned booking does not identify a physical room. */
    public function forTypeWithRooms(int $hotelId, int $roomTypeId, string $checkIn, string $checkOut, ?int $excludeBooking = null): array
    {
        Validator::make(['check_in' => $checkIn, 'check_out' => $checkOut], [
            'check_in' => 'required|date_format:Y-m-d',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
        ])->validate();

        $rooms = Room::where('hotel_id', $hotelId)->where('room_type_id', $roomTypeId)
            ->managed()->get(['id', 'status'])->keyBy('id');
        $availableStock = $rooms->where('status', 'available')->count();
        if (HotelRoomTypeService::enabled() && in_array(app(HotelRoomTypeService::class)->effective($hotelId,$roomTypeId)->status, ['inactive', 'archived'], true)) $availableStock = 0;
        $roomStates = $rooms->mapWithKeys(fn($room) => [$room->id => [
            'physical_status' => $room->status,
            'occupancy_status' => $room->status,
        ]])->all();

        $reservations = DB::table('booking_room as br')
            ->join('booking as b', 'b.id', '=', 'br.booking_id')
            ->where('b.hotel_id', $hotelId)
            ->where('br.room_type_id', $roomTypeId)
            ->when($excludeBooking !== null, fn($q) => $q->where('b.id', '!=', $excludeBooking))
            ->whereNotIn('b.status', ['cancelled', 'expired'])
            ->when(\App\Support\LocalBookingDemo::enabled(), fn($q) => $q->where(function($q) {
                $q->where('b.status', '!=', 'pending')->orWhereNotExists(fn($d) => $d->selectRaw('1')->from('booking_demo as demo')
                    ->whereColumn('demo.booking_id', 'b.id')->whereRaw('demo.expires_at_epoch <= UNIX_TIMESTAMP()'));
            }))
            ->where('b.check_in', '<', $checkOut)
            ->where('b.check_out', '>', $checkIn)
            ->get(['br.id', 'br.booking_id', 'br.room_id', 'br.quantity', 'b.check_in', 'b.check_out', 'b.status']);

        $events = [];
        foreach ($reservations->unique('id')->groupBy('booking_id') as $lines) {
            $booking = $lines->first();
            $start = max($checkIn, $booking->check_in);
            $end = min($checkOut, $booking->check_out);
            $unassigned = (int) $lines->whereNull('room_id')->sum('quantity');
            $assigned = [];

            foreach ($lines->whereNotNull('room_id')->groupBy('room_id') as $roomId => $roomLines) {
                // Duplicate rows for the same booking/physical room occupy one room.
                $quantity = max(0, (int) $roomLines->max('quantity'));
                if ($quantity === 0) {
                    continue;
                }
                if (!$rooms->has($roomId)) {
                    // Preserve the reservation's capacity if legacy data references an invalid room.
                    $unassigned += $quantity;
                    continue;
                }
                $assigned[] = $roomId;
                $unassigned += max(0, $quantity - 1);
                $occupancy = $booking->status === 'pending' ? 'locked' : 'booked';
                if ($roomStates[$roomId]['occupancy_status'] !== 'booked') {
                    $roomStates[$roomId]['occupancy_status'] = $occupancy;
                }
            }

            $this->addEvent($events, $start, $unassigned, $assigned, 1);
            $this->addEvent($events, $end, $unassigned, $assigned, -1);
        }

        // Apply arrivals and departures together. Check-out never occupies that night's stock.
        ksort($events, SORT_STRING);
        $unassigned = 0;
        $occupiedRooms = [];
        $peakBooked = 0;
        $peakConsumedStock = 0;
        foreach ($events as $date => $event) {
            $unassigned += $event['unassigned'];
            foreach ($event['assigned'] as $roomId => $delta) {
                $occupiedRooms[$roomId] = ($occupiedRooms[$roomId] ?? 0) + $delta;
                if ($occupiedRooms[$roomId] === 0) {
                    unset($occupiedRooms[$roomId]);
                }
            }
            if ($date >= $checkOut) {
                break;
            }
            $occupiedAvailable = count(array_filter(array_keys($occupiedRooms),
                fn($roomId) => $rooms[$roomId]->status === 'available'));
            $peakBooked = max($peakBooked, count($occupiedRooms) + $unassigned);
            $peakConsumedStock = max($peakConsumedStock, $occupiedAvailable + $unassigned);
        }

        $availability = [
            'available_rooms' => max(0, $availableStock - $peakConsumedStock),
            'total_rooms' => $rooms->count(),
            'booked_rooms' => $peakBooked,
        ];
        foreach ($roomStates as &$state) {
            $state['bookable'] = $state['physical_status'] === 'available'
                && $state['occupancy_status'] === 'available'
                && $availability['available_rooms'] > 0;
        }
        unset($state);

        return ['availability' => $availability, 'rooms' => $roomStates];
    }

    private function addEvent(array &$events, string $date, int $unassigned, array $roomIds, int $direction): void
    {
        $events[$date] ??= ['unassigned' => 0, 'assigned' => []];
        $events[$date]['unassigned'] += $direction * $unassigned;
        foreach ($roomIds as $roomId) {
            $events[$date]['assigned'][$roomId] = ($events[$date]['assigned'][$roomId] ?? 0) + $direction;
        }
    }
}
