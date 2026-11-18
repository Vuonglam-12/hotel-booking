<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Invoice;
use Carbon\Carbon;

/** Read-only values shared by the booking email and PDF views. */
final class BookingDocumentPresentation
{
    public static function for(Booking $booking, ?Invoice $invoice = null, mixed $bookingDetailsUrl = null): object
    {
        $payment = $invoice ? $invoice->payment : $booking->payment;
        $hotel = $booking->hotel;
        $url = is_string($bookingDetailsUrl) ? trim($bookingDetailsUrl) : '';
        $parts = parse_url($url);

        return (object) [
            'hotel' => $hotel,
            'customer' => $booking->customer,
            'rooms' => $booking->bookingRooms,
            'payment' => $payment,
            'isPaid' => $payment?->payment_status === 'success',
            'isConfirmed' => in_array($booking->status, ['confirmed', 'completed'], true),
            'paymentMethod' => [
                'banking' => 'Chuyển khoản ngân hàng',
                'vnpay' => 'VNPay',
                'cash' => 'Tiền mặt',
                'credit_card' => 'Thẻ tín dụng',
            ][$payment?->payment_method ?? ''] ?? ($payment?->payment_method ?: 'Chưa có thông tin'),
            'paymentStatus' => [
                'success' => 'Đã thanh toán',
                'pending' => 'Chờ thanh toán',
                'failed' => 'Thanh toán thất bại',
                'expired' => 'Đã hết hạn thanh toán',
                'refunded' => 'Đã hoàn tiền',
                'cancelled' => 'Đã hủy',
            ][$payment?->payment_status ?? ''] ?? 'Chưa xác nhận thanh toán',
            'supportEmail' => filter_var($hotel?->email, FILTER_VALIDATE_EMAIL) ?: null,
            'supportPhone' => $hotel?->phone,
            // The current delivery flow supplies no detail URL. Never invent one.
            'detailsUrl' => filter_var($url, FILTER_VALIDATE_URL)
                && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                && ! isset($parts['user']) && ! isset($parts['pass']) ? $url : null,
        ];
    }

    public static function money(mixed $amount): string
    {
        return is_numeric($amount) ? number_format((float) $amount, 0, ',', '.').' VND' : 'Chưa có thông tin';
    }

    public static function date(mixed $date, string $format = 'd/m/Y'): string
    {
        if (blank($date)) {
            return 'Chưa có thông tin';
        }

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Throwable $exception) {
            return 'Chưa có thông tin';
        }
    }
}
