<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $table = 'wishlist';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'hotel_id',
        'created_at',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}