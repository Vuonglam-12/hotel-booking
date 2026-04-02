<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelImage extends Model
{
    protected $table = 'hotel_image';

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'image_url',
        'caption',
        'is_primary',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
