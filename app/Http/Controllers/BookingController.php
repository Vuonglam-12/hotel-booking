<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\BookingService;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    public function store(Request $request)
    {
        if (\App\Support\LocalBookingDemo::enabled()) {
            \App\Support\LocalBookingDemo::assertRequest($request);
            $data = $request->all();
            $data['request_key'] = $request->header('Idempotency-Key', $request->input('request_key'));
            $result = app(\App\Services\LocalBookingDemoService::class)->create((int)auth('sanctum')->id(), $data);
            return response()->json($result + ['booking'=>['id'=>$result['booking_id']],
                'payment_url'=>'/payments/bookings/'.$result['booking_id']], $result['duplicate'] ? 200 : 201);
        }
        // The approved V2 registrar owns creation. Keep the legacy API closed during preparation.
        return response()->json(['message' => 'Tính năng đặt phòng tạm thời chưa được mở.'], 503)->header('Cache-Control', 'no-store');

        $validated = $request->validate([
            'hotel_id'        => ['required', 'integer', Rule::exists('hotel', 'id')->where('status', 'active')],
            'room_type_id'    => 'required|integer|exists:room_type,id',
            'room_id'         => ['nullable', 'integer', Rule::exists('room', 'id')
                ->where('hotel_id', $request->integer('hotel_id'))
                ->where('room_type_id', $request->integer('room_type_id'))
                ->where('status', 'available')],
            'quantity'        => ['required', 'integer', 'min:1', 'max:' . ($request->filled('room_id') ? 1 : 10)],
            'check_in'        => 'required|date|after_or_equal:today',
            'check_out'       => 'required|date|after:check_in',
            'num_guests'      => 'required|integer|min:1',
            'special_request' => 'nullable|string|max:500',
            'payment_method'  => 'nullable|in:banking,cash',
        ], [
            'hotel_id.exists' => 'Khách sạn hiện không nhận đặt phòng.',
            'room_id.exists' => 'Phòng không thuộc khách sạn hoặc loại phòng đã chọn, hoặc không thể đặt.',
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

        } catch (ValidationException $e) {
            throw $e;
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
        if (\App\Support\LocalBookingDemo::isDemo((int)$id)) {
            app(\App\Services\LocalBookingDemoService::class)->cancel((int)$id,(int)auth('sanctum')->id());
            return response()->json(['message'=>'Đã hủy đặt phòng DEMO.', 'booking'=>$this->bookingService->getDetail((int)$id,(int)auth('sanctum')->id())]);
        }
        try {
            $booking = $this->bookingService->cancel($id, auth('sanctum')->id());
            return response()->json(['message' => 'Huỷ booking thành công', 'booking' => $booking]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
