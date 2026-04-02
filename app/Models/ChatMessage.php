<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    // Tên bảng trong DB
    protected $table = 'chat_message';

    // Tắt auto timestamps
    public $timestamps = false;

    // Các field được phép ghi
    protected $fillable = [
        'session_id',               // Thuộc phiên chat nào
        'role',                     // 'user' hoặc 'bot'
        'content',                  // Nội dung tin nhắn
        'intent',                   // find_hotel / plan_trip / book_room / get_info
        'entities_json',            // Thông tin trích xuất: city, check_in, budget...
        'referenced_hotel_id',      // KS bot đang gợi ý
        'referenced_destination_id',// Địa điểm bot đang nhắc đến
        'created_at',
    ];

    // Cast entities_json thành array tự động
    protected $casts = [
        'entities_json' => 'array',
        'created_at'    => 'datetime',
    ];

    // Quan hệ: tin nhắn thuộc phiên chat nào
    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    // Quan hệ: tin nhắn đang nhắc đến KS nào
    public function referencedHotel()
    {
        return $this->belongsTo(Hotel::class, 'referenced_hotel_id');
    }
}