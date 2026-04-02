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
        Schema::create('wishlist', function (Blueprint $table) {
            $table->comment('Danh sách KS yêu thích');
            $table->integer('id', true);
            $table->integer('customer_id');
            $table->integer('hotel_id')->index('fk_wishlist_hotel');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['customer_id', 'hotel_id'], 'uq_wishlist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist');
    }
};
