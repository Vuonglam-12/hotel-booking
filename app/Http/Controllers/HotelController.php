<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{
    // Danh sách KS — có filter + search
    public function index(Request $request)
    {
        $query = Hotel::with(['location', 'amenities', 'images' => function ($q) {
            $q->where('is_primary', true);
        }])
        ->where('status', 'active')
        ->withMin(['rooms' => function ($q) {
            $q->where('status', 'available');
        }], 'price');

        // Filter theo city
        if ($request->city) {
            $query->whereHas('location', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->city . '%');
            });
        }

        // Filter theo location_id
        if ($request->location_id) {
            $query->where('location_id', $request->location_id);
        }

        // Filter theo số sao
        if ($request->star) {
            $query->where('star_rating', $request->star);
        }

        // Filter theo giá tối đa
        if ($request->max_price) {
            $query->whereHas('rooms', function ($q) use ($request) {
                $q->where('price', '<=', $request->max_price)
                  ->where('status', 'available');
            });
        }

        // Filter theo tiện ích
        if ($request->amenity) {
            $query->whereHas('amenities', function ($q) use ($request) {
                $q->where('amenity', $request->amenity);
            });
        }

        // Tìm kiếm theo tên
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sort = $request->sort ?? 'avg_rating';
        $dir  = $request->dir  ?? 'desc';
        if (in_array($sort, ['avg_rating', 'star_rating', 'name'])) {
            $query->orderBy($sort, $dir);
        }

        $hotels = $query->paginate($request->per_page ?? 10);

        return response()->json($hotels);
    }

    // Chi tiết 1 KS
    public function show($id)
    {
        $hotel = Hotel::with([
            'location',
            'amenities',
            'images',
            'rooms.roomType',
            'rooms.images',
        ])->where('status', 'active')->findOrFail($id);

        // Thêm thông tin review
        $hotel->review_count = DB::table('review')
            ->where('hotel_id', $id)
            ->count();

        $hotel->rating_breakdown = DB::table('review')
            ->where('hotel_id', $id)
            ->selectRaw('
                AVG(rating) as avg_overall,
                AVG(rating_cleanliness) as avg_cleanliness,2
                AVG(rating_service) as avg_service,
                AVG(rating_location) as avg_location
            ')
            ->first();

        return response()->json($hotel);
    }

    // Danh sách phòng của KS — check available theo ngày
    public function rooms(Request $request, $id)
    {
        $hotel = Hotel::findOrFail($id);

        $query = $hotel->rooms()
            ->with(['roomType', 'images'])
            ->where('status', 'available');

        // Filter theo số khách
        if ($request->guests) {
            $query->where('capacity', '>=', $request->guests);
        }

        // Check phòng trống theo ngày
        if ($request->check_in && $request->check_out) {
            $query->whereNotIn('id', function ($q) use ($request) {
                $q->select('br.room_id')
                  ->from('booking_room as br')
                  ->join('booking as b', 'b.id', '=', 'br.booking_id')
                  ->whereIn('b.status', ['pending', 'confirmed'])
                  ->where(function ($q2) use ($request) {
                      $q2->whereBetween('b.check_in',  [$request->check_in, $request->check_out])
                         ->orWhereBetween('b.check_out', [$request->check_in, $request->check_out])
                         ->orWhere(function ($q3) use ($request) {
                             $q3->where('b.check_in',  '<=', $request->check_in)
                                ->where('b.check_out', '>=', $request->check_out);
                         });
                  });
            });
        }

        return response()->json($query->get());
    }

    // API cho Google Maps — trả về tất cả KS có tọa độ
    public function mapData(Request $request)
    {
        $query = DB::table('v_map_hotels');

        // Filter theo city nếu có
        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Tìm KS gần vị trí user
        if ($request->lat && $request->lng) {
            $lat    = $request->lat;
            $lng    = $request->lng;
            $radius = $request->radius ?? 10; // km

            $query->selectRaw("
                *,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ", [$lat, $lng, $lat])
            ->havingRaw('distance_km < ?', [$radius])
            ->orderBy('distance_km');
        }

        // Log tìm kiếm trên map
        if ($request->lat && $request->lng) {
            DB::table('map_search_log')->insert([
                'customer_id'   => auth('sanctum')->id(),
                'search_lat'    => $request->lat,
                'search_lng'    => $request->lng,
                'radius_km'     => $request->radius ?? 10,
                'result_count'  => $query->count(),
                'created_at'    => now(),
            ]);
        }

        return response()->json($query->get());
    }

    // Danh sách tỉnh/thành để filter
    public function locations()
    {
        $locations = Location::withCount('hotels')
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }
}
