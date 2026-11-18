<?php
namespace App\Http\Middleware;

use App\Support\LocalBookingDemo;
use Closure;
use Illuminate\Http\Request;

final class LocalBookingDemoOnly
{
    public function handle(Request $r, Closure $next)
    {
        LocalBookingDemo::assertRequest($r);
        LocalBookingDemo::connection();
        return $next($r)->header('Cache-Control','no-store')->header('X-AUREL-Mode','LOCAL-DEMO');
    }
}
