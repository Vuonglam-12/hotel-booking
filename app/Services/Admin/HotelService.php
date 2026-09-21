<?php
// Chứa Service để quản lý Hotel, được AdminController gọi đến
namespace App\Services\Admin;

use App\Models\Hotel;
use App\Models\HotelAmenity;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Lớp này cung cấp các phương thức để quản lý Hotel, bao gồm:
class HotelService
{
    // Lấy danh sách Hotel theo các bộ lọc, trả về LengthAwarePaginator
    public function getList(array $filters): LengthAwarePaginator
    {
        $query = Hotel::with(['location', 'amenities'])
            ->withCount('rooms')
            ->orderBy('id', 'desc');

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['star'])) {
            $query->where('star_rating', $filters['star']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // Lấy chi tiết một Hotel theo id, trả về Hotel kèm location và amenities
    public function create(array $data): Hotel
    {
        $hotel = Hotel::create([
            'name'           => $data['name'],
            'location_id'    => $data['location_id'],
            'star_rating'    => $data['star_rating'],
            'address'        => $data['address'],
            'description'    => $data['description']    ?? null,
            'phone'          => $data['phone']           ?? null,
            'email'          => $data['email']           ?? null,
            'check_in_time'  => $data['check_in_time']  ?? '14:00:00',
            'check_out_time' => $data['check_out_time'] ?? '12:00:00',
            'status'         => $data['status']         ?? 'active',
            'avg_rating'     => 0,
        ]);

        if (!empty($data['amenities'])) {
            foreach ($data['amenities'] as $amenity) {
                HotelAmenity::create(['hotel_id' => $hotel->id, 'amenity' => $amenity]);
            }
        }

        return $hotel->load('location', 'amenities');
    }

    // Cập nhật một Hotel theo id, trả về Hotel đã cập nhật
    public function update(int $id, array $data): Hotel
    {
        $hotel = Hotel::findOrFail($id);

        $hotel->update(array_filter([
            'name'           => $data['name']           ?? null,
            'location_id'    => $data['location_id']    ?? null,
            'star_rating'    => $data['star_rating']    ?? null,
            'address'        => $data['address']        ?? null,
            'description'    => $data['description']    ?? null,
            'phone'          => $data['phone']          ?? null,
            'email'          => $data['email']          ?? null,
            'check_in_time'  => $data['check_in_time']  ?? null,
            'check_out_time' => $data['check_out_time'] ?? null,
            'status'         => $data['status']         ?? null,
        ], fn($v) => !is_null($v)));

        if (isset($data['amenities'])) {
            HotelAmenity::where('hotel_id', $id)->delete();
            foreach ((array) $data['amenities'] as $amenity) {
                HotelAmenity::create(['hotel_id' => $hotel->id, 'amenity' => $amenity]);
            }
        }

        return $hotel->load('location', 'amenities');
    }

    // Xóa một Hotel theo id, nếu không tìm thấy sẽ ném lỗi
    public function updateStatus(int $id, string $status): Hotel
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->update(['status' => $status]);
        return $hotel;
    }

    // Lấy danh sách RoomType của một Hotel theo hotel_id, trả về Collection chứa RoomType và số lượng phòng của mỗi loại
    public function getRoomTypes(int $hotelId): \Illuminate\Database\Eloquent\Collection
    {
        return RoomType::whereHas('rooms', fn($q) => $q->where('hotel_id', $hotelId))
            ->withCount(['rooms' => fn($q) => $q->where('hotel_id', $hotelId)])
            ->get();
    }

    // Tạo mới một RoomType cùng với số lượng Room tương ứng, trả về mảng chứa RoomType vừa tạo và số lượng phòng đã tạo
    public function createRoomType(int $hotelId, array $data): array
    {
        $hotel    = Hotel::findOrFail($hotelId);
        $roomType = RoomType::create([
            'name'        => $data['name'],
            'base_price'  => $data['base_price'],
            'capacity'    => $data['capacity'],
            'bed_type'    => $data['bed_type']    ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $floorStart = $data['floor_start'] ?? 1;
        $count      = $data['room_count'];

        for ($i = 1; $i <= $count; $i++) {
            $floor = $floorStart + intdiv($i - 1, 10);
            Room::create([
                'hotel_id'     => $hotel->id,
                'room_type_id' => $roomType->id,
                'room_number'  => $floor . str_pad($i, 2, '0', STR_PAD_LEFT),
                'floor'        => $floor,
                'price'        => $data['base_price'],
                'capacity'     => $data['capacity'],
                'status'       => 'available',
            ]);
        }

        return ['room_type' => $roomType, 'rooms_created' => $count];
    }
}