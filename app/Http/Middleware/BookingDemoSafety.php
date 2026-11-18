<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class BookingDemoSafety
{
    public function handle(Request $request, Closure $next)
    {
        \App\Support\BookingDemo::connection();
        // No gateway initiation, callback, webhook or assertion of actual bank funds in this runtime.
        if (preg_match('~^api/(payments/(?:create|retry|manual|vnpay[^/]*)(?:/|$)|webhooks/)|verify-banking~', $request->path())
            || str_contains($request->path(),'sepay') || $request->path()==='payment/result') {
            return response()->json(['message'=>'DEMO: chỉ dùng các nút giả lập, không có giao dịch ngân hàng.'],403);
        }
        if ($request->isMethod('GET') && $request->is('api/hotels/*/room-types','api/demo/*','api/admin/demo/*','api/bookings*')) {
            app(\App\Services\DemoBookingPaymentService::class)->expireDue();
        }
        $response=$next($request);
        if (str_contains($response->headers->get('Content-Type',''),'text/html')) {
            $banner='<div role="status" style="position:fixed;bottom:10px;left:50%;transform:translateX(-50%);z-index:10000;max-width:calc(100vw - 24px);padding:7px 14px;border:1px solid #FDE68A;border-radius:8px;background:#FFFBEB;color:#92400E;font:600 12px system-ui;text-align:center;pointer-events:none">AUREL DEMO · MySQL cô lập · Không chuyển tiền thật</div>';
            $response->setContent(str_replace('</body>',$banner.'</body>',$response->getContent()));
        }
        $response->headers->set('X-AUREL-Mode','DEMO');
        $response->headers->set('Cache-Control','no-store');
        return $response;
    }
}
