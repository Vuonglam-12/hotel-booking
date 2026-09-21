<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\HotelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function __construct(private HotelService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getList($request->only(['search', 'status', 'star', 'per_page']))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'location_id' => 'required|exists:location,id',
            'star_rating' => 'required|integer|between:1,5',
            'address'     => 'required|string|max:500',
            'status'      => 'in:active,inactive,closed',
            'email'       => 'nullable|email',
            'amenities'   => 'nullable|array',
        ]);

        return response()->json([
            'message' => 'Đã thêm khách sạn mới',
            'hotel'   => $this->service->create($data),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'location_id' => 'sometimes|exists:location,id',
            'star_rating' => 'sometimes|integer|between:1,5',
            'address'     => 'sometimes|string|max:500',
            'status'      => 'sometimes|in:active,inactive,closed',
            'email'       => 'nullable|email',
            'amenities'   => 'nullable|array',
        ]);

        return response()->json([
            'message' => 'Đã cập nhật khách sạn',
            'hotel'   => $this->service->update($id, $data),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|in:active,inactive,closed']);

        $labelMap = [
            'active'   => 'Đã bật khách sạn',
            'inactive' => 'Đã tạm ngưng khách sạn',
            'closed'   => 'Đã đóng cửa khách sạn',
        ];

        $hotel = $this->service->updateStatus($id, $request->status);

        return response()->json([
            'message' => $labelMap[$request->status] ?? 'Đã cập nhật trạng thái',
            'hotel'   => $hotel->only(['id', 'name', 'status']),
        ]);
    }

    public function getRoomTypes(int $hotelId): JsonResponse
    {
        return response()->json($this->service->getRoomTypes($hotelId));
    }

    public function createRoomType(Request $request, int $hotelId): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'base_price'  => 'required|numeric|min:0',
            'capacity'    => 'required|integer|min:1|max:10',
            'bed_type'    => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'room_count'  => 'required|integer|min:1|max:100',
            'floor_start' => 'nullable|integer|min:1|max:99',
        ]);

        $result = $this->service->createRoomType($hotelId, $data);

        return response()->json([
            'message'       => "Đã tạo loại phòng và {$result['rooms_created']} phòng thành công",
            'room_type'     => $result['room_type'],
            'rooms_created' => $result['rooms_created'],
        ], 201);
    }
}