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
        Schema::table('destination', function (Blueprint $table) {
            $table->foreign(['location_id'], 'fk_dest_location')->references(['id'])->on('location')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destination', function (Blueprint $table) {
            $table->dropForeign('fk_dest_location');
        });
    }
};
