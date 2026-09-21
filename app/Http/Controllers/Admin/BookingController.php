<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getList($request->only([
                'status', 'hotel_id', 'date_from', 'date_to', 'search', 'per_page',
            ]))
        );
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        return response()->json(
            $this->service->updateStatus($id, $request->status)
        );
    }
}