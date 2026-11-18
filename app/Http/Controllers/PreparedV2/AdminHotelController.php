<?php

namespace App\Http\Controllers\PreparedV2;

use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class AdminHotelController extends \App\Http\Controllers\AdminController
{
    public function hotels(Request $request)
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        if (config('roomtype-v2.final_integration',false)) {
            $q=app(\App\Services\HotelCatalogServiceV2::class)->query($request->all(),false);
            $stats=(clone $q)->select([])->selectRaw("COUNT(*) AS total, COALESCE(SUM(status='active'),0) AS active, COALESCE(SUM(status='inactive'),0) AS inactive, COALESCE(SUM(status='closed'),0) AS closed, AVG(avg_rating) AS average_rating")->first();
            $p=$q->orderByDesc('id')->paginate(min(100,max(1,(int)$request->input('per_page',15))));
            $p->getCollection()->each(fn($h)=>$h->append('cover_image_url'));
            return response()->json(array_merge($p->toArray(),['stats'=>['total'=>(int)$stats->total,'active'=>(int)$stats->active,'inactive'=>(int)$stats->inactive,
                'closed'=>(int)$stats->closed,'other'=>(int)$stats->total-(int)$stats->active-(int)$stats->inactive-(int)$stats->closed,'average_rating'=>$stats->average_rating===null?null:round((float)$stats->average_rating,1)]]));
        }
        $response=parent::hotels($request); $payload=$response->getData(true);
        $totals=$db->table('room_type')->whereIn('hotel_id',array_column($payload['data'],'id'))->groupBy('hotel_id')
            ->selectRaw('hotel_id, SUM(total_rooms) AS inventory_total')->pluck('inventory_total','hotel_id');
        foreach ($payload['data'] as &$hotel) $hotel['rooms_count']=(int)($totals[$hotel['id']]??0);
        unset($hotel); return response()->json($payload);
    }
    public function page()
    {
        RoomTypeV2Inventory::open('mysql'); return view('prepared-v2.admin-hotels');
    }
}
