<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * HÀM STORE: XỬ LÝ KHI KHÁCH BẤM NÚT "ĐẶT PHÒNG"
     */
    public function store(Request $request)
    {
        // --- BƯỚC 1: VALIDATION ---
        $request->validate([
            'hotel_id'        => 'required|integer|exists:hotel,id',
            'room_type_id'    => 'required|integer|exists:room_type,id',
            'quantity'        => 'required|integer|min:1|max:10',
            'check_in'        => 'required|date|after_or_equal:today',
            'check_out'       => 'required|date|after:check_in',
            'num_guests'      => 'required|integer|min:1',
            'special_request' => 'nullable|string|max:500',
            'payment_method'  => 'nullable|in:vnpay,banking,cash',
        ]);

        // --- BƯỚC 2: CHUẨN BỊ DỮ LIỆU ---
        $customerId    = auth('sanctum')->id();
        $checkIn       = $request->check_in;
        $checkOut      = $request->check_out;
        $nights        = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        $paymentMethod = $request->payment_method ?? 'vnpay';

        // --- BƯỚC 3: KIỂM TRA PHÒNG TRỐNG ---
        // Đếm tổng số phòng của loại phòng đó trong khách sạn
        $totalRooms = DB::table('room')
            ->where('hotel_id',     $request->hotel_id)
            ->where('room_type_id', $request->room_type_id)
            ->where('status',       'available')
            ->count();

        // Đếm số phòng đã bị booking trong khoảng ngày đó (trùng lịch)
        $bookedRooms = DB::table('booking_room as br')
            ->join('booking as b', 'b.id', '=', 'br.booking_id')
            ->where('b.hotel_id',     $request->hotel_id)
            ->where('br.room_type_id', $request->room_type_id)
            ->whereNotIn('b.status',  ['cancelled'])
            ->where('b.check_in',  '<', $checkOut)  // Booking bắt đầu trước ngày trả
            ->where('b.check_out', '>', $checkIn)   // Booking kết thúc sau ngày nhận
            ->sum('br.quantity');

        // Số phòng thực sự còn trống
        $available = $totalRooms - $bookedRooms;

        if ($available < $request->quantity) {
            return response()->json([
                'message' => 'Không đủ phòng trống trong khoảng thời gian này',
            ], 422);
        }

        // --- BƯỚC 4: LẤY GIÁ PHÒNG ---
        $roomType   = RoomType::findOrFail($request->room_type_id);
        $totalPrice = $roomType->base_price * $nights * $request->quantity;

        // --- BƯỚC 5: TẠO ĐƠN HÀNG (TRANSACTION) ---
        $booking = DB::transaction(function () use ($request, $customerId, $totalPrice, $roomType, $checkIn, $checkOut, $nights) {

            // 5.1 Tạo booking chính
            $booking = Booking::create([
                'customer_id'     => $customerId,
                'hotel_id'        => $request->hotel_id,
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'num_guests'      => $request->num_guests,
                'total_price'     => $totalPrice,
                'status'          => 'pending',
                'expires_at'      => now()->addMinutes(15),
                'special_request' => $request->special_request,
            ]);

            // 5.2 Lưu chi tiết phòng đặt (chốt giá tại thời điểm đặt)
            BookingRoom::create([
                'booking_id'       => $booking->id,
                'room_type_id'     => $request->room_type_id,
                'price_at_booking' => $roomType->base_price,
                'nights'           => $nights,
                'quantity'         => $request->quantity,
            ]);

            return $booking;
        });

        // --- BƯỚC 6: XỬ LÝ THANH TOÁN THỦ CÔNG (BANKING / CASH) ---
        if (in_array($paymentMethod, ['banking', 'cash'])) {
            try {
                $payment = Payment::create([
                    'booking_id'     => $booking->id,
                    'amount'         => $totalPrice,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                    'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
                ]);

                // Tạo hóa đơn
                InvoiceController::createFromPayment($booking, $payment);

                // Gửi email xác nhận
                $booking->load('customer');
                Mail::to($booking->customer->email)
                    ->send(new \App\Mail\BookingConfirmed($booking));

            } catch (\Exception $e) {
                Log::error('BookingController@store: Lỗi tạo invoice/email cho ' . $paymentMethod, [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // --- BƯỚC 7: TRẢ KẾT QUẢ ---
        $bookingData = $booking->load(['bookingRooms.roomType', 'hotel'])->toArray();
        $bookingData['total_price'] = number_format($booking->total_price, 0, '.', ',') . ' VND';
        foreach ($bookingData['booking_rooms'] as &$br) {
            $br['price_at_booking']        = number_format($br['price_at_booking'], 0, '.', ',') . ' VND';
            $br['room_type']['base_price'] = number_format($br['room_type']['base_price'], 0, '.', ',') . ' VND';
        }

        return response()->json([
            'message'        => 'Đặt phòng thành công' . ($paymentMethod === 'vnpay' ? ', vui lòng thanh toán trong 15 phút' : ''),
            'booking'        => $bookingData,
            'payment_method' => $paymentMethod,
        ], 201);
    }

    /**
     * HÀM MYBOOKINGS: XEM LỊCH SỬ ĐẶT PHÒNG
     */
    public function myBookings(Request $request)
    {
        $bookings = Booking::with(['hotel', 'bookingRooms.roomType', 'payment'])
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($bookings);
    }

    /**
     * HÀM SHOW: XEM CHI TIẾT 1 ĐƠN HÀNG
     */
    public function show($id)
    {
        $booking = Booking::with(['hotel', 'bookingRooms.roomType', 'payment'])
            ->where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        return response()->json($booking);
    }

    /**
     * HÀM CANCEL: KHÁCH TỰ HỦY ĐƠN
     */
    public function cancel($id)
    {
        $booking = Booking::with('bookingRooms')
            ->where('customer_id', auth('sanctum')->id())
            ->findOrFail($id);

        // Kiểm tra trạng thái có được hủy không
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Không thể huỷ booking ở trạng thái ' . $booking->status,
            ], 422);
        }

        // Chặn hủy trong vòng 24 giờ trước check-in
        $hoursUntilCheckIn = now()->diffInHours($booking->check_in, false);
        if ($hoursUntilCheckIn < 24) {
            return response()->json([
                'message' => 'Không thể huỷ booking trong vòng 24 giờ trước ngày nhận phòng',
            ], 422);
        }

        // Tiến hành hủy (không cần cập nhật room_availability nữa vì dùng logic đếm trực tiếp)
        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => 'customer',
            ]);
        });

        // Trả kết quả
        $bookingData = $booking->fresh(['bookingRooms.roomType', 'hotel'])->toArray();
        $bookingData['total_price'] = number_format($booking->total_price, 0, '.', ',') . ' VND';
        foreach ($bookingData['booking_rooms'] as &$br) {
            $br['price_at_booking']        = number_format($br['price_at_booking'], 0, '.', ',') . ' VND';
            $br['room_type']['base_price'] = number_format($br['room_type']['base_price'], 0, '.', ',') . ' VND';
        }

        return response()->json([
            'message' => 'Huỷ booking thành công',
            'booking' => $bookingData,
        ]);
    }
}