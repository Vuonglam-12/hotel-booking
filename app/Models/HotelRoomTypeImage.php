<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomTypeImage extends Model
{
    protected $table = 'hotel_room_type_image';
    public $timestamps = false;
    protected $guarded = ['id'];
}
