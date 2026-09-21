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
        Schema::create('attraction', function (Blueprint $table) {
            $table->id();
            $table->integer('location_id');
            $table->foreign('location_id')
                ->references('id')
                ->on('location')
                ->cascadeOnDelete();

            $table->enum('item_type', ['hotel', 'restaurant', 'attraction', 'activity', 'transport', 'shopping']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('price_min')->default(0);
            $table->unsignedInteger('price_max')->default(0);
            $table->unsignedTinyInteger('duration_hours')->default(2);
            $table->string('open_time')->nullable();
            $table->string('close_time')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
