<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Customer;  
use App\Models\Location;

class Itinerary extends Model
{
    protected $table = 'itinerary'; 

    public $timestamps = false;
    
    protected $fillable = [
        'customer_id',     
        'title',
        'location_id',   
        'total_days',
        'start_date',
        'end_date',
        'estimated_budget',
        'status',
        'session_id',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'estimated_budget' => 'decimal:2',
        'total_days'       => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class, 'itinerary_id')
                    ->orderBy('day_number')
                    ->orderBy('order_in_day');
    }

    // THÊM relationship này — ItineraryController cần nó
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}