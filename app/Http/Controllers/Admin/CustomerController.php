<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getList($request->only(['search', 'per_page']))
        );
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            $this->service->getDetail($id)
        );
    }
}