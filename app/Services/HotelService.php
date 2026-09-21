<?php
// Tạo file app/Services/Hotel/HotelService.php với nội dung sau:
namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

// Dịch vụ xử lý logic liên quan đến khách sạn
class HotelService
{
    public function getList(array $filters): LengthAwarePaginator
    {
        $query = Hotel::with(['location', 'amenities', 'images' => fn($q) => $q->where('is_primary', true)])
            ->where('status', 'active')
            ->withMin(['rooms' => fn($q) => $q->where('status', 'available')], 'price');

        if (!empty($filters['city'])) {
            $query->whereHas('location', fn($q) =>
                $q->where('name', 'like', '%' . $filters['city'] . '%')
            );
        }
        if (!empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }
        if (!empty($filters['star'])) {
            $query->where('star_rating', $filters['star']);
        }
        if (!empty($filters['max_price'])) {
            $query->whereHas('rooms', fn($q) =>
                $q->where('price', '<=', $filters['max_price'])->where('status', 'available')
            );
        }
        if (!empty($filters['amenity'])) {
            $query->whereHas('amenities', fn($q) =>
                $q->where('amenity', $filters['amenity'])
            );
        }
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        $sort = in_array($filters['sort'] ?? '', ['avg_rating', 'star_rating', 'name'])
            ? $filters['sort'] : 'avg_rating';
        $dir = $filters['dir'] ?? 'desc';

        return $query->orderBy($sort, $dir)->paginate($filters['per_page'] ?? 10);
    }

    public function getDetail(int $id): Hotel
    {
        $hotel = Hotel::with(['location', 'amenities', 'images', 'rooms.roomType', 'rooms.images'])
            ->where('status', 'active')
            ->findOrFail($id);

        $hotel->review_count = DB::table('review')->where('hotel_id', $id)->count();

        $hotel->rating_breakdown = DB::table('review')
            ->where('hotel_id', $id)
            ->selectRaw('AVG(rating) as avg_overall, AVG(rating_cleanliness) as avg_cleanliness, AVG(rating_service) as avg_service, AVG(rating_location) as avg_location')
            ->first();

        return $hotel;
    }

    public function getRooms(int $hotelId, array $filters): \Illuminate\Support\Collection
    {
        $hotel    = Hotel::findOrFail($hotelId);
        $checkIn  = $filters['check_in']  ?? null;
        $checkOut = $filters['check_out'] ?? null;

        $query = $hotel->rooms()
            ->with(['roomType', 'images'])
            ->whereIn('status', ['available', 'locked']);

        if (!empty($filters['room_type_id'])) {
            $query->where('room_type_id', $filters['room_type_id']);
        }
        if (!empty($filters['guests'])) {
            $query->where('capacity', '>=', $filters['guests']);
        }

        return $query->get()->map(function ($room) use ($checkIn, $checkOut) {
            $status = 'available';

            if ($checkIn && $checkOut) {
                $overlap = DB::table('booking_room as br')
                    ->join('booking as b', 'b.id', '=', 'br.booking_id')
                    ->where('br.room_id', $room->id)
                    ->whereIn('b.status', ['pending', 'confirmed'])
                    ->where('b.check_in', '<', $checkOut)
                    ->where('b.check_out', '>', $checkIn)
                    ->first();

                if ($overlap) {
                    $status = $overlap->status === 'pending' ? 'locked' : 'booked';
                }
            }

            $room->status = $status;
            return $room;
        });
    }

    public function getLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::withCount('hotels')->orderBy('name')->get();
    }
}