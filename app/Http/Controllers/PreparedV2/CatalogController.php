<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Services\HotelCatalogServiceV2;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $r,HotelCatalogServiceV2 $catalog) { return response()->json($catalog->getList($r->all())); }
    public function show(int $id,HotelCatalogServiceV2 $catalog) { return response()->json($catalog->getDetail($id)); }
    public function locations(HotelCatalogServiceV2 $catalog) { return response()->json($catalog->getLocations()); }
    public function home(Request $r,HotelCatalogServiceV2 $catalog)
    {
        $locations=$catalog->getLocations(); $hotelSections=collect(); $hotels=collect();
        if ($r->hasAny(['city','star','search'])) $hotels=$catalog->getList($r->all()+['per_page'=>9]);
        else $hotelSections=$locations->map(function ($l) use ($catalog) {
            $rows=$catalog->query(['location_id'=>$l->id])->orderByDesc('avg_rating')->take(6)->get();
            return ['location'=>$l,'hotels'=>$rows,'hasMore'=>$rows->count()>5];
        })->filter(fn($s)=>$s['hotels']->isNotEmpty())->values();
        return view('Home',compact('hotels','locations','hotelSections'));
    }
    public function map(Request $r,HotelCatalogServiceV2 $catalog)
    {
        $data=$r->validate(['lat'=>'required_with:lng|numeric|between:-90,90','lng'=>'required_with:lat|numeric|between:-180,180','radius'=>'sometimes|numeric|gt:0|max:20000','city'=>'sometimes|string|max:100']);
        $q=$catalog->query($data)->whereNotNull('latitude')->whereNotNull('longitude');
        if (isset($data['lat'],$data['lng'])) $q->selectRaw('6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?))*COS(RADIANS(latitude))*COS(RADIANS(longitude)-RADIANS(?))+SIN(RADIANS(?))*SIN(RADIANS(latitude))))) AS distance_km',[$data['lat'],$data['lng'],$data['lat']])
            ->having('distance_km','<',$data['radius']??10)->orderBy('distance_km');
        $rows=$q->get(); foreach ($rows as $h) { $h->city=$h->location?->name; $h->min_price=$h->rooms_min_price; $h->append('cover_image_url'); }
        return response()->json($rows);
    }
}
