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
        Schema::create('room_image', function (Blueprint $table) {
            $table->comment('Ảnh phòng');
            $table->integer('id', true);
            $table->integer('room_id')->index('idx_room_image');
            $table->string('image_url', 500);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_image');
    }
};
