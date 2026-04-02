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
        Schema::create('service', function (Blueprint $table) {
            $table->comment('Dịch vụ đi kèm');
            $table->integer('id', true);
            $table->integer('hotel_id')->nullable()->index('idx_service_hotel')->comment('NULL = dịch vụ chung toàn hệ thống');
            $table->string('name', 100);
            $table->decimal('price', 12);
            $table->string('unit', 30)->nullable()->comment('per_person/per_day/per_night/per_trip');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service');
    }
};
