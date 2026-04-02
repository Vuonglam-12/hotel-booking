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
        Schema::table('itinerary', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'fk_itinerary_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['destination_id'], 'fk_itinerary_dest')->references(['id'])->on('destination')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['session_id'], 'fk_itinerary_session')->references(['id'])->on('chat_session')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itinerary', function (Blueprint $table) {
            $table->dropForeign('fk_itinerary_customer');
            $table->dropForeign('fk_itinerary_dest');
            $table->dropForeign('fk_itinerary_session');
        });
    }
};
