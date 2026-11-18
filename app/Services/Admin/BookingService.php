<?php
// Chứa Service để quản lý Booking, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Lớp này cung cấp các phương thức để quản lý Booking, bao gồm:
class BookingService
{
    // Lấy danh sách Booking theo các bộ lọc, trả về LengthAwarePaginator
    public function getList(array $filters): LengthAwarePaginator 
    {
        $query = Booking::with(['customer:id,name,email,phone', 'hotel:id,name', 'payment'])// with() dùng eager loading để giảm số lần query database
            ->orderBy('created_at', 'desc'); // sắp xếp theo created_at mới nhất trước

        if (!empty($filters['status'])) { // nếu có bộ lọc status, thêm điều kiện vào query
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['hotel_id'])) { 
            $query->where('hotel_id', $filters['hotel_id']);
        }
        if (!empty($filters['date_from'])) { 
            $query->where('check_in', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('check_out', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) { // nếu có bộ lọc search, thêm điều kiện vào query để tìm kiếm theo tên hoặc email của khách hàng
            $query->whereHas('customer', fn($q) =>
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
            );
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // Cập nhật trạng thái của một Booking, trả về mảng chứa message và booking đã cập nhật
    public function updateStatus(int $id, string $status): array
    {
        [$booking,$oldStatus]=\Illuminate\Support\Facades\DB::transaction(function()use($id,$status){
            $candidate=Booking::findOrFail($id);
            \App\Models\Hotel::whereKey($candidate->hotel_id)->lockForUpdate()->firstOrFail();
            $booking=Booking::whereKey($id)->lockForUpdate()->firstOrFail();
            \App\Support\BookingLifecyclePolicy::transition(\Illuminate\Support\Facades\DB::connection(),$booking,$status);
            $oldStatus=$booking->status;
            $booking->update(['status'=>$status]);
            return [$booking,$oldStatus];
        },3);

        return [
            'message' => "Đã cập nhật booking #{$id}: {$oldStatus} → {$status}",
            'booking' => $booking,
        ];
    }
}
