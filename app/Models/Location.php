<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'location';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'region',
        'country',
        'latitude',
        'longitude',
    ];

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }

    public function rooms()
    {
        return $this->hasManyThrough(Room::class, Hotel::class, 'location_id', 'hotel_id', 'id', 'id');
    }

    // CẦN THÊM:
    public function attractions()
    {
        return $this->hasMany(Attraction::class);
    }
}
