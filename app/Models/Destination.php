<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $table = 'destination';

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'name',
        'category',
        'description',
        'address',
        'latitude',
        'longitude',
        'google_place_id',
        'image_url',
        'avg_rating',
        'created_at',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}