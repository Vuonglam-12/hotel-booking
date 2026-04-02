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
        Schema::create('room_availability', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hotel_id')->index('idx_avail_hotel');
            $table->integer('room_type_id')->index('idx_avail_room_type');
            $table->date('date')->index('idx_avail_date');
            $table->integer('total_rooms');
            $table->integer('available_count');
            $table->integer('booked_count')->default(0);

            $table->unique(['hotel_id', 'room_type_id', 'date'], 'uq_availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availability');
    }
};
