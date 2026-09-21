<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_room', function (Blueprint $table) {
            // Thêm cột room_id. Phải để nullable() vì lỡ trong DB mày đang có 
            // mấy đơn hàng cũ chưa có room_id, để not null nó sẽ báo lỗi crash DB.
            $table->integer('room_id')->nullable()->after('room_type_id');
            
            // Tùy chọn: Thêm index để tốc độ dò phòng trống (cái hàm tao viết) chạy nhanh hơn
            $table->index('room_id', 'idx_booking_room_room_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_room', function (Blueprint $table) {
            $table->dropIndex('idx_booking_room_room_id');
            $table->dropColumn('room_id');
        });
    }
};