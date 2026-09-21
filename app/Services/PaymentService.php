<?php

namespace App\Services\Payment;

use App\Http\Controllers\InvoiceController;
use App\Models\Booking;
use App\Models\CustomerNotification as Notification;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function createVNPayUrl(int $customerId, int $bookingId): array
    {
        $booking = Booking::where('id', $bookingId)
            ->where('customer_id', $customerId)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($booking->expires_at && now()->gt($booking->expires_at)) {
            $booking->update(['status' => 'cancelled', 'cancelled_by' => 'system']);
            Notification::bookingCancelled($booking->customer_id, $booking->id, $booking->load('hotel')->hotel->name);
            throw new \Exception('Booking đã hết hạn, vui lòng đặt lại');
        }

        if (Payment::where('booking_id', $booking->id)->where('payment_status', 'success')->exists()) {
            throw new \Exception('Booking này đã được thanh toán rồi');
        }

        $txnRef = time() . '_' . $booking->id;

        Payment::updateOrCreate(
            ['booking_id' => $booking->id, 'payment_status' => 'pending'],
            [
                'amount'         => $booking->total_price,
                'payment_method' => 'vnpay',
                'payment_status' => 'pending',
                'transaction_id' => $txnRef,
                'created_at'     => now(),
            ]
        );

        return [
            'message'     => 'Tạo URL thanh toán thành công',
            'payment_url' => $this->buildVNPayUrl($booking, $txnRef),
            'txn_ref'     => $txnRef,
            'amount'      => number_format($booking->total_price, 0, '.', ',') . ' VND',
        ];
    }

    public function createManual(int $customerId, int $bookingId, string $method): void
    {
        $booking = Booking::where('id', $bookingId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'completed'])
            ->firstOrFail();

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'amount'         => $booking->total_price,
                'payment_method' => $method,
                'payment_status' => 'pending',
                'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
            ]
        );

        if ($method === 'banking') {
            $booking->update(['status' => 'completed', 'confirmed_at' => now()]);
            Notification::paymentSuccess(
                $booking->customer_id,
                $booking->id,
                $booking->load('hotel')->hotel->name,
                (int) $booking->total_price
            );
        }

        InvoiceController::createFromPayment($booking, $payment);
        $booking->load('customer');
        Mail::to($booking->customer->email)->send(new \App\Mail\BookingConfirmed($booking));
    }

    public function getDetail(int $customerId, int $bookingId): array
    {
        $booking = Booking::where('id', $bookingId)->where('customer_id', $customerId)->firstOrFail();
        $payment = Payment::where('booking_id', $bookingId)->first();

        return [
            'booking' => array_merge($booking->toArray(), [
                'total_price_formatted' => number_format($booking->total_price, 0, '.', ',') . ' VND',
            ]),
            'payment' => $payment ? array_merge($payment->toArray(), [
                'amount_formatted' => number_format($payment->amount, 0, '.', ',') . ' VND',
            ]) : null,
        ];
    }

    private function buildVNPayUrl(Booking $booking, string $txnRef): string
    {
        // VNPay URL builder — giữ nguyên logic cũ nếu có
        // Placeholder — thay bằng logic VNPay thật nếu cần
        return config('app.url') . '/api/payments/vnpay?txn=' . $txnRef;
    }
}