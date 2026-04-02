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
}
