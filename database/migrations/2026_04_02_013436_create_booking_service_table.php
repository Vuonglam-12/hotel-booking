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
        Schema::create('booking_service', function (Blueprint $table) {
            $table->comment('Dịch vụ trong đơn đặt');
            $table->integer('id', true);
            $table->integer('booking_id')->index('idx_bs_booking');
            $table->integer('service_id')->index('idx_bs_service');
            $table->integer('quantity')->default(1);
            $table->decimal('price_at_booking', 12)->comment('Snapshot giá lúc đặt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_service');
    }
};
