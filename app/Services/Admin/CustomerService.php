<?php
// Chứa Service để quản lý Customer, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Lớp này cung cấp các phương thức để quản lý Customer, bao gồm:
class CustomerService
{
    // Lấy danh sách Customer theo các bộ lọc, trả về mảng chứa stats và customers
    public function getList(array $filters): array
    {
        $query = Customer::withCount(['bookings', 'reviews', 'wishlist']) // đếm số lượng bookings, reviews và wishlist của mỗi customer
            ->withSum(['payments as total_spent' => fn($q) => // tính tổng số tiền đã chi tiêu của mỗi customer, chỉ tính những payment có payment_status là success
                $q->where('payment_status', 'success') // chỉ tính những payment có payment_status là success
            ], 'amount') 
            ->orderBy('created_at', 'desc');// sắp xếp theo created_at mới nhất trước

        if (!empty($filters['search'])) { // nếu có bộ lọc search, thêm điều kiện vào query để tìm kiếm theo tên hoặc email của khách hàng
            $query->where(fn($q) =>
                $q->where('name', 'like', '%' . $filters['search'] . '%') // tìm kiếm theo name, nếu name chứa chuỗi search thì sẽ được chọn
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%') // tìm kiếm theo email, nếu email chứa chuỗi search thì sẽ được chọn
            );
        }

        return [
            'stats'     => $this->getStats(), // lấy thống kê tổng số khách hàng, khách hàng mới trong tháng, tổng số booking và tổng doanh thu
            'customers' => $query->paginate($filters['per_page'] ?? 20), // trả về danh sách khách hàng theo các bộ lọc, có phân trang, mỗi trang mặc định 20 khách hàng
        ];
    }

    // Lấy chi tiết một Customer theo id, trả về mảng chứa customer và bookings
    public function getDetail(int $id): array
    {
        $customer = Customer::withCount(['bookings', 'reviews'])// đếm số lượng bookings và reviews của customer
            ->withSum(['payments as total_spent' => fn($q) => // tính tổng số tiền đã chi tiêu của customer, chỉ tính những payment có payment_status là success
                $q->where('payment_status', 'success') // chỉ tính những payment có payment_status là success
            ], 'amount')
            ->findOrFail($id); // tìm Customer theo id, nếu không tìm thấy sẽ ném lỗi

        $bookings = Booking::with(['hotel:id,name', 'payment']) // with() dùng eager loading để giảm số lần query database, chỉ lấy id và name của hotel để tiết kiệm tài nguyên
            ->where('customer_id', $id)
            ->orderBy('created_at', 'desc') //
            ->take(10) // chỉ lấy 10 booking mới nhất của customer
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'hotel'       => $b->hotel?->name,
                'check_in'    => $b->check_in,
                'check_out'   => $b->check_out,
                'status'      => $b->status,
                'total_price' => $b->total_price,
            ]);

        return [
            'customer' => $customer,
            'bookings' => $bookings,
        ];
    }

    private function getStats(): array
    {
        return [
            'total'         => Customer::count(), // đếm tổng số khách hàng trong database
            'new_month'     => Customer::whereMonth('created_at', now()->month) // đếm số khách hàng mới được tạo trong tháng hiện tại, sử dụng whereMonth để lọc theo tháng của created_at, so sánh với tháng hiện tại của hệ thống
                                ->whereYear('created_at', now()->year)->count(), // thêm điều kiện whereYear để đảm bảo chỉ đếm khách hàng mới trong tháng hiện tại của năm hiện tại, tránh đếm nhầm khách hàng tạo vào tháng này nhưng của năm trước
            'total_booking' => Booking::count(), // đếm tổng số booking trong database
            'total_revenue' => Payment::where('payment_status', 'success')->sum('amount'), // tính tổng doanh thu từ các payment có payment_status là success, sử dụng sum để tính tổng giá trị của cột amount
        ];
    }
}