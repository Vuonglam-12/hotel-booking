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
            $table->foreign(['destination_id'], 'fk_item_dest')->references(['id'])->on('destination')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['hotel_id'], 'fk_item_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['itinerary_id'], 'fk_item_itinerary')->references(['id'])->on('itinerary')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary_item', function (Blueprint $table) {
            $table->dropForeign('fk_item_dest');
            $table->dropForeign('fk_item_hotel');
            $table->dropForeign('fk_item_itinerary');
        });
    }
};
