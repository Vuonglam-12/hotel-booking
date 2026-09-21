<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Itinerary extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'city',
        'total_days',
        'start_date',
        'end_date',
        'estimated_budget',
        'status',
        'ai_raw',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'estimated_budget' => 'integer',
        'total_days'       => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('day_number')->orderBy('sort_order');
    }
}


// ===== ITINERARY ITEM MODEL =====
// (Đặt cùng file để tiện, hoặc tách ra file riêng ItineraryItem.php)