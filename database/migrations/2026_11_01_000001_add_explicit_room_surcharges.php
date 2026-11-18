<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Old values retain their original meaning. Conversion requires identified evidence.
        Schema::table('hotel_room_rate', function (Blueprint $table) {
            $table->string('amount_mode', 20)->default('replacement');
        });
        Schema::table('booking_nightly_rate', function (Blueprint $table) {
            // Null for old snapshots: do not reconstruct history from today's catalog.
            $table->decimal('base_price', 12, 2)->nullable();
            $table->decimal('surcharge', 12, 2)->nullable();
            $table->string('amount_mode', 20)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain explicit price meanings and immutable booking breakdowns.');
    }
};
