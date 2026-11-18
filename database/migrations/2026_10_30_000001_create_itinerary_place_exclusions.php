<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('itinerary_place_exclusion', function (Blueprint $table) {
            $table->char('identity_key',64)->primary();
            $table->string('location_name',150);
            $table->string('name',200);
            $table->string('provider_place_id',255)->nullable();
            $table->json('coordinates')->nullable();
            $table->string('address',500)->nullable();
            $table->string('status',40);
            $table->string('source',80);
            $table->dateTime('observed_at');
        });
    }
    public function down(): void { Schema::dropIfExists('itinerary_place_exclusion'); }
};
