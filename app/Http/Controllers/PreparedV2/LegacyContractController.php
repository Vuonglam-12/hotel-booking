<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Support\RoomTypeV2Inventory;

class LegacyContractController extends Controller
{
    public function unavailable(int $id)
    {
        RoomTypeV2Inventory::open('mysql');
        return response()->json(['message'=>'Legacy physical-room/checkout contract is disabled in the prepared V2 overlay.',
            'room_types_url'=>url('/api/hotels/'.$id.'/room-types')],409);
    }
}
