<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RevenueService;
use Illuminate\Http\JsonResponse;

class RevenueController extends Controller
{
    public function __construct(private RevenueService $service) {}

    public function index(): JsonResponse
    {
        return response()->json($this->service->getSummary());
    }
}   