<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'room';

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'room_number',
        'floor',
        'price',
        'capacity',
        'status',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }
}
