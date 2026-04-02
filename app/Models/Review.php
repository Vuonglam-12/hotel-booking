<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    // Tên bảng trong DB — Laravel mặc định tìm 'reviews' nên phải khai báo rõ
    protected $table = 'review';

    // Tắt auto timestamps vì bảng không có updated_at
    public $timestamps = false;

    // Các field được phép ghi vào DB
    protected $fillable = [
        'customer_id',       // Ai viết review
        'booking_id',        // Booking nào (để verify đã ở thật)
        'hotel_id',          // Review KS (1 trong 2 phải có)
        'destination_id',    // Review địa điểm (1 trong 2 phải có)
        'rating',            // Điểm tổng (1-5)
        'rating_cleanliness',// Điểm vệ sinh (1-5)
        'rating_service',    // Điểm dịch vụ (1-5)
        'rating_location',   // Điểm vị trí (1-5)
        'comment',           // Nội dung nhận xét
        'helpful_count',     // Số người thấy hữu ích
        'created_at',
    ];

    // Quan hệ: review thuộc về customer nào
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Quan hệ: review thuộc về booking nào
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Quan hệ: review thuộc về KS nào
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // Quan hệ: review có nhiều ảnh
    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }
}