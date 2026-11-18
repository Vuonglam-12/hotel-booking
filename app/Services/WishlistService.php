<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function getList(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        $items=Wishlist::with(['hotel'=>fn($q)=>$q->with(['images'=>fn($q)=>$q->orderByDesc('is_primary')->orderBy('id'),'location','amenities'])])
            ->where('customer_id',$customerId)->whereHas('hotel',fn($q)=>$q->where('status','active'))->orderByDesc('created_at')->get();
        $items->each(fn($item)=>$item->hotel?->append('cover_image_url'));
        return $items;
    }

    public function add(int $customerId,int $hotelId): void
    {
        DB::transaction(function()use($customerId,$hotelId){
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            Hotel::where('status','active')->findOrFail($hotelId);
            Wishlist::firstOrCreate(['customer_id'=>$customerId,'hotel_id'=>$hotelId],['created_at'=>now()]);
        },3);
    }

    public function remove(int $customerId,int $hotelId): void
    {
        DB::transaction(function()use($customerId,$hotelId){
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            Wishlist::where('customer_id',$customerId)->where('hotel_id',$hotelId)->delete();
        },3);
    }

    public function toggle(int $customerId,int $hotelId): bool
    {
        return DB::transaction(function()use($customerId,$hotelId){
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $item=Wishlist::where('customer_id',$customerId)->where('hotel_id',$hotelId)->first();
            if($item){$item->delete();return false;}
            Hotel::where('status','active')->findOrFail($hotelId);
            Wishlist::create(['customer_id'=>$customerId,'hotel_id'=>$hotelId,'created_at'=>now()]);
            return true;
        },3);
    }
}
