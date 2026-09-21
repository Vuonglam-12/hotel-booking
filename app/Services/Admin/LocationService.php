<?php

// Chứa Service để quản lý Location, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Location;

// Lớp này cung cấp các phương thức để quản lý Location, bao gồm:
class LocationService
{
    // Lấy danh sách Location kèm số lượng Attraction của mỗi Location, sắp xếp theo name
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::withCount('attractions')
            ->orderBy('name')
            ->get();
    }

    // Lấy chi tiết một Location theo id, trả về Location kèm danh sách Attraction
    public function create(array $data): Location
    {
        return Location::create([
            'name'    => $data['name'],
            'region'  => $data['region'],
            'country' => $data['country'] ?? 'Việt Nam',
        ]);
    }

    // Cập nhật một Location theo id, trả về Location đã cập nhật
    public function update(int $id, array $data): Location
    {
        $location = Location::findOrFail($id);
        $location->update(array_filter([
            'name'    => $data['name']    ?? null,
            'region'  => $data['region']  ?? null,
            'country' => $data['country'] ?? null,
        ], fn($v) => !is_null($v)));

        return $location;
    }

    // Xóa một Location theo id, nếu không tìm thấy sẽ ném lỗi, nếu đang có khách sạn liên kết sẽ ném lỗi
    public function delete(int $id): void
    {
        $location = Location::findOrFail($id);

        if ($location->hotels()->exists()) {
            throw new \Exception('Không thể xóa — location đang có khách sạn liên kết');
        }

        $location->delete();
    }
}