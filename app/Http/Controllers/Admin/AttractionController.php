<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AttractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttractionController extends Controller
{
    public function __construct(private AttractionService $service) {}

    public function index(int $locId): JsonResponse
    {
        return response()->json($this->service->getByLocation($locId));
    }

    public function store(Request $request, int $locId): JsonResponse
    {
        $data = $request->validate([
            'item_type'      => 'required|in:hotel,restaurant,attraction,activity,transport,shopping',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'address'        => 'nullable|string|max:300',
            'price_min'      => 'nullable|integer|min:0',
            'price_max'      => 'nullable|integer|min:0',
            'duration_hours' => 'nullable|integer|min:0|max:24',
            'open_time'      => 'nullable|string',
            'close_time'     => 'nullable|string',
        ]);

        return response()->json($this->service->create($locId, $data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->only([
            'name', 'description', 'address',
            'price_min', 'price_max', 'duration_hours',
            'open_time', 'close_time', 'item_type',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Đã xóa!']);
    }

    public function toggle(int $id): JsonResponse
    {
        $attraction = $this->service->toggle($id);
        return response()->json(['is_active' => $attraction->is_active]);
    }
}