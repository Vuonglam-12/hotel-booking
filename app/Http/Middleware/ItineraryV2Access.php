<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Support\CustomerSessions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class ItineraryV2Access
{
    public function handle(Request $request, Closure $next)
    {
        $customer = $request->user();
        abort_unless($customer instanceof Customer && ! CustomerSessions::isAdminToken($customer, $customer->currentAccessToken()), 403);
        abort_unless(Schema::hasColumn('itinerary', 'schema_version'), 503, 'API chuyến đi mới chưa được áp dụng cấu trúc dữ liệu.');
        foreach (['itinerary_destination', 'itinerary_region_mapping', 'itinerary_calendar_day', 'itinerary_place_snapshot',
            'itinerary_lodging_room', 'itinerary_cost_line', 'itinerary_write_request'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'API chuyến đi mới chưa được áp dụng cấu trúc dữ liệu.');
        }

        return $next($request);
    }
}
