<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotification extends Model
{
    protected $table = 'customer_notifications'; 
    protected $fillable = [
        'customer_id',
        'type',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====
    public function user(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // ===== SCOPES =====
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $customerId)
    {
        return $query->where('customer_id', $customerId)->latest();
    }

    // ===== HELPERS =====
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public static function bookingSuccess(int $customerId, int $bookingId, string $hotelName, int $totalPrice): self
    {
        return self::create([
            'customer_id' => $customerId,
            'type'        => 'booking_success',
            'data'        => [
                'title'      => '🎉 Đặt phòng thành công!',
                'message'    => "Booking #{$bookingId} tại {$hotelName} đã được xác nhận. Tổng tiền: " . number_format($totalPrice, 0, ',', '.') . 'đ.',
                'link'       => '/dashboard#bookings',
                'booking_id' => $bookingId,
                'hotel_name' => $hotelName,
            ],
        ]);
    }

    public static function paymentSuccess(int $customerId, int $bookingId, string $hotelName, int $amount): self
    {
        return self::create([
            'customer_id' => $customerId,
            'type'        => 'payment_success',
            'data'        => [
                'title'      => '💳 Thanh toán thành công!',
                'message'    => "Thanh toán QR cho Booking #{$bookingId} tại {$hotelName} đã được xác nhận. Hóa đơn đã gửi vào email.",
                'link'       => '/dashboard#bookings',
                'booking_id' => $bookingId,
                'hotel_name' => $hotelName,
                'amount'     => $amount,
            ],
        ]);
    }

    public static function bookingCancelled(int $customerId, int $bookingId, string $hotelName): self
    {
        return self::create([
            'customer_id' => $customerId,
            'type'        => 'booking_cancelled',
            'data'        => [
                'title'      => '❌ Booking đã bị hủy',
                'message'    => "Booking #{$bookingId} tại {$hotelName} đã được hủy thành công.",
                'link'       => '/dashboard#bookings',
                'booking_id' => $bookingId,
                'hotel_name' => $hotelName,
            ],
        ]);
    }

    public static function itineraryReady(int $customerId, int $itineraryId, string $title): self
    {
        return self::create([
            'customer_id'  => $customerId,
            'type'         => 'itinerary_ready',
            'data'         => [
                'title'        => '🗺 Lịch trình đã sẵn sàng!',
                'message'      => "AI đã tạo xong lịch trình \"{$title}\" cho bạn. Xem ngay!",
                'link'         => '/dashboard#itineraries',
                'itinerary_id' => $itineraryId,
            ],
        ]);
    }
    public static function reviewSubmitted(int $customerId, int $reviewId, string $hotelName, int $rating): self
    {
        $starDisplay = str_repeat('⭐', $rating);
        return self::create([
            'customer_id' => $customerId,
            'type'        => 'review_submitted',
            'data'        => [
                'title'     => '⭐ Cảm ơn bạn đã đánh giá!',
                'message'   => "Bạn vừa đánh giá {$starDisplay} cho {$hotelName}. Cảm ơn bạn đã chia sẻ trải nghiệm!",
                'link'      => '/dashboard#reviews',
                'review_id' => $reviewId,
                'hotel_name'=> $hotelName,
                'rating'    => $rating,
            ],
        ]);
    }

    public static function bookingExpired(int $customerId, int $bookingId, string $hotelName): self
    {
        return self::create([
            'customer_id' => $customerId,
            'type'        => 'booking_expired',
            'data'        => [
                'title'      => '⏰ Booking đã hết hạn',
                'message'    => "Booking #{$bookingId} tại {$hotelName} đã hết thời gian thanh toán và bị huỷ tự động.",
                'link'       => '/dashboard#bookings',
                'booking_id' => $bookingId,
                'hotel_name' => $hotelName,
            ],
        ]);
    }
}