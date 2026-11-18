<?php

namespace App\Http\Controllers;

use App\Services\LocalBookingReconciliationService;
use Illuminate\Http\Request;

final class LocalBookingReconciliationController extends Controller
{
    public function index(Request $request, LocalBookingReconciliationService $service)
    {
        $data = $request->validate(['page'=>'sometimes|integer|min:1','state'=>'nullable|in:open,reviewed,refund_verified']);
        return response()->json($service->listing((int)($data['page']??1), $data['state']??null));
    }

    public function review(Request $request, int $id, LocalBookingReconciliationService $service)
    {
        return response()->json($service->review($id, (int)$request->user('admin')->id,
            $request->only(['state','note','evidence_reference']), $request->file('evidence')));
    }
}
