<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payment';

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'payment_status',
        'transaction_id',
        'paid_at',
        'created_at',
        'refund_amount',  // thêm
        'refunded_at',    // thêm
        'refund_note',    // thêm
    ];

    protected $casts = [
        'paid_at'      => 'datetime',
        'created_at'   => 'datetime',
        'refunded_at'  => 'datetime',  // thêm
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}