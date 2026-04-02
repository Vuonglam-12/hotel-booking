<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    protected $table = 'room_type';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'capacity',
        'base_price',
        'bed_type',
        'description',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
