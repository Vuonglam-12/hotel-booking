<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\CustomerNotification as Notification;

class PaymentController extends Controller
{
    // ==========================================
    // POST /api/payments/create
    // Tạo URL thanh toán VNPay
    // ==========================================
    public function createPayment(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:booking,id',
        ]);

        $customerId = auth('sanctum')->id();

        $booking = Booking::where('id', $request->booking_id)
            ->where('customer_id', $customerId)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($booking->expires_at && now()->gt($booking->expires_at)) {
            $booking->update(['status' => 'cancelled', 'cancelled_by' => 'system']);
            Notification::bookingCancelled(
                $booking->customer_id,
                $booking->id,
                $booking->load('hotel')->hotel->name
            );
            return response()->json([
                'message' => 'Booking đã hết hạn, vui lòng đặt lại',
            ], 422);
        }

        $existingSuccess = Payment::where('booking_id', $booking->id)
            ->where('payment_status', 'success')
            ->exists();

        if ($existingSuccess) {
            return response()->json([
                'message' => 'Booking này đã được thanh toán rồi',
            ], 422);
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

        $vnpayUrl = $this->buildVNPayUrl($booking, $txnRef);

        return response()->json([
            'message'     => 'Tạo URL thanh toán thành công',
            'payment_url' => $vnpayUrl,
            'txn_ref'     => $txnRef,
            'amount'      => number_format($booking->total_price, 0, '.', ',') . ' VND',
        ]);
    }

    // ==========================================
    // POST /api/payments/manual
    // Tạo payment + invoice cho banking/cash
    // Gọi từ frontend sau khi user xác nhận
    // ==========================================
    public function createManual(Request $request)
    {
        $request->validate([
            'booking_id'     => 'required|integer|exists:booking,id',
            'payment_method' => 'required|in:banking,cash',
        ]);

        $customerId = auth('sanctum')->id();

        $booking = Booking::where('id', $request->booking_id)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'completed'])
            ->firstOrFail();

        // Chống tạo 2 lần
        // $exists = Payment::where('booking_id', $booking->id)->exists();
        // if ($exists) {
        //     return response()->json(['message' => 'Booking này đã có payment rồi'], 422);
        // }

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'amount'         => $booking->total_price,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
            ]
        );
                // ✅ THÊM — banking QR đã thanh toán → completed
        if ($request->payment_method === 'banking') {
            $booking->update([
                'status'       => 'completed',
                'confirmed_at' => now(),
            ]);
            Notification::paymentSuccess(
                $booking->customer_id,
                $booking->id,
                $booking->load('hotel')->hotel->name,
                (int) $booking->total_price
            );
        }

        // Tạo invoice + gửi email kèm PDF
        InvoiceController::createFromPayment($booking, $payment);

        $booking->load('customer');
        Mail::to($booking->customer->email)
            ->send(new \App\Mail\BookingConfirmed($booking));

        return response()->json([
            'message' => 'Đã ghi nhận thanh toán ' . $request->payment_method,
        ]);
    }

    // ==========================================
    // GET /api/payments/{booking_id}
    // ==========================================
    public function show($booking_id)
    {
        $customerId = auth('sanctum')->id();

        $booking = Booking::where('id', $booking_id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $payment = Payment::where('booking_id', $booking_id)->first();

        return response()->json([
            'booking' => array_merge($booking->toArray(), [
                'total_price_formatted' => number_format($booking->total_price, 0, '.', ',') . ' VND',
            ]),
            'payment' => $payment ? array_merge($payment->toArray(), [
                'amount_formatted' => number_format($payment->amount, 0, '.', ',') . ' VND',
            ]) : null,
        ]);
    }
}