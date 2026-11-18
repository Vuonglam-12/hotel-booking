<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotel_room_rate', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hotel_id');
            $table->integer('room_type_id');
            $table->string('kind', 10);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 12, 2);
            $table->string('label', 150)->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'room_type_id', 'kind', 'start_date', 'end_date'], 'idx_hotel_room_rate_period');
            $table->foreign('hotel_id')->references('id')->on('hotel');
            $table->foreign('room_type_id')->references('id')->on('room_type');
        });
        Schema::create('booking_nightly_rate', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('booking_room_id');
            $table->date('night_date');
            $table->decimal('unit_price', 12, 2);
            $table->string('source', 10);
            // Mutable price rules may be removed. Historical snapshots remain independent.
            $table->integer('rate_id')->nullable();
            $table->string('rate_label', 150)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['booking_room_id', 'night_date'], 'uq_booking_nightly_rate');
            $table->foreign('booking_room_id')->references('id')->on('booking_room');
        });
    }

    public function down(): void
    {
        if (DB::table('booking_nightly_rate')->exists() || DB::table('hotel_room_rate')->exists()) {
            throw new RuntimeException('Rollback refused: retain room prices and booking nightly snapshots.');
        }
        Schema::drop('booking_nightly_rate');
        Schema::drop('hotel_room_rate');
    }
};
