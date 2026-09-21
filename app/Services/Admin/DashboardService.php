<?php
// Chứa Service để cung cấp dữ liệu cho Dashboard của Admin, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Review;

// Lớp này cung cấp các phương thức để lấy dữ liệu tổng quan cho Dashboard của Admin, bao gồm:
class DashboardService
{
    // Lấy dữ liệu tổng quan cho Dashboard, trả về mảng chứa các chỉ số và danh sách booking/review mới
    public function getSummary(): array
    {
        $now       = now();
        $thisMonth = $now->month;
        $thisYear  = $now->year;
        $lastMonth = $now->copy()->subMonth(); // lấy thời điểm của tháng trước bằng cách trừ đi 1 tháng từ thời điểm hiện tại

        return [
            'total_bookings'       => Booking::count(), // đếm tổng số booking trong database
            'booking_change'       => $this->percentChange( // tính phần trăm thay đổi số booking so với tháng trước, sử dụng phương thức percentChange để tính toán, truyền vào số booking của tháng trước và số booking của tháng hiện tại
                Booking::whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year)->count(), // đếm số booking được tạo trong tháng trước, sử dụng whereMonth để lọc theo tháng của created_at, so sánh với tháng của lastMonth, thêm điều kiện whereYear để đảm bảo chỉ đếm booking của tháng trước cùng năm, tránh đếm nhầm booking tạo vào tháng này nhưng của năm trước
                Booking::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count() // đếm số booking được tạo trong tháng hiện tại, sử dụng whereMonth để lọc theo tháng của created_at, so sánh với tháng hiện tại, thêm điều kiện whereYear để đảm bảo chỉ đếm booking của tháng hiện tại cùng năm, tránh đếm nhầm booking tạo vào tháng này nhưng của năm trước
            ),
            'monthly_revenue'      => $this->revenueOfMonth($thisMonth, $thisYear), // tính doanh thu của tháng hiện tại bằng cách gọi phương thức revenueOfMonth với tham số là tháng và năm hiện tại
            'revenue_change'       => $this->percentChange( // tính phần trăm thay đổi doanh thu so với tháng trước, sử dụng phương thức percentChange để tính toán, truyền vào doanh thu của tháng trước và doanh thu của tháng hiện tại
                $this->revenueOfMonth($lastMonth->month, $lastMonth->year), 
                $this->revenueOfMonth($thisMonth, $thisYear)
            ),
            'new_customers'        => Customer::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count(),
            'customer_change'      => $this->percentChange(
                Customer::whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year)->count(),
                Customer::whereMonth('created_at', $thisMonth)->whereYear('created_at', $thisYear)->count()
            ),
            'pending_reviews'      => Review::where('status', 'pending')->count(),
            'pending_bookings'     => Booking::where('status', 'pending')->count(),
            'recent_bookings'      => $this->recentBookings(),
            'pending_reviews_list' => $this->pendingReviewsList(),
            'revenue_7days'        => $this->revenue7Days(),
        ];
    }

    // Tính doanh thu của một tháng cụ thể, trả về tổng số tiền đã thanh toán thành công trong tháng đó
    private function revenueOfMonth(int $month, int $year): float
    {
        return Payment::where('payment_status', 'success')
            ->whereMonth('paid_at', $month)
            ->whereYear('paid_at', $year)
            ->sum('amount');
    }

    // Tính phần trăm thay đổi giữa hai giá trị, trả về phần trăm làm tròn, hoặc null nếu giá trị cũ là 0 để tránh chia cho 0
    private function percentChange(float $old, float $new): ?int
    {
        return $old > 0 ? round((($new - $old) / $old) * 100) : null;
    }

    // Lấy danh sách 6 booking mới nhất, trả về Collection chứa thông tin booking và khách hàng
    private function recentBookings(): \Illuminate\Support\Collection
    {
        return Booking::with(['customer:id,name', 'hotel:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'status'      => $b->status,
                'total_price' => $b->total_price,
                'user'        => $b->customer ? ['name' => $b->customer->name] : null,
                'room'        => ['hotel' => $b->hotel ? ['name' => $b->hotel->name] : null],
            ]);
    }

    // Lấy danh sách 5 review đang chờ duyệt mới nhất, trả về Collection chứa thông tin review, khách hàng và khách sạn
    private function pendingReviewsList(): \Illuminate\Support\Collection
    {
        return Review::with(['customer:id,name', 'hotel:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'created_at' => $r->created_at,
                'user'       => $r->customer ? ['name' => $r->customer->name] : null,
                'hotel'      => $r->hotel    ? ['name' => $r->hotel->name]    : null,
            ]);
    }

    // Lấy doanh thu của 7 ngày gần nhất, trả về Collection chứa label ngày và số tiền đã thanh toán thành công trong ngày đó
    private function revenue7Days(): \Illuminate\Support\Collection
    {
        $dayNames = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

        return collect(range(6, 0))->map(function ($daysAgo) use ($dayNames) {
            $date = now()->subDays($daysAgo);
            return [
                'label'  => $dayNames[$date->dayOfWeek],
                'amount' => (int) Payment::where('payment_status', 'success')
                    ->whereDate('paid_at', $date->toDateString())
                    ->sum('amount'),
            ];
        });
    }
}