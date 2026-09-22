<?php

namespace App\Services;

use App\Http\Controllers\InvoiceController;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\CustomerNotification as Notification;
use App\Models\Payment;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingService
{
    public function create(int $customerId, array $data): Booking
    {
        $checkIn       = $data['check_in'];
        $checkOut      = $data['check_out'];
        $nights        = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $roomId        = $data['room_id'] ?? null;

        // Kiểm tra chồng ngày
        $this->checkAvailability($data, $checkIn, $checkOut, $roomId);

        // Chống spam đơn
        $this->checkDuplicate($customerId, $data, $checkIn, $checkOut, $roomId);

        $roomType      = RoomType::findOrFail($data['room_type_id']);
        $totalPrice    = $roomType->base_price * $nights * $data['quantity'];
        $initialStatus = $paymentMethod === 'banking' ? 'confirmed' : 'pending';

        // Lưu DB
        $booking = DB::transaction(function () use ($data, $customerId, $totalPrice, $roomType, $checkIn, $checkOut, $nights, $roomId, $initialStatus) {
            $booking = Booking::create([
                'customer_id'     => $customerId,
                'hotel_id'        => $data['hotel_id'],
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'num_guests'      => $data['num_guests'],
                'total_price'     => $totalPrice,
                'status'          => $initialStatus,
                'expires_at'      => now()->addMinutes(15),
                'special_request' => $data['special_request'] ?? null,
            ]);

            BookingRoom::create([
                'booking_id'       => $booking->id,
                'room_type_id'     => $data['room_type_id'],
                'room_id'          => $roomId,
                'price_at_booking' => $roomType->base_price,
                'nights'           => $nights,
                'quantity'         => $data['quantity'],
            ]);

            return $booking;
        });

        // Notification
        $this->sendBookingNotification($booking, $customerId, $paymentMethod, $totalPrice);

        // Payment + Invoice + Email
        $this->handlePayment($booking, $totalPrice, $paymentMethod);

        return $booking->load(['bookingRooms.roomType', 'bookingRooms.room', 'hotel']);
    }

    public function myBookings(int $customerId, array $filters): LengthAwarePaginator
    {
        $bookings = Booking::with(['hotel.imgaes', 'bookingRooms.roomType', 'bookingRooms.room', 'payment'])
            ->where('customer_id', $customerId)
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']), fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('id', 'like', '%' . $filters['search'] . '%')
                       ->orWhereHas('hotel', fn($q3) =>
                            $q3->where('name', 'like', '%' . $filters['search'] . '%')
                        )
                )
            )
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 10);

        $bookings->getCollection()->transform(function ($b) {
            $b->payment_method = $b->payment?->payment_method ?? null;
            return $b;
        });

        return $bookings;
    }

    public function getDetail(int $id, int $customerId): Booking
    {
        return Booking::with(['hotel.imgaes', 'bookingRooms.roomType', 'bookingRooms.room', 'payment'])
            ->where('customer_id', $customerId)
            ->findOrFail($id);
    }

    public function cancel(int $id, int $customerId): Booking
    {
        $booking = Booking::with('bookingRooms')
            ->where('customer_id', $customerId)
            ->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'confirmed', 'expired'])) {
            throw new \Exception('Không thể huỷ booking ở trạng thái ' . $booking->status);
        }

        $hoursUntilCheckIn = now()->diffInHours($booking->check_in, false);
        if ($hoursUntilCheckIn >= 0 && $hoursUntilCheckIn < 24) {
            throw new \Exception('Không thể huỷ booking trong vòng 24 giờ trước ngày nhận phòng');
        }

        $wasExpired = $booking->status === 'expired';

        DB::transaction(fn() => $booking->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => 'customer',
        ]));

        if ($wasExpired) {
            Notification::bookingExpired($booking->customer_id, $booking->id, $booking->hotel->name);
        } else {
            Notification::bookingCancelled($booking->customer_id, $booking->id, $booking->hotel->name);
        }

        return $booking->fresh(['bookingRooms.roomType', 'bookingRooms.room', 'hotel']);
    }

    // ── Private helpers ──────────────────────────────

    private function checkAvailability(array $data, string $checkIn, string $checkOut, ?int $roomId): void
    {
        if ($roomId) {
            $taken = DB::table('booking_room as br')
                ->join('booking as b', 'b.id', '=', 'br.booking_id')
                ->where('br.room_id', $roomId)
                ->whereNotIn('b.status', ['cancelled', 'expired'])
                ->where('b.check_in', '<', $checkOut)
                ->where('b.check_out', '>', $checkIn)
                ->exists();

            if ($taken) {
                throw new \Exception('Phòng này vừa có người nhanh tay đặt mất rồi. Vui lòng chọn phòng khác!');
            }
        } else {
            $totalRooms  = DB::table('room')
                ->where('hotel_id', $data['hotel_id'])
                ->where('room_type_id', $data['room_type_id'])
                ->where('status', 'available')
                ->count();

            $bookedRooms = DB::table('booking_room as br')
                ->join('booking as b', 'b.id', '=', 'br.booking_id')
                ->where('b.hotel_id', $data['hotel_id'])
                ->where('br.room_type_id', $data['room_type_id'])
                ->whereNotIn('b.status', ['cancelled', 'expired'])
                ->where('b.check_in', '<', $checkOut)
                ->where('b.check_out', '>', $checkIn)
                ->sum('br.quantity');

            if (($totalRooms - $bookedRooms) < $data['quantity']) {
                throw new \Exception('Không đủ phòng trống trong khoảng thời gian này.');
            }
        }
    }

    private function checkDuplicate(int $customerId, array $data, string $checkIn, string $checkOut, ?int $roomId): void
    {
        $exists = Booking::where('customer_id', $customerId)
            ->where('hotel_id', $data['hotel_id'])
            ->where('check_in', $checkIn)
            ->where('check_out', $checkOut)
            ->whereIn('status', ['pending', 'confirmed'])
            ->when($roomId, fn($q) =>
                $q->whereHas('bookingRooms', fn($q2) => $q2->where('room_id', $roomId))
            )
            ->exists();

        if ($exists) {
            throw new \Exception('Bạn đã có yêu cầu đặt phòng trùng ngày. Vui lòng xử lý đơn cũ trước.');
        }
    }

    private function sendBookingNotification(Booking $booking, int $customerId, string $paymentMethod, float $totalPrice): void
    {
        $hotelName = $booking->load('hotel')->hotel->name;

        if ($paymentMethod === 'banking') {
            Notification::bookingSuccess($customerId, $booking->id, $hotelName, $totalPrice);
        } else {
            Notification::create([
                'customer_id' => $customerId,
                'type'        => 'booking_pending',
                'data'        => [
                    'title'      => '📋 Đặt phòng đang chờ xác nhận',
                    'message'    => 'Booking #' . $booking->id . ' tại ' . $hotelName . ' đã được tạo. Vui lòng thanh toán tại quầy khi nhận phòng.',
                    'link'       => '/dashboard#bookings',
                    'booking_id' => $booking->id,
                ],
            ]);
        }
    }

    private function handlePayment(Booking $booking, float $totalPrice, string $paymentMethod): void
    {
        try {
            $payment = Payment::create([
                'booking_id'     => $booking->id,
                'amount'         => $totalPrice,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'banking' ? 'success' : 'pending',
                'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
            ]);

            if ($paymentMethod === 'banking') {
                InvoiceController::createFromPayment($booking, $payment);
                $booking->load('customer');
                Mail::to($booking->customer->email)->send(new \App\Mail\BookingConfirmed($booking));
            }
        } catch (\Exception $e) {
            Log::error('BookingService: Lỗi tạo invoice/email', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}