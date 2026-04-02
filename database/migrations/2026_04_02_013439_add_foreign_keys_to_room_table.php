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
        Schema::table('room', function (Blueprint $table) {
            $table->foreign(['hotel_id'], 'fk_room_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['room_type_id'], 'fk_room_type')->references(['id'])->on('room_type')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room', function (Blueprint $table) {
            $table->dropForeign('fk_room_hotel');
            $table->dropForeign('fk_room_type');
        });
    }
};
