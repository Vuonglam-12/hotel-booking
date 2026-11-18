<?php

namespace App\Services;

use App\Http\Controllers\InvoiceController;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\CustomerNotification as Notification;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private RoomAvailabilityService $roomAvailability) {}

    public function create(int $customerId, array $data): Booking
    {
        Validator::make($data, [
            'quantity' => ['bail', 'required', 'integer', 'min:1', 'max:10',
                function ($attribute, $value, $fail) {
                    if (!is_int($value) && !is_string($value)) {
                        $fail('Số lượng phòng phải là số nguyên dương.');
                    }
                }],
            'num_guests' => ['bail', 'required', 'integer', 'min:1',
                function ($attribute, $value, $fail) {
                    if (!is_int($value) && !is_string($value)) {
                        $fail('Số khách phải là số nguyên dương.');
                    }
                }],
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
        ], [
            'quantity.integer' => 'Số lượng phòng phải là số nguyên dương.',
            'quantity.min' => 'Số lượng phòng phải lớn hơn 0.',
            'check_in.date_format' => 'Ngày nhận phòng phải hợp lệ và có định dạng YYYY-MM-DD.',
            'check_out.date_format' => 'Ngày trả phòng phải hợp lệ và có định dạng YYYY-MM-DD.',
            'check_out.after' => 'Ngày trả phòng phải sau ngày nhận phòng.',
        ])->validate();

        $checkIn       = $data['check_in'];
        $checkOut      = $data['check_out'];
        $nights        = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $roomId        = isset($data['room_id']) ? (int) $data['room_id'] : null;
        $initialStatus = $paymentMethod === 'banking' ? 'confirmed' : 'pending';

        $booking = DB::transaction(function () use ($data, $customerId, $checkIn, $checkOut, $nights, $roomId, $initialStatus) {
            // Giữ khóa đến khi ghi xong booking. UPDATE trạng thái khách sạn
            // phải chờ khóa này, kể cả khi request đã qua validation trước đó.
            $hotel = Hotel::where('id', $data['hotel_id'])->lockForUpdate()->first();

            if (!$hotel || $hotel->status !== 'active') {
                throw ValidationException::withMessages([
                    'hotel_id' => 'Khách sạn hiện không nhận đặt phòng.',
                ]);
            }

            $this->validateRoomSelection($data, $roomId);
            $this->roomAvailability->validateCapacity(
                (int) $data['hotel_id'], (int) $data['room_type_id'], (int) $data['quantity'], (int) $data['num_guests'], $roomId
            );
            $this->checkAvailability($data, $checkIn, $checkOut, $roomId);
            $this->checkDuplicate($customerId, $data, $checkIn, $checkOut, $roomId);

            $roomType = app(HotelRoomTypeService::class)->effective((int)$data['hotel_id'], (int)$data['room_type_id']);
            $totalPrice = $roomType->base_price * $nights * $data['quantity'];
            if (!is_finite((float) $totalPrice) || $totalPrice > 9999999999.99) {
                throw ValidationException::withMessages(['total_price' => 'Tổng tiền vượt giới hạn lưu trữ. Vui lòng giảm số lượng phòng hoặc khoảng lưu trú.']);
            }

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

        $totalPrice = (float) $booking->total_price;

        // Notification
        $this->sendBookingNotification($booking, $customerId, $paymentMethod, $totalPrice);

        // Payment + Invoice + Email
        $this->handlePayment($booking, $totalPrice, $paymentMethod);

        return app(HotelRoomTypeService::class)->applyToBooking($booking->load(['bookingRooms.roomType', 'bookingRooms.room', 'hotel']));
    }

    public function myBookings(int $customerId, array $filters): LengthAwarePaginator
    {
        if (\App\Support\LocalBookingDemo::enabled()) app(LocalBookingDemoService::class)->expireDue($customerId);
        $bookings = Booking::with(['hotel.images', 'bookingRooms.roomType', 'bookingRooms.room', 'payments', 'invoice:id,booking_id,invoice_no,issued_at,total'])
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
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 10))));

        $bookings->getCollection()->transform(function ($b) {
            app(HotelRoomTypeService::class)->applyToBooking($b);
            $b->hotel?->append('cover_image_url');
            $this->decoratePayment($b);
            return $b;
        });

        return $bookings;
    }

    public function getDetail(int $id, int $customerId): Booking
    {
        $booking = Booking::with(['hotel.images', 'bookingRooms.roomType', 'bookingRooms.room', 'payments', 'invoice:id,booking_id,invoice_no,issued_at,total'])
            ->where('customer_id', $customerId)
            ->findOrFail($id);
        $booking->hotel?->append('cover_image_url');
        app(HotelRoomTypeService::class)->applyToBooking($booking);
        $this->decoratePayment($booking);
        return $booking;
    }

    private function decoratePayment(Booking $booking): void
    {
        // A retry may precede the successful payment; never infer success from booking status.
        $payments = $booking->payments->sortByDesc('id');
        $payment = $payments->firstWhere('payment_status', 'success') ?? $payments->first();
        if ($payment) {
            $payment->display_status = $payment->payment_status === 'success' && in_array($booking->status, ['cancelled', 'expired'], true)
                ? 'reconciliation' : $payment->payment_status;
        }
        $booking->setRelation('payment', $payment);
        $booking->payment_method = $payment?->payment_method;
        if (\App\Support\LocalBookingDemo::isDemo((int)$booking->id)) {
            $meta = DB::table('booking_demo')->where('booking_id',$booking->id)->first();
            $booking->is_demo = true;
            $booking->demo_room_name = $meta->room_name;
            $booking->expires_at_utc = gmdate('Y-m-d\TH:i:s\Z',$meta->expires_at_epoch);
            $booking->banking_policy = ['confirmation_allowed'=>$booking->status==='pending' && $meta->expires_at_epoch>time(),
                'payment_deadline'=>$booking->expires_at_utc];
            $booking->created_at = \Carbon\Carbon::createFromTimestampUTC($meta->created_at_epoch);
            $booking->bookingRooms->each(function($line)use($meta){if($line->roomType){$type=clone $line->roomType;$type->name=$meta->room_name;$line->setRelation('roomType',$type);}});
            $booking->bookingRooms->each(function($line){$line->nightly_rates=app(RoomPricingService::class)->snapshot((int)$line->id);});
        }
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

        return app(HotelRoomTypeService::class)->applyToBooking($booking->fresh(['bookingRooms.roomType', 'bookingRooms.room', 'hotel']));
    }

    // ── Private helpers ──────────────────────────────

    private function validateRoomSelection(array $data, ?int $roomId): void
    {
        $rooms = Room::where('hotel_id', $data['hotel_id'])
            ->where('room_type_id', $data['room_type_id'])->offered();

        if ($roomId !== null) {
            if (!(clone $rooms)->where('id', $roomId)->where('status', 'available')->exists()) {
                throw ValidationException::withMessages([
                    'room_id' => 'Phòng không thuộc khách sạn hoặc loại phòng đã chọn, hoặc không thể đặt.',
                ]);
            }

            if ((int) $data['quantity'] !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => 'Mỗi phòng cụ thể chỉ có thể đặt số lượng 1.',
                ]);
            }
        } elseif (!$rooms->exists()) {
            throw ValidationException::withMessages([
                'room_type_id' => 'Khách sạn không có loại phòng đã chọn.',
            ]);
        }
    }

    private function checkAvailability(array $data, string $checkIn, string $checkOut, ?int $roomId): void
    {
        if ($roomId !== null) {
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
        }

        $availability = $this->roomAvailability->forType(
            (int) $data['hotel_id'], (int) $data['room_type_id'], $checkIn, $checkOut
        );

        if ($availability['available_rooms'] < $data['quantity']) {
            throw new \Exception('Không đủ phòng trống trong khoảng thời gian này.');
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
