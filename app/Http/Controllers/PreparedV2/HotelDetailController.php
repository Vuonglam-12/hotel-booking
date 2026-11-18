<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomTypeV2;
use App\Support\AccommodationImage;
use App\Support\RoomTypeV2Inventory;

class HotelDetailController extends Controller
{
    public function show(int $id)
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        $hotel=config('roomtype-v2.final_integration',false)?app(\App\Services\HotelCatalogServiceV2::class)->getDetail($id):Hotel::with(['location','amenities','images'])->where('status','active')->findOrFail($id);
        $reviews=$hotel->reviews()->with(['customer','images'])->orderByDesc('created_at')->take(6)->get();
        $hotel->review_count=$db->table('review')->where('hotel_id',$id)->count();
        $detailRoomTypes=RoomTypeV2::with('images')->where('hotel_id',$id)->where('status','active')->orderBy('id')->get()->map(function ($type) {
            $images=$type->gallery_image_urls;
            return ['id'=>$type->id,'hotel_id'=>$type->hotel_id,'name'=>$type->name,'description'=>$type->description,
                'capacity'=>$type->capacity,'bed_type'=>$type->bed_type,'area'=>null,'amenities'=>[],'policies'=>[],
                'price'=>$type->base_price,'total_rooms'=>$type->total_rooms,'images'=>$images,
                'cover'=>$images[0]??AccommodationImage::placeholder()];
        })->all();
        $similarQuery=config('roomtype-v2.final_integration',false)?app(\App\Services\HotelCatalogServiceV2::class)->query():Hotel::with(['location','images'])->where('status','active');
        $similarHotels=$similarQuery->whereKeyNot($id)
            ->where('location_id',$hotel->location_id)->orderByDesc('avg_rating')->take(6)->get();
        foreach ($similarHotels as $similar) {
            $similar->prepared_min_price=$db->table('room_type')->where('hotel_id',$similar->id)->where('status','active')->min('base_price');
        }
        return view('prepared-v2.hotel-detail',compact('hotel','reviews','similarHotels','detailRoomTypes'));
    }
}
