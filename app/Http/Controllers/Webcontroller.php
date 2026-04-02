<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebController extends Controller
{
    // Trang chủ — hiển thị danh sách KS + search
    public function home(Request $request)
    {
        $query = Hotel::with(['location', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('status', 'active')
            ->withMin(['rooms' => fn($q) => $q->where('status', 'available')], 'price');

        // Filter theo thành phố
        if ($request->city) {
            $query->whereHas('location', fn($q) => $q->where('name', 'like', '%' . $request->city . '%'));
        }

        // Filter theo số sao
        if ($request->star) {
            $query->where('star_rating', $request->star);
        }

        // Tìm kiếm theo tên
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $hotels    = $query->orderBy('avg_rating', 'desc')->paginate(9);
        $locations = Location::orderBy('name')->get();

        return view('home', compact('hotels', 'locations'));
    }

    // Trang chi tiết KS
    public function hotelDetail($id)
    {
        $hotel = Hotel::with(['location', 'amenities', 'images', 'rooms.roomType'])
            ->where('status', 'active')
            ->findOrFail($id);

        return view('hotel-detail', compact('hotel'));
    }

    // Trang đặt phòng
    public function booking($id)
    {
        $hotel = Hotel::with(['rooms.roomType'])
            ->where('status', 'active')
            ->findOrFail($id);

        return view('booking', compact('hotel'));
    }

    // Trang dashboard user (sau khi đăng nhập)
    public function dashboard()
    {
        return view('dashboard');
    }

    // Trang login
    public function login()
    {
        return view('auth.login');
    }

    // Trang register
    public function register()
    {
        return view('auth.register');
    }

    public function paymentResult(Request $request)
    {
        // Verify chữ ký VNPay
        $vnpData       = $request->all();
        $vnpSecureHash = $vnpData['vnp_SecureHash'] ?? '';

        unset($vnpData['vnp_SecureHash']);
        unset($vnpData['vnp_SecureHashType']);
        ksort($vnpData);

        $hashData = '';
        foreach ($vnpData as $key => $value) {
            $hashData .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $hashData = rtrim($hashData, '&');

        $secureHash = hash_hmac('sha512', $hashData, config('services.vnpay.hash_secret'));

        // Hash không khớp — giả mạo
        if ($secureHash !== $vnpSecureHash) {
            return view('payment.result', [
                'status'  => 'error',
                'message' => 'Chữ ký không hợp lệ',
                'booking' => null,
            ]);
        }

        $txnRef       = $request->vnp_TxnRef;
        $responseCode = $request->vnp_ResponseCode;

        $payment = \App\Models\Payment::with('booking.hotel')->where('transaction_id', $txnRef)->first();

        if (!$payment) {
            return view('payment.result', [
                'status'  => 'error',
                'message' => 'Không tìm thấy giao dịch',
                'booking' => null,
            ]);
        }

        // Idempotency — đã xử lý rồi thì bỏ qua
        if ($payment->payment_status !== 'success' && $responseCode === '00') {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
                $payment->update([
                    'payment_status' => 'success',
                    'paid_at'        => now(),
                ]);
                \App\Models\Booking::where('id', $payment->booking_id)
                    ->update(['status' => 'confirmed', 'confirmed_at' => now()]);

                // Tạo invoice
                $booking = \App\Models\Booking::find($payment->booking_id);
                \App\Http\Controllers\InvoiceController::createFromPayment($booking, $payment);

                // Gửi email
                $booking->load(['hotel', 'bookingRooms.roomType', 'customer']);
                try {
                    \Illuminate\Support\Facades\Mail::to($booking->customer->email)
                        ->send(new \App\Mail\BookingConfirmed($booking));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Email error: ' . $e->getMessage());
                }
            });
        } elseif ($responseCode !== '00') {
            $payment->update(['payment_status' => 'failed']);
        }

        $payment->refresh();

        return view('payment.result', [
            'status'  => $responseCode === '00' ? 'success' : 'failed',
            'message' => $responseCode === '00' ? 'Thanh toán thành công' : 'Thanh toán thất bại — mã lỗi: ' . $responseCode,
            'booking' => $payment->booking,
            'payment' => $payment,
        ]);
    }
}