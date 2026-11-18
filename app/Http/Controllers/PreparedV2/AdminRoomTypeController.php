<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Services\RoomTypeAdminServiceV2;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class AdminRoomTypeController extends Controller
{
    public function index(int $hotel)
    {
        $s=RoomTypeV2Inventory::open('mysql'); $s->hotel($hotel);
        return response()->json(['inventory_model'=>'v2','room_types'=>$s->db->table('room_type')->where('hotel_id',$hotel)->orderBy('id')->get()]);
    }
    public function page(int $hotel)
    {
        $s=RoomTypeV2Inventory::open('mysql'); $record=$s->hotel($hotel);
        return view('prepared-v2.admin-room-types',['hotelId'=>$hotel,'hotelName'=>$record->name]);
    }
    public function store(Request $r,int $hotel,RoomTypeAdminServiceV2 $service) { [$changes,$files]=app(\App\Services\AdminAccommodationImageService::class)->input($r); return response()->json(['room_type'=>$service->save('mysql',$hotel,null,$r->all(),$changes,$files)],201); }
    public function update(Request $r,int $hotel,int $type,RoomTypeAdminServiceV2 $service) { [$changes,$files]=app(\App\Services\AdminAccommodationImageService::class)->input($r); return response()->json(['room_type'=>$service->save('mysql',$hotel,$type,$r->all(),$changes,$files)]); }
    public function disable(int $hotel,int $type,RoomTypeAdminServiceV2 $service) { return response()->json(['room_type'=>$service->save('mysql',$hotel,$type,['status'=>'inactive'])]); }
    public function images(int $hotel,int $type,RoomTypeAdminServiceV2 $service) {
        $service->gallery('mysql',$hotel,$type); // Existing Hotel–Type ownership check.
        $images=app(\App\Services\AdminAccommodationImageService::class);
        return response()->json(['images'=>$images->gallery(RoomTypeV2Inventory::open('mysql')->db,'room_type_image','room_type_id',$type),'image_validation'=>$images::limits()]);
    }
    public function addImage(Request $r,int $hotel,int $type,RoomTypeAdminServiceV2 $service) { return response()->json(['images'=>$service->gallery('mysql',$hotel,$type,$r->all())]); }
    public function deleteImage(int $hotel,int $type,int $image,RoomTypeAdminServiceV2 $service) { return response()->json(['images'=>$service->gallery('mysql',$hotel,$type,null,$image)]); }
}
