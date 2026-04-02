<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomImage extends Model
{
    protected $table = 'room_image';

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'image_url',
        'is_primary',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
