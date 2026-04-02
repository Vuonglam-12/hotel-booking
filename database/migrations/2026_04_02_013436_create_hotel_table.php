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
        Schema::create('hotel', function (Blueprint $table) {
            $table->comment('Thông tin khách sạn');
            $table->integer('id', true);
            $table->integer('location_id')->index('idx_hotel_location');
            $table->string('name', 200);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 300)->nullable();
            $table->decimal('latitude', 10, 7)->comment('Vĩ độ — hiển thị Google Maps');
            $table->decimal('longitude', 10, 7)->comment('Kinh độ — hiển thị Google Maps');
            $table->string('google_place_id', 100)->nullable()->comment('Google Place ID — lấy ảnh, directions từ Google');
            $table->tinyInteger('star_rating')->default(3)->index('idx_hotel_star');
            $table->text('description')->nullable();
            $table->time('check_in_time')->default('14:00:00');
            $table->time('check_out_time')->default('12:00:00');
            $table->string('status', 20)->default('active')->index('idx_hotel_status')->comment('active/inactive/closed');
            $table->decimal('avg_rating', 2, 1)->default(0)->comment('Denormalized từ review — tính sẵn để query nhanh');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['latitude', 'longitude'], 'idx_hotel_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel');
    }
};
