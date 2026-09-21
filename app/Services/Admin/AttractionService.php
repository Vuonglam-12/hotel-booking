<?php
// Chứa Service để quản lý Attraction, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Attraction;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

// Lớp này cung cấp các phương thức để quản lý Attraction, bao gồm:
class AttractionService
{
    // Lấy danh sách Attraction theo location_id, sắp xếp theo item_type và name
    public function getByLocation(int $locId): Collection // trả về Collection các Attraction theo location_id, sắp xếp theo item_type và name
    {
        return Attraction::where('location_id', $locId)
            ->orderBy('item_type') // ưu tiên sắp xếp theo item_type trước, sau đó mới đến name
            ->orderBy('name') // sắp xếp theo name
            ->get(); // trả về Collection các Attraction
    }

    // Tạo mới một Attraction, đảm bảo location_id tồn tại
    public function create(int $locId, array $data): Attraction // trả về Attraction vừa tạo
    {
        Location::findOrFail($locId); // kiểm tra location_id tồn tại, nếu không sẽ ném lỗi

        return Attraction::create(array_merge($data, ['location_id' => $locId]));
    }

    // Cập nhật một Attraction theo id, trả về Attraction đã cập nhật
    public function update(int $id, array $data): Attraction // trả về Attraction đã cập nhật
    {
        $attraction = Attraction::findOrFail($id); // tìm Attraction theo id, nếu không tìm thấy sẽ ném lỗi
        $attraction->update($data); // cập nhật Attraction với dữ liệu mới
        return $attraction; // trả về Attraction đã cập nhật
    }

    // Xóa một Attraction theo id, nếu không tìm thấy sẽ ném lỗi
    public function delete(int $id): void // không trả về gì, nếu không tìm thấy sẽ ném lỗi
    {
        Attraction::findOrFail($id)->delete(); // tìm Attraction theo id, nếu không tìm thấy sẽ ném lỗi, nếu tìm thấy sẽ xóa
    }

    // Chuyển trạng thái is_active của một Attraction, trả về Attraction đã cập nhật
    public function toggle(int $id): Attraction // trả về Attraction đã cập nhật sau khi chuyển trạng thái is_active
    {
        $attraction = Attraction::findOrFail($id); // tìm Attraction theo id, nếu không tìm thấy sẽ ném lỗi
        $attraction->update(['is_active' => !$attraction->is_active]); // chuyển trạng thái is_active, nếu đang là true sẽ thành false, ngược lại sẽ thành true
        return $attraction;
    }
}