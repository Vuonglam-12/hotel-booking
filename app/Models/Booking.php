<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'booking';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'hotel_id',
        'chat_session_id',
        'check_in',
        'check_out',
        'num_guests',
        'total_price',
        'status',
        'special_request',
        'created_at',
        'expires_at',       // thêm
        'confirmed_at',     // thêm
        'cancelled_at',     // thêm
        'cancelled_by',     // thêm
    ];

    protected $casts = [
        'check_in'     => 'datetime',
        'check_out'    => 'datetime',
        'created_at'   => 'datetime',
        'expires_at'   => 'datetime',   // thêm
        'confirmed_at' => 'datetime',   // thêm
        'cancelled_at' => 'datetime',   // thêm
    ];

    // Format giá tiền
    public function getFormattedPriceAttribute()
    {
        return number_format($this->total_price, 0, '.', ',') . ' VND';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_room')
                    ->withPivot('price_at_booking');
    }
}