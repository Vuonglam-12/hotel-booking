<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attraction extends Model
{
    protected $table = 'attraction'; 

    // Đổi destination_id thành location_id
    protected $fillable = [
        'location_id', 'item_type', 'name', 'description', 'address',
        'price_min', 'price_max', 'duration_hours', 'open_time', 'close_time',
        'lat', 'lng', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // Đổi relationship trỏ về Location
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}