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
        Schema::create('destination', function (Blueprint $table) {
            $table->comment('Địa điểm du lịch');
            $table->integer('id', true);
            $table->integer('location_id')->index('idx_dest_location');
            $table->string('name', 200);
            $table->string('category', 50)->nullable()->index('idx_dest_category')->comment('beach/mountain/city/temple/park/entertainment');
            $table->text('description')->nullable();
            $table->string('address', 300)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('google_place_id', 100)->nullable()->comment('Google Place ID — dùng cho Maps API');
            $table->string('image_url', 500)->nullable();
            $table->decimal('avg_rating', 2, 1)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['latitude', 'longitude'], 'idx_dest_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destination');
    }
};
