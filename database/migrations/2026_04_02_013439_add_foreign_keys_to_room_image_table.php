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
        Schema::table('room_image', function (Blueprint $table) {
            $table->foreign(['room_id'], 'fk_room_image')->references(['id'])->on('room')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_image', function (Blueprint $table) {
            $table->dropForeign('fk_room_image');
        });
    }
};
