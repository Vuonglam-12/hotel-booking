<?php

namespace App\Services\PreparedV2;

use App\Services\RoomAvailabilityService;
use App\Services\RoomAvailabilityServiceV2;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Validation\ValidationException;

class AvailabilityBridge extends RoomAvailabilityService
{
    public function validateCapacity(int $hotelId,int $roomTypeId,int $quantity,int $guests,?int $roomId=null): void
    {
        $s=RoomTypeV2Inventory::open('mysql'); $type=$s->types($hotelId,[$roomTypeId])[$roomTypeId];
        if ($roomId!==null) throw ValidationException::withMessages(['room_id'=>'Physical room selection is unavailable in V2.']);
        if ($type->status!=='active') throw ValidationException::withMessages(['room_type_id'=>'This RoomType has stopped selling.']);
        if ($quantity<1 || $quantity>10 || $guests<1 || $guests>$type->capacity*$quantity) throw ValidationException::withMessages(['num_guests'=>'Invalid quantity/capacity.']);
    }
    public function forType(int $hotelId,int $roomTypeId,string $checkIn,string $checkOut): array
    {
        return app(RoomAvailabilityServiceV2::class)->forType('mysql',$hotelId,$roomTypeId,$checkIn,$checkOut);
    }
    public function forTypeWithRooms(int $hotelId,int $roomTypeId,string $checkIn,string $checkOut): array
    {
        return ['availability'=>$this->forType($hotelId,$roomTypeId,$checkIn,$checkOut),'rooms'=>[]];
    }
}
