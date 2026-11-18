<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Installed only by the approved V2 overlay; keeps authentication and routes unchanged. */
final class RoomTypeV2ResponsePolicy
{
    public function handle(Request $request,Closure $next)
    {
        $response=$next($request);
        $private=$request->is('admin','admin/*','profile','password','bookings','payments/*','payment/*','reset-password/*')
            || ($request->is('api/*') && !$request->is('api/hotels','api/hotels/*','api/locations'));
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','SAMEORIGIN');
        $response->headers->set('Referrer-Policy',$private?'no-referrer':'strict-origin-when-cross-origin');
        if ($private) { $response->headers->set('Cache-Control','no-store, private'); $response->headers->set('X-Robots-Tag','noindex, noarchive'); }
        return $response;
    }
}
