<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('travel_source_place',fn(Blueprint $t)=>$t->json('opening_schedule')->nullable()); }
    public function down(): void { Schema::table('travel_source_place',fn(Blueprint $t)=>$t->dropColumn('opening_schedule')); }
};
