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
        Schema::create('itinerary_item', function (Blueprint $table) {
            $table->comment('Từng hoạt động trong lịch trình');
            $table->integer('id', true);
            $table->integer('itinerary_id');
            $table->integer('day_number')->comment('Ngày thứ mấy trong chuyến');
            $table->integer('order_in_day')->comment('Thứ tự hoạt động trong ngày');
            $table->string('item_type', 20)->nullable()->comment('hotel/destination/restaurant/transport/activity');
            $table->integer('hotel_id')->nullable()->index('fk_item_hotel');
            $table->integer('destination_id')->nullable()->index('fk_item_dest');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('estimated_cost', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable()->comment('Tọa độ cho Map routing');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Tọa độ cho Map routing');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['itinerary_id', 'day_number', 'order_in_day'], 'idx_item_itinerary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary_item');
    }
};
