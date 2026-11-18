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
        'catalog_identity',
    ];

    // Catalog entries have no hotel; assigned amenities keep their existing hotel_id.
    public function scopeCatalog($query)
    {
        return $query->whereNull('hotel_id')->whereNotNull('catalog_identity');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
