<?php

namespace App\Http\Controllers;

use App\Services\WishlistService;

class WishlistController extends Controller
{
    public function __construct(private WishlistService $service) {}
    public function index() { return response()->json($this->service->getList((int)auth('sanctum')->id())); }
    public function add(int $hotel_id)
    {
        $this->service->add((int)auth('sanctum')->id(),$hotel_id);
        return response()->json(['message'=>'Đã lưu khách sạn yêu thích.','liked'=>true]);
    }
    public function remove(int $hotel_id)
    {
        $this->service->remove((int)auth('sanctum')->id(),$hotel_id);
        return response()->json(['message'=>'Đã bỏ khách sạn khỏi yêu thích.','liked'=>false]);
    }
    public function toggle(int $hotel_id)
    {
        $liked=$this->service->toggle((int)auth('sanctum')->id(),$hotel_id);
        return response()->json(['message'=>$liked?'Đã lưu khách sạn yêu thích.':'Đã bỏ khách sạn khỏi yêu thích.','liked'=>$liked]);
    }
}
