<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            ->where('status', 'pending')
            ->firstOrFail();

        // Chống tạo 2 lần
        $exists = Payment::where('booking_id', $booking->id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Booking này đã có payment rồi'], 422);
        }

        $payment = Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $booking->total_price,
            'payment_method' => $request->payment_method,
            'payment_status' => 'pending',
            'transaction_id' => 'MANUAL_' . time() . '_' . $booking->id,
        ]);

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
    // GET /api/payments/vnpay-return
    // VNPay callback sau khi user thanh toán
    // ==========================================
    public function vnpayReturn(Request $request)
    {
        $vnpData       = $request->all();
        $vnpSecureHash = $vnpData['vnp_SecureHash'] ?? '';

        unset($vnpData['vnp_SecureHash']);
        unset($vnpData['vnp_SecureHashType']);

        ksort($vnpData);

        // ✅ FIX: hash raw string, không urlencode
        $hashData = '';
        foreach ($vnpData as $key => $value) {
            $hashData .= $key . '=' . $value . '&';
        }
        $hashData = rtrim($hashData, '&');

        $secureHash = hash_hmac('sha512', $hashData, config('services.vnpay.hash_secret'));

        if ($secureHash !== $vnpSecureHash) {
            return response()->json(['message' => 'Chữ ký không hợp lệ'], 400);
        }

        $txnRef       = $request->vnp_TxnRef;
        $responseCode = $request->vnp_ResponseCode;
        $transNo      = $request->vnp_TransactionNo;

        $payment = Payment::where('transaction_id', $txnRef)->first();

        if (!$payment) {
            return response()->json(['message' => 'Không tìm thấy giao dịch'], 404);
        }

        // IDEMPOTENCY — tránh xử lý 2 lần
        if ($payment->payment_status === 'success') {
            return response()->json([
                'message' => 'Giao dịch đã được xử lý trước đó',
                'status'  => 'success',
            ]);
        }

        if ($responseCode === '00') {
            DB::transaction(function () use ($payment, $transNo) {
                $payment->update([
                    'payment_status' => 'success',
                    'paid_at'        => now(),
                ]);

                Booking::where('id', $payment->booking_id)
                    ->update([
                        'status'       => 'confirmed',
                        'confirmed_at' => now(),
                    ]);

                $booking = Booking::find($payment->booking_id);
                InvoiceController::createFromPayment($booking, $payment);

                $booking->load('customer');
                Mail::to($booking->customer->email)
                    ->send(new \App\Mail\BookingConfirmed($booking));
            });

            return response()->json([
                'message'  => 'Thanh toán thành công',
                'status'   => 'success',
                'trans_no' => $transNo,
            ]);

        } else {
            $payment->update(['payment_status' => 'failed']);

            return response()->json([
                'message' => 'Thanh toán thất bại — mã lỗi: ' . $responseCode,
                'status'  => 'failed',
            ]);
        }
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

    // ==========================================
    // POST /api/payments/retry
    // ==========================================
    public function retry(Request $request)
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
            return response()->json([
                'message' => 'Booking đã hết hạn, vui lòng đặt lại',
            ], 422);
        }

        // Thêm random suffix tránh trùng txnRef
        $txnRef = time() . '_' . $booking->id . '_' . rand(100, 999);

        Payment::where('booking_id', $booking->id)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => 'failed']);

        Payment::create([
            'booking_id'     => $booking->id,
            'amount'         => $booking->total_price,
            'payment_method' => 'vnpay',
            'payment_status' => 'pending',
            'transaction_id' => $txnRef,
            'created_at'     => now(),
        ]);

        $vnpayUrl = $this->buildVNPayUrl($booking, $txnRef);

        return response()->json([
            'message'     => 'Tạo lại URL thanh toán thành công',
            'payment_url' => $vnpayUrl,
            'txn_ref'     => $txnRef,
            'amount'      => number_format($booking->total_price, 0, '.', ',') . ' VND',
        ]);
    }

    // ==========================================
    // Hàm nội bộ — tạo URL VNPay
    // ==========================================
    private function buildVNPayUrl(Booking $booking, string $txnRef): string
    {
        $vnpUrl     = config('services.vnpay.url');
        $tmnCode    = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');
        $returnUrl  = config('services.vnpay.return_url');

        $amount = (int)($booking->total_price * 100);

        $inputData = [
            'vnp_Version'    => '2.1.0',
            'vnp_TmnCode'    => $tmnCode,
            'vnp_Amount'     => $amount,
            'vnp_Command'    => 'pay',
            'vnp_CreateDate' => now()->timezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => request()->ip(),
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toan booking #' . $booking->id,
            'vnp_OrderType'  => 'other',
            'vnp_ReturnUrl'  => $returnUrl,
            'vnp_TxnRef'     => $txnRef,
            'vnp_ExpireDate' => now()->timezone('Asia/Ho_Chi_Minh')->addMinutes(15)->format('YmdHis'),
        ];

        ksort($inputData);

        $hashData = '';
        $query    = '';
        foreach ($inputData as $key => $value) {
            // ✅ FIX: hashData dùng raw string, query mới urlencode
            $hashData .= $key . '=' . $value . '&';
            $query    .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $hashData = rtrim($hashData, '&');
        $query    = rtrim($query, '&');

        $vnpSecureHash = hash_hmac('sha512', $hashData, $hashSecret);

        return $vnpUrl . '?' . $query . '&vnp_SecureHash=' . $vnpSecureHash;
    }
}