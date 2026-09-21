<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== ITINERARIES =====
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('city');
            $table->unsignedTinyInteger('total_days')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('estimated_budget')->nullable();
            // status: draft | confirmed | completed
            $table->string('status', 20)->default('draft');
            // raw AI response (for debugging)
            $table->text('ai_raw')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // ===== ITINERARY ITEMS =====
        Schema::create('itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number')->default(1);
            // type: hotel | restaurant | attraction | transport | activity | shopping
            $table->string('type', 30)->default('attraction');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('time')->nullable();           // "08:00"
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['itinerary_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itineraries');
    }
};