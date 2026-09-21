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
        Schema::table('itinerary_item', function (Blueprint $table) {
            $table->foreignId('attraction_id')
                ->nullable()
                ->after('itinerary_id')
                ->constrained('attraction') // Bảng attraction (số ít)
                ->nullOnDelete();
            // nullable vì AI có thể tạo item không có trong DB (tour tự phát, v.v.)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary_item', function (Blueprint $table) {
            //
        });
    }
};
