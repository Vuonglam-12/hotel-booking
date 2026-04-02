<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelAmenity extends Model
{
    protected $table = 'hotel_amenity';

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'amenity',
        'icon',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
