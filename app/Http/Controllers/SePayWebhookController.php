<?php

namespace App\Http\Controllers;

use App\Services\SePayWebhookServiceV2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class SePayWebhookController extends Controller
{
    public function __invoke(Request $request, SePayWebhookServiceV2 $service)
    {
        if (!$service->enabled()) {
            return response()->json(['success' => false, 'message' => 'SePay webhook is disabled.'], 503);
        }
        try {
            $service->assertOperational();
        } catch (\LogicException $e) {
            Log::error('SePay webhook configuration or Booking V2 guard refused delivery.', ['reason' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'SePay webhook is not operationally enabled.'], 503);
        }
        if (!$service->authenticate($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized SePay webhook.'], 401);
        }
        try {
            $service->receive($request->getContent());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['success' => true]);
    }
}
