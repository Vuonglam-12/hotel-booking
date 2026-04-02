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
        Schema::create('location', function (Blueprint $table) {
            $table->comment('Tỉnh/thành phố');
            $table->integer('id', true);
            $table->string('name', 100)->comment('Tên tỉnh/thành phố');
            $table->string('region', 50)->nullable()->comment('Bắc / Trung / Nam');
            $table->string('country', 50)->default('Vietnam');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location');
    }
};
