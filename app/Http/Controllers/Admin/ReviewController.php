<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getList($request->only(['max_rating', 'per_page']))
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Đã xóa review']);
    }
}