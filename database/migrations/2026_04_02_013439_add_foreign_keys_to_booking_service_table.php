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
        Schema::table('booking_service', function (Blueprint $table) {
            $table->foreign(['booking_id'], 'fk_bs_booking')->references(['id'])->on('booking')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['service_id'], 'fk_bs_service')->references(['id'])->on('service')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropForeign('fk_bs_booking');
            $table->dropForeign('fk_bs_service');
        });
    }
};
