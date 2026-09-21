<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * ====================================================================
     * KIỂM TRA PHÒNG CÒN TRỐNG TRONG KHOẢNG THỜI GIAN
     * ====================================================================
     * Mục đích: Kiểm tra xem một loại phòng có còn phòng trống
     *           trong khoảng check_in đến check_out không
     * ====================================================================
     */
    public function checkAvailability(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'check_in'     => 'required|date',
            'check_out'    => 'required|date|after:check_in',
        ]);

        // Lấy danh sách tất cả phòng thuộc loại phòng này
        $rooms = Room::where('room_type_id', $request->room_type_id)->get();

        if ($rooms->isEmpty()) {
            return response()->json([
                'available' => false,
                'message' => 'Không có phòng nào thuộc loại này'
            ]);
        }

        // Lấy danh sách ID của các phòng đã được đặt trong khoảng thời gian
        $bookedRoomIds = Booking::whereIn('room_id', $rooms->pluck('id'))
            ->where('status', '!=', 'cancelled')  // Không tính booking đã hủy
            ->where(function ($query) use ($request) {
                $query->whereBetween('check_in', [$request->check_in, $request->check_out])
                      ->orWhereBetween('check_out', [$request->check_in, $request->check_out])
                      ->orWhere(function ($subQuery) use ($request) {
                          $subQuery->where('check_in', '<=', $request->check_in)
                                   ->where('check_out', '>=', $request->check_out);
                      });
            })
            ->pluck('room_id')
            ->unique()
            ->toArray();

        // Tính số phòng còn trống
        $availableRooms = $rooms->whereNotIn('id', $bookedRoomIds);

        return response()->json([
            'available'        => $availableRooms->isNotEmpty(),
            'available_rooms'  => $availableRooms->count(),
            'total_rooms'      => $rooms->count(),
            'booked_rooms'     => count($bookedRoomIds),
        ]);
    }
}