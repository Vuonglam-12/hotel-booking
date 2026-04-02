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
        Schema::table('booking', function (Blueprint $table) {
            $table->foreign(['chat_session_id'], 'fk_booking_chat')->references(['id'])->on('chat_session')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'fk_booking_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['hotel_id'], 'fk_booking_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking', function (Blueprint $table) {
            $table->dropForeign('fk_booking_chat');
            $table->dropForeign('fk_booking_customer');
            $table->dropForeign('fk_booking_hotel');
        });
    }
};
