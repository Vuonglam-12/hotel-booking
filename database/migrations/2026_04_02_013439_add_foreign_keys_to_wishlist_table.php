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
        Schema::table('wishlist', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'fk_wishlist_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['hotel_id'], 'fk_wishlist_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlist', function (Blueprint $table) {
            $table->dropForeign('fk_wishlist_customer');
            $table->dropForeign('fk_wishlist_hotel');
        });
    }
};
