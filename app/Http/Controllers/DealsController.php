<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class DealsController extends Controller
{
    public function index()
    {
        // Flash sale — KS giá rẻ nhất, sắp xếp theo giá tăng dần
        $flashSale = Hotel::with(['location'])
            ->where('status', 'active')
            ->withMin(['rooms' => fn($q) => $q->where('status', 'available')], 'price')
            ->having('rooms_min_price', '>', 0)
            ->orderBy('rooms_min_price', 'asc')
            ->limit(8)
            ->get()
            ->map(function ($hotel) {
                // Badge giảm giá ngẫu nhiên: 20%, 25%, 30%, 35%, 40%
                $discounts = [20, 25, 30, 35, 40];
                $hotel->discount = $discounts[array_rand($discounts)];
                // Giá gốc = giá hiện tại / (1 - discount%)
                $hotel->original_price = $hotel->rooms_min_price
                    ? round($hotel->rooms_min_price / (1 - $hotel->discount / 100) / 1000) * 1000
                    : 0;
                return $hotel;
            });

        // Ưu đãi nổi bật — KS rating cao + giá tốt
        $featured = Hotel::with(['location'])
            ->where('status', 'active')
            ->where('avg_rating', '>=', 4.0)
            ->withMin(['rooms' => fn($q) => $q->where('status', 'available')], 'price')
            ->having('rooms_min_price', '>', 0)
            ->orderBy('avg_rating', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($hotel) {
                $discounts = [15, 20, 25];
                $hotel->discount = $discounts[array_rand($discounts)];
                $hotel->original_price = $hotel->rooms_min_price
                    ? round($hotel->rooms_min_price / (1 - $hotel->discount / 100) / 1000) * 1000
                    : 0;
                return $hotel;
            });

        // Theo điểm đến — lấy các location có KS
        $destinations = Location::withCount(['hotels' => fn($q) => $q->where('hotel.status', 'active')])
            ->having('hotels_count', '>', 0)
            ->withMin(['rooms' => fn($q) => $q->where('room.status', 'available')], 'price')
            ->orderBy('hotels_count', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($loc) {
                $discounts = [15, 20, 25, 30, 35, 40];
                $loc->discount = $discounts[array_rand($discounts)];
                return $loc;
            });

        // Combo tiết kiệm — KS 5 sao giá tốt
        $combos = Hotel::with(['location'])
            ->where('status', 'active')
            ->where('star_rating', '>=', 4)
            ->withMin(['rooms' => fn($q) => $q->where('status', 'available')], 'price')
            ->having('rooms_min_price', '>', 0)
            ->orderBy('star_rating', 'desc')
            ->limit(3)
            ->get();

        // Locations cho filter
        $locations = Location::withCount(['hotels' => fn($q) => $q->where('hotel.status', 'active')])
            ->having('hotels_count', '>', 0)
            ->orderBy('name')
            ->get();

        return view('deals', compact(
            'flashSale', 'featured', 'destinations', 'combos', 'locations'
        ));
    }
}