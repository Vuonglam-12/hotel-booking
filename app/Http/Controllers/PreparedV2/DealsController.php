<?php

namespace App\Http\Controllers\PreparedV2;

class DealsController extends \App\Http\Controllers\Controller
{
    public function index()
    {
        $catalog=app(\App\Services\HotelCatalogServiceV2::class);
        $flashSale=$catalog->query()->where('status','active')->having('rooms_min_price','>',0)->orderBy('rooms_min_price')->limit(8)->get();
        $featured=$catalog->query()->where('status','active')->where('avg_rating','>=',4)->having('rooms_min_price','>',0)->orderByDesc('avg_rating')->limit(4)->get();
        $combos=$catalog->query()->where('status','active')->where('star_rating','>=',4)->having('rooms_min_price','>',0)->orderByDesc('star_rating')->limit(3)->get();
        $locations=$catalog->getLocations()->filter(fn($l)=>$l->hotels_count>0)->sortBy('name')->values();
        $destinations=$locations->sortByDesc('hotels_count')->take(6);
        // No verified promotion source: no random discounts, reference prices or bundled services.
        return view('prepared-v2.deals',compact('flashSale','featured','combos','locations','destinations'));
    }
}
