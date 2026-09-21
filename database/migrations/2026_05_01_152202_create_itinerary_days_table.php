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
        Schema::create('itinerary_day', function (Blueprint $table) {
            $table->id();
            $table->integer('itinerary_id');
            $table->foreign('itinerary_id')
                ->references('id')
                ->on('itinerary')
                ->cascadeOnDelete();
            $table->integer('day_number');
            $table->string('theme')->nullable(); // Lưu chủ đề ngày (VD: Khám phá phố cổ)
            $table->timestamps();
        });
    }
};
