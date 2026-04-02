<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoom extends Model
{
    protected $table = 'booking_room';

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'room_type_id',
        'price_at_booking',
        'nights',
        'quantity',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function getPriceAtBookingFormattedAttribute()
    {
        return number_format($this->price_at_booking, 0, '.', ',') . ' VND';
    }

    public function getBasePriceFormattedAttribute()
    {
        return number_format($this->base_price, 0, '.', ',') . ' VND';
    }
}