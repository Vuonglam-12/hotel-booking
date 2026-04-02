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
        Schema::create('room_type', function (Blueprint $table) {
            $table->comment('Loại phòng');
            $table->integer('id', true);
            $table->string('name', 100)->comment('Standard/Deluxe/Suite/Family/Presidential');
            $table->integer('capacity')->default(2);
            $table->decimal('base_price', 12);
            $table->string('bed_type', 50)->nullable()->comment('Single/Double/Twin/King');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_type');
    }
};
