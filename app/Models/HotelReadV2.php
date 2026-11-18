<?php

namespace App\Models;

use App\Support\RoomTypeV2Inventory;

class HotelReadV2 extends Hotel
{
    public function getForeignKey() { return 'hotel_id'; }
    public function minPrice()
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        return $db->table('room_type')->where('hotel_id',$this->id)->where('status','active')->min('base_price');
    }
    public function rooms() { throw new \RuntimeException('Physical Room relation is disabled in the V2 read model.'); }
}
