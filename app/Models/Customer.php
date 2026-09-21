<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'customer';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'avatar_url',
        'preferred_location_id',
        'role',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }
    public function payments()
    {
        return $this->hasManyThrough(
            \App\Models\Payment::class,
            \App\Models\Booking::class,
            'customer_id', // FK trên booking
            'booking_id',  // FK trên payment
            'id',          // PK trên customer
            'id',          // PK trên booking
        );
    }
}
