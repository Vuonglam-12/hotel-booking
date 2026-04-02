<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    // Tên bảng trong DB
    protected $table = 'itinerary';

    // Tắt auto timestamps
    public $timestamps = false;

    protected $fillable = [
        'customer_id',       // User tạo lịch trình
        'session_id',        // Phiên chat tạo ra lịch trình này (nếu có)
        'title',             // Tên lịch trình VD: "Du lịch Hội An 3N2Đ"
        'destination_id',    // Điểm đến chính
        'start_date',        // Ngày bắt đầu
        'end_date',          // Ngày kết thúc
        'total_days',        // Tổng số ngày
        'estimated_budget',  // Ngân sách dự kiến
        'status',            // draft / confirmed / completed
        'created_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'created_at' => 'datetime',
    ];

    // Quan hệ: 1 lịch trình có nhiều hoạt động
    public function items()
    {
        return $this->hasMany(ItineraryItem::class)
            ->orderBy('day_number')
            ->orderBy('order_in_day');
    }

    // Quan hệ: lịch trình thuộc về customer nào
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Quan hệ: điểm đến chính của lịch trình
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    // Quan hệ: lịch trình được tạo từ phiên chat nào
    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}