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
        Schema::create('review_image', function (Blueprint $table) {
            $table->comment('Ảnh đính kèm review');
            $table->integer('id', true);
            $table->integer('review_id')->index('idx_review_image');
            $table->string('image_url', 500);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_image');
    }
};
