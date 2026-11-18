<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private RoomAvailabilityService $roomAvailability) {}

    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'hotel_id'     => 'required|integer|exists:hotel,id',
            'room_type_id' => 'required|integer|exists:room_type,id',
            'check_in'     => 'required|date',
            'check_out'    => 'required|date|after:check_in',
            'quantity'     => 'required|integer|min:1|max:10',
            'num_guests'   => 'required|integer|min:1',
            'room_id'      => 'nullable|integer|exists:room,id',
        ]);

        $hotel = Hotel::findOrFail($data['hotel_id']);
        if (!empty($data['room_id']) && (int) $data['quantity'] !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'Mỗi phòng cụ thể chỉ có thể đặt số lượng 1.']);
        }
        $this->roomAvailability->validateCapacity(
            $hotel->id, (int) $data['room_type_id'], (int) $data['quantity'], (int) $data['num_guests'],
            isset($data['room_id']) ? (int) $data['room_id'] : null
        );
        $inspection = $this->roomAvailability->forTypeWithRooms(
            $hotel->id, (int) $data['room_type_id'], $data['check_in'], $data['check_out']
        );
        $availability = $inspection['availability'];

        if ($hotel->status !== 'active') {
            return response()->json(array_merge($availability, [
                'available' => false,
                'available_rooms' => 0,
                'message' => 'Khách sạn hiện không nhận đặt phòng.',
            ]));
        }

        $available = $availability['available_rooms'] >= $data['quantity']
            && (empty($data['room_id']) || ($inspection['rooms'][$data['room_id']]['bookable'] ?? false));
        return response()->json(array_merge($availability, [
            'available' => $available,
            'message' => $available ? null : 'Không đủ số phòng đã chọn trong toàn bộ khoảng lưu trú.',
        ]));
    }
}
