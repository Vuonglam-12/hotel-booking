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
        $search=app(\App\Services\HotelSearchService::class);
        $filters=$search->validate($request->all());
        $locations=$search->locations()->orderBy('name')->get();

        if (!array_intersect(array_keys($filters), ['city','star','search','location_id','hotel_id','check_in','guests','max_price','amenity'])) {
            $hotelSections = $locations->map(function ($location) {
                $sectionHotels = Hotel::with(['location', 'images' => fn($q) => $q->orderByDesc('is_primary')->orderBy('id')])
                    ->where('status', 'active')
                    ->where('location_id', $location->id)
                    ->withMin(['rooms' => fn($q) => $q->where('status', 'available')->offered()], 'price')
                    ->orderBy('avg_rating', 'desc')
                    ->take(6)
                    ->get();

                return [
                    'location' => $location,
                    'hotels' => $sectionHotels,
                    'hasMore' => $sectionHotels->count() > 5,
                ];
            })->filter(fn($section) => $section['hotels']->isNotEmpty())->values();

            $hotels = collect();
        } else {
            $hotels = $search->search($filters,9);
            $hotelSections = collect();
        }

        return view('home', compact('hotels', 'locations', 'hotelSections'));
    }

    // Trang chi tiết KS
    public function hotelDetail($id)
    {
        $hotel = Hotel::with([
            'location',
            'amenities',
            'images',
            'rooms.roomType',
            'rooms.images',
        ])
            ->where('status', 'active')
            ->findOrFail($id);

        $hotel->setRelation('rooms', app(\App\Services\HotelRoomTypeService::class)->applyToRooms($hotel->rooms));
        $reviewData=app(\App\Services\ReviewService::class)->getHotelReviews((int)$id,['per_page'=>6]);
        $reviewSummary=$reviewData['stats'];
        $hotel->review_count=$reviewSummary['total'];
        $reviews=$reviewData['reviews'];

        $similarHotels = Hotel::with([
            'location',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
        ])
            ->where('status', 'active')
            ->whereKeyNot($hotel->id)
            ->where('location_id', $hotel->location_id)
            ->orderByDesc('avg_rating')
            ->take(6)
            ->get();

        return view('hotel-detail', compact('hotel', 'reviews', 'similarHotels', 'reviewSummary'));
    }

    // Trang đặt phòng
    public function booking($id)
    {
        if (\App\Support\LocalBookingDemo::enabled()) {
            return redirect()->route('hotel.detail', ['id'=>$id]);
        }
        $hotel = Hotel::with(['rooms.roomType'])
            ->where('status', 'active')
            ->findOrFail($id);

        $hotel->setRelation('rooms', app(\App\Services\HotelRoomTypeService::class)->applyToRooms($hotel->rooms));
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

    // Trang blog
    public function blog()
    {
        return view('blog');
    }

    public function paymentResult(Request $request)
    {
        // Return URLs cannot establish receipt of funds. The guarded V2 route is read-only.
        return response()->view('payment.result', ['status' => 'error', 'message' => 'Thanh toán đang chờ đối soát, chưa thể xác nhận qua trang này.', 'booking' => null], 503)->header('Cache-Control', 'no-store');
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
