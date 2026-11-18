<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Location;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{
    public function __construct(private RoomAvailabilityService $roomAvailability) {}

    // Danh sách KS — có filter + search
    public function index(Request $request)
    {
        $search=app(\App\Services\HotelSearchService::class);
        return response()->json($search->search($search->validate($request->all())));
    }

    public function suggestions(Request $request)
    {
        $data=$request->validate(['q'=>'nullable|string|max:100']);
        return response()->json(app(\App\Services\HotelSearchService::class)->suggestions(trim($data['q']??'')));
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
        $hotel->append('cover_image_url');
        $hotel->setRelation('rooms', app(\App\Services\HotelRoomTypeService::class)->applyToRooms($hotel->rooms));
        $hotel->rooms->each(fn($room) => $room->append('cover_image_url'));

        // Thêm thông tin review
        $hotel->review_count = DB::table('review')
            ->where('hotel_id', $id)
            ->where('status','approved')
            ->count();

        $hotel->rating_breakdown = DB::table('review')
            ->where('hotel_id', $id)
            ->where('status','approved')
            ->selectRaw('
                AVG(rating) as avg_overall,
                AVG(rating_cleanliness) as avg_cleanliness,
                AVG(rating_service) as avg_service,
                AVG(rating_location) as avg_location
            ')
            ->first();

        return response()->json($hotel);
    }

    // Danh sách phòng giữ dạng JSON array; tồn kho loại phòng được trả riêng trên từng dòng.
    public function rooms(Request $request, $id)
    {
        $hotel = Hotel::where('status', 'active')->findOrFail($id);
        $data = $request->validate([
            'check_in' => 'nullable|required_with:check_out|date_format:Y-m-d',
            'check_out' => 'nullable|required_with:check_in|date_format:Y-m-d|after:check_in',
            'room_type_id' => 'nullable|integer|exists:room_type,id',
            'guests' => 'nullable|integer|min:1',
            'num_guests' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);
        $guests = $data['num_guests'] ?? $data['guests'] ?? null;
        $quantity = $data['quantity'] ?? 1;
        if ($guests !== null && !empty($data['room_type_id'])) {
            $this->roomAvailability->validateCapacity($hotel->id, (int) $data['room_type_id'], (int) $quantity, (int) $guests);
        }
        $checkIn = $data['check_in'] ?? null;
        $checkOut = $data['check_out'] ?? null;

        $query = $hotel->rooms()
            ->with(['roomType', 'images'])
            ->whereIn('status', ['available', 'locked'])->offered();

        if ($request->room_type_id) {
            $query->where('room_type_id', $request->room_type_id);
        }

        if ($guests !== null && !\App\Services\HotelRoomTypeService::enabled()) {
            $query->whereHas('roomType', fn($type) => $type->where('capacity', '>=', (int) ceil($guests / $quantity)));
        }

        $rooms = app(\App\Services\HotelRoomTypeService::class)->applyToRooms($query->get());
        if ($guests !== null) $rooms = $rooms->filter(fn($room) => $room->roomType->capacity >= (int)ceil($guests/$quantity))->values();
        $byType = [];
        $physicalStock = $checkIn ? null : $hotel->rooms()->managed()->get(['id', 'room_type_id', 'status'])->groupBy('room_type_id');
        foreach ($rooms->pluck('room_type_id')->unique() as $roomTypeId) {
            if ($checkIn) {
                $byType[$roomTypeId] = $this->roomAvailability->forTypeWithRooms(
                    $hotel->id, (int) $roomTypeId, $checkIn, $checkOut
                );
            } else {
                $stock = $physicalStock[$roomTypeId];
                $byType[$roomTypeId] = ['availability' => [
                    'available_rooms' => $stock->where('status', 'available')->count(),
                    'total_rooms' => $stock->count(),
                    'booked_rooms' => 0,
                ], 'rooms' => []];
            }
        }

        $rooms->each(function ($room) use ($byType) {
            $room->append('cover_image_url');
            $inspection = $byType[$room->room_type_id];
            $state = $inspection['rooms'][$room->id] ?? [
                'physical_status' => $room->status,
                'occupancy_status' => $room->status,
                'bookable' => $room->status === 'available',
            ];
            $room->physical_status = $state['physical_status'];
            $room->occupancy_status = $state['occupancy_status'];
            $room->bookable = $state['bookable'];
            $room->available_rooms = $inspection['availability']['available_rooms'];
            $room->total_rooms = $inspection['availability']['total_rooms'];
            $room->booked_rooms = $inspection['availability']['booked_rooms'];

            // 'unavailable' is a booking restriction, not an invented physical assignment.
            // Existing clients checking status === 'available' cannot select a sold-out type.
            $room->status = $state['bookable'] ? 'available'
                : ($state['occupancy_status'] === 'available' ? 'unavailable' : $state['occupancy_status']);
        });

        return response()->json($rooms);
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
