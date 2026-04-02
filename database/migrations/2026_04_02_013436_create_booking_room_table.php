<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_room', function (Blueprint $table) {
            $table->comment('Chi tiết phòng trong đơn đặt');
            $table->integer('id', true);
            $table->integer('booking_id')->index('idx_br_booking');
            $table->integer('room_type_id')->index('idx_br_room_type')->comment('Loại phòng đặt — phòng cụ thể staff assign lúc check-in');
            $table->decimal('price_at_booking', 12)->comment('Snapshot giá lúc đặt — không đổi dù giá phòng thay đổi');
            $table->integer('nights')->default(1)->comment('Số đêm');
            $table->integer('quantity')->default(1)->comment('Số phòng cùng loại');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_room');
    }
};
