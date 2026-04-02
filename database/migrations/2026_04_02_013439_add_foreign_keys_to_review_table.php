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
        Schema::table('review', function (Blueprint $table) {
            $table->foreign(['booking_id'], 'fk_review_booking')->references(['id'])->on('booking')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['customer_id'], 'fk_review_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['destination_id'], 'fk_review_destination')->references(['id'])->on('destination')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['hotel_id'], 'fk_review_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review', function (Blueprint $table) {
            $table->dropForeign('fk_review_booking');
            $table->dropForeign('fk_review_customer');
            $table->dropForeign('fk_review_destination');
            $table->dropForeign('fk_review_hotel');
        });
    }
};
