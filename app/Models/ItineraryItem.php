<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryItem extends Model
{
    protected $table = 'itinerary_item';

    public $timestamps = false;
    
    protected $fillable = [
        'itinerary_id',
        'day_number',
        'item_type',        // ← Đổi từ type
        'title',            // ← Đổi từ name
        'description',
        'start_time',       // ← Đổi từ time
        'end_time',
        'estimated_cost',   // ← Đổi từ cost
        'order_in_day',     // ← Đổi từ sort_order
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'day_number'     => 'integer',
        'order_in_day'   => 'integer',
    ];

    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class, 'itinerary_id');
    }
}