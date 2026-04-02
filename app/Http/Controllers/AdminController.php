<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ==========================================
    // MIDDLEWARE CHECK — chỉ staff/admin mới vào được
    // Gọi hàm này đầu mỗi function
    // ==========================================
    private function checkAdmin(Request $request)
    {
        $staff = auth('sanctum')->user();

        // Kiểm tra user có phải là Staff không
        // Staff model dùng bảng staff, không phải customer
        if (!$staff || !($staff instanceof \App\Models\Staff)) {
            abort(403, 'Chỉ admin mới có quyền truy cập');
        }

        return $staff;
    }

    // ==========================================
    // DASHBOARD — tổng quan hệ thống
    // GET /api/admin/dashboard
    // ==========================================
    public function dashboard(Request $request)
    {
        $this->checkAdmin($request);

        // Thống kê tổng quan
        $stats = [
            'total_hotels'   => Hotel::where('status', 'active')->count(),
            'total_customers'=> Customer::count(),
            'total_bookings' => Booking::count(),

            // Booking theo trạng thái
            'bookings_pending'   => Booking::where('status', 'pending')->count(),
            'bookings_confirmed' => Booking::where('status', 'confirmed')->count(),
            'bookings_cancelled' => Booking::where('status', 'cancelled')->count(),

            // Doanh thu tháng này
            'revenue_this_month' => Payment::where('payment_status', 'success')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),

            // Doanh thu tổng
            'revenue_total' => Payment::where('payment_status', 'success')->sum('amount'),

            // Booking mới nhất
            'recent_bookings' => Booking::with(['customer:id,name,email', 'hotel:id,name'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
        ];

        return response()->json($stats);
    }

    // ==========================================
    // QUẢN LÝ BOOKING
    // ==========================================

    // Xem tất cả booking (có filter)
    // GET /api/admin/bookings
    public function bookings(Request $request)
    {
        $this->checkAdmin($request);

        $query = Booking::with(['customer:id,name,email,phone', 'hotel:id,name', 'payment'])
            ->orderBy('created_at', 'desc');

        // Filter theo trạng thái
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter theo KS
        if ($request->hotel_id) {
            $query->where('hotel_id', $request->hotel_id);
        }

        // Filter theo ngày
        if ($request->date_from) {
            $query->where('check_in', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('check_out', '<=', $request->date_to);
        }

        // Tìm kiếm theo tên khách
        if ($request->search) {
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
            );
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // Cập nhật trạng thái booking
    // PUT /api/admin/bookings/{id}/status
    public function updateBookingStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        $booking = Booking::findOrFail($id);
        $oldStatus = $booking->status;
        $booking->update(['status' => $request->status]);

        return response()->json([
            'message' => "Đã cập nhật booking #{$id}: {$oldStatus} → {$request->status}",
            'booking' => $booking,
        ]);
    }

    // ==========================================
    // QUẢN LÝ KHÁCH SẠN
    // ==========================================

    // Xem tất cả KS
    // GET /api/admin/hotels
    public function hotels(Request $request)
    {
        $this->checkAdmin($request);

        $hotels = Hotel::with(['location', 'rooms'])
            ->withCount(['rooms', 'bookings' => fn($q) => $q->where('status', 'confirmed')])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($hotels);
    }

    // Cập nhật trạng thái KS (active/inactive/closed)
    // PUT /api/admin/hotels/{id}/status
    public function updateHotelStatus(Request $request, $id)
    {
        $this->checkAdmin($request);

        $request->validate([
            'status' => 'required|in:active,inactive,closed',
        ]);

        Hotel::findOrFail($id)->update(['status' => $request->status]);

        return response()->json(['message' => 'Đã cập nhật trạng thái KS']);
    }

    // ==========================================
    // QUẢN LÝ KHÁCH HÀNG
    // ==========================================

    // Xem tất cả khách hàng
    // GET /api/admin/customers
    public function customers(Request $request)
    {
        $this->checkAdmin($request);

        $query = Customer::withCount(['bookings', 'reviews', 'wishlist'])
            ->orderBy('created_at', 'desc');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // ==========================================
    // QUẢN LÝ REVIEW
    // ==========================================

    // Xem tất cả review (kể cả review xấu)
    // GET /api/admin/reviews
    public function reviews(Request $request)
    {
        $this->checkAdmin($request);

        $query = Review::with(['customer:id,name', 'hotel:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter theo rating thấp để kiểm duyệt
        if ($request->max_rating) {
            $query->where('rating', '<=', $request->max_rating);
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    // Xóa review vi phạm
    // DELETE /api/admin/reviews/{id}
    public function deleteReview($id)
    {
        $review = Review::findOrFail($id);
        $hotelId = $review->hotel_id;
        $review->delete();

        // Cập nhật lại avg_rating
        $avg = Review::where('hotel_id', $hotelId)->avg('rating') ?? 0;
        Hotel::where('id', $hotelId)->update(['avg_rating' => round($avg, 1)]);

        return response()->json(['message' => 'Đã xóa review']);
    }

    // ==========================================
    // BÁO CÁO DOANH THU
    // GET /api/admin/revenue
    // ==========================================
    public function revenue(Request $request)
    {
        $this->checkAdmin($request);

        // Doanh thu theo tháng trong năm hiện tại
        $monthlyRevenue = Payment::where('payment_status', 'success')
            ->whereYear('paid_at', now()->year)
            ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top KS doanh thu cao nhất
        $topHotels = DB::table('payment as p')
            ->join('booking as b', 'b.id', '=', 'p.booking_id')
            ->join('hotel as h', 'h.id', '=', 'b.hotel_id')
            ->where('p.payment_status', 'success')
            ->selectRaw('h.id, h.name, SUM(p.amount) as revenue, COUNT(p.id) as transactions')
            ->groupBy('h.id', 'h.name')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        return response()->json([
            'monthly_revenue' => $monthlyRevenue,
            'top_hotels'      => $topHotels,
            'total_revenue'   => Payment::where('payment_status', 'success')->sum('amount'),
        ]);
    }
}