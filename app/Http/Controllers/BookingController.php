<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Services\BookingService;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hotel_id'        => 'required|integer|exists:hotel,id',
            'room_type_id'    => 'required|integer|exists:room_type,id',
            'room_id'         => 'nullable|integer|exists:room,id',
            'quantity'        => 'required|integer|min:1|max:10',
            'check_in'        => 'required|date|after_or_equal:today',
            'check_out'       => 'required|date|after:check_in',
            'num_guests'      => 'required|integer|min:1',
            'special_request' => 'nullable|string|max:500',
            'payment_method'  => 'nullable|in:banking,cash',
        ]);

        try {
            $booking = $this->bookingService->create(
                auth('sanctum')->id(),
                $validated
            );

            return response()->json([
                'message' => 'Đặt phòng thành công',
                'booking' => $booking,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function myBookings(Request $request)
    {
        return response()->json(
            $this->bookingService->myBookings(auth('sanctum')->id(), $request->all())
        );
    }

    public function show($id)
    {
        return response()->json(
            $this->bookingService->getDetail($id, auth('sanctum')->id())
        );
    }

    public function cancel($id)
    {
        try {
            $booking = $this->bookingService->cancel($id, auth('sanctum')->id());
            return response()->json(['message' => 'Huỷ booking thành công', 'booking' => $booking]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}