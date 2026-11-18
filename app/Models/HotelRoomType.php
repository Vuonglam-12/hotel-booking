<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomType extends Model
{
    protected $table = 'hotel_room_type';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['amenities' => 'array'];

    public function images() { return $this->hasMany(HotelRoomTypeImage::class)->orderBy('sort_order')->orderBy('id'); }
}
