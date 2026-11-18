<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function index(Request $request,int $hotel)
    {
        $s=RoomTypeV2Inventory::open('mysql');
        $data=$request->validate(['check_in'=>'required_with:check_out|date_format:Y-m-d','check_out'=>'required_with:check_in|date_format:Y-m-d|after:check_in',
            'quantity'=>'sometimes|integer|between:1,10','num_guests'=>'sometimes|integer|min:1|max:2147483647']);
        $h=$s->db->table('hotel')->where('id',$hotel)->where('status','active')->first(); if (!$h) abort(404);
        $types=$s->db->table('room_type')->where('hotel_id',$hotel)->where('status','active')->orderBy('id')->get(); $result=[];
        foreach ($types as $type) {
            $s->types($hotel,[$type->id]);
            try { $a=isset($data['check_in'])?$s->inspect($type,$data['check_in'],$data['check_out']):null; }
            catch (\RuntimeException $e) { return response()->json(['message'=>'Inventory requires reconciliation.'],409); }
            $capacity=!isset($data['num_guests']) || $data['num_guests']<=$type->capacity*($data['quantity'] ?? 1);
            $images=$s->db->table('room_type_image')->where('room_type_id',$type->id)->orderByDesc('is_primary')->orderBy('id')->get();
            $result[]=['id'=>$type->id,'hotel_id'=>$hotel,'name'=>$type->name,'description'=>$type->description,'bed_type'=>$type->bed_type,
                'base_price'=>$type->base_price,'capacity'=>$type->capacity,'total_rooms'=>$type->total_rooms,'status'=>$type->status,
                'available_rooms'=>$a['available_rooms'] ?? null,'availability'=>$a,'bookable'=>$a!==null&&$capacity&&$a['available_rooms']>=($data['quantity'] ?? 1),
                'availability_requires_dates'=>$a===null,'capacity_sufficient'=>$capacity,'images'=>$images];
        }
        return response()->json(['hotel_id'=>$hotel,'room_types'=>$result]);
    }
}
