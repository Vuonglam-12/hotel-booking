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
        Schema::create('hotel_amenity', function (Blueprint $table) {
            $table->comment('Tiện ích khách sạn — dùng để filter tìm kiếm');
            $table->integer('id', true);
            $table->integer('hotel_id')->index('idx_amenity_hotel');
            $table->string('amenity', 100)->index('idx_amenity_name')->comment('wifi/pool/gym/spa/parking/restaurant/bar/airport_shuttle/beach_access');
            $table->string('icon', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_amenity');
    }
};
