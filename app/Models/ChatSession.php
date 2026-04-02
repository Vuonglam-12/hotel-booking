<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    // Tên bảng trong DB
    protected $table = 'chat_session';

    // Tắt auto timestamps vì bảng dùng started_at/ended_at thay vì created_at/updated_at
    public $timestamps = false;

    // Các field được phép ghi vào DB
    protected $fillable = [
        'customer_id',    // User đang chat (null nếu chưa đăng nhập)
        'session_token',  // Token random để identify phiên chat
        'title',          // Tiêu đề tự động từ tin nhắn đầu tiên
        'context_json',   // Bộ nhớ ngắn hạn của bot (city, budget, guests...)
        'status',         // active / closed
        'started_at',
        'ended_at',
    ];

    // Cast context_json thành array tự động khi đọc
    protected $casts = [
        'context_json' => 'array',
        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
    ];

    // Quan hệ: 1 session có nhiều tin nhắn
    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    // Quan hệ: session thuộc về customer nào
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}