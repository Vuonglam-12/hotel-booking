<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'room';

    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'room_number',
        'floor',
        'price',
        'capacity',
        'status',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }


    public function getCoverImageUrlAttribute(): string
    {
        $img = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : $this->images()->orderByDesc('is_primary')->first();

        if (!$img) {
            return "https://picsum.photos/seed/room-{$this->id}/400/300";
        }

        return asset(dirname($img->image_url) . '/' . rawurlencode(basename($img->image_url)));
    }
}
