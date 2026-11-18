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
        'slug', 'experience_category', 'experience_summary', 'experience_duration_minutes', 'experience_source_url',
        'experience_location_code', 'experience_verified_by', 'experience_verified_at',
        'experience_published_at', 'experience_archived_at',
    ];

    protected $casts = ['is_active' => 'boolean', 'experience_verified_at' => 'datetime',
        'experience_published_at' => 'datetime', 'experience_archived_at' => 'datetime'];

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttractionImage::class, 'attraction_id')->orderByDesc('is_primary')->orderBy('id');
    }

    // Đổi relationship trỏ về Location
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
