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
        Schema::table('hotel_amenity', function (Blueprint $table) {
            $table->foreign(['hotel_id'], 'fk_amenity_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_amenity', function (Blueprint $table) {
            $table->dropForeign('fk_amenity_hotel');
        });
    }
};
