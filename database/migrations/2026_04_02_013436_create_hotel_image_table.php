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
        Schema::create('hotel_image', function (Blueprint $table) {
            $table->comment('Ảnh khách sạn');
            $table->integer('id', true);
            $table->integer('hotel_id')->index('idx_hotel_image');
            $table->string('image_url', 500);
            $table->string('caption', 200)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_image');
    }
};
