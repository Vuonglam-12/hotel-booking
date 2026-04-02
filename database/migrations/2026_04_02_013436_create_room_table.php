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
        Schema::create('room', function (Blueprint $table) {
            $table->comment('Phòng khách sạn');
            $table->integer('id', true);
            $table->integer('hotel_id')->index('idx_room_hotel');
            $table->integer('room_type_id')->index('idx_room_type');
            $table->string('room_number', 10);
            $table->integer('floor')->nullable();
            $table->decimal('price', 12);
            $table->integer('capacity')->default(2);
            $table->string('status', 20)->default('available')->index('idx_room_status')->comment('available/booked/maintenance');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hotel_id', 'room_number'], 'uq_room');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room');
    }
};
