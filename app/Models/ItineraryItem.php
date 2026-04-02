<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryItem extends Model
{
    protected $table = 'itinerary_item';

    public $timestamps = false;

    protected $fillable = [
        'itinerary_id',   // Thuộc lịch trình nào
        'day_number',     // Ngày thứ mấy (1, 2, 3...)
        'order_in_day',   // Thứ tự trong ngày (1, 2, 3...)
        'item_type',      // hotel / destination / restaurant / transport / activity
        'hotel_id',       // Nếu là KS
        'destination_id', // Nếu là địa điểm
        'title',          // Tên hoạt động VD: "Check-in Sofitel"
        'description',    // Mô tả chi tiết
        'start_time',     // Giờ bắt đầu VD: "14:00"
        'end_time',       // Giờ kết thúc
        'estimated_cost', // Chi phí dự kiến
        'latitude',       // Tọa độ cho Google Maps routing
        'longitude',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Quan hệ: item thuộc lịch trình nào
    public function itinerary()
    {
        return $this->belongsTo(Itinerary::class);
    }

    // Quan hệ: nếu item là KS
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // Quan hệ: nếu item là địa điểm
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }
}