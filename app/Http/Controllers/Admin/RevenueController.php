<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RevenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function __construct(private RevenueService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->getSummary($request->only([
            'period', 'date_from', 'date_to', 'payment_scope', 'page',
        ])))->header('Cache-Control', 'no-store');
    }
}
