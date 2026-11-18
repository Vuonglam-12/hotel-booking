<?php

namespace App\Models;

use App\Models\Concerns\HasAccommodationImages;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasAccommodationImages;

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

    public function bookingLines() { return $this->hasMany(BookingRoom::class, 'room_id'); }

    public function scopeManaged($query)
    {
        return \App\Services\HotelRoomTypeService::enabled() ? $query->where('catalog_retired', false) : $query;
    }

    public function scopeOffered($query)
    {
        if (!\App\Services\HotelRoomTypeService::enabled()) return $query;
        return $query->managed()->whereNotExists(fn($q) => $q->selectRaw('1')->from('hotel_room_type as catalog')
            ->whereColumn('catalog.hotel_id', 'room.hotel_id')->whereColumn('catalog.room_type_id', 'room.room_type_id')->whereIn('catalog.status', ['inactive', 'archived']));
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }


}
