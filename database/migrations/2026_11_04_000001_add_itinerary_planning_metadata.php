<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('itinerary','planning_metadata'))Schema::table('itinerary',fn(Blueprint $table)=>$table->json('planning_metadata')->nullable());
    }
    public function down(): void
    {
        if(Schema::hasColumn('itinerary','planning_metadata'))Schema::table('itinerary',fn(Blueprint $table)=>$table->dropColumn('planning_metadata'));
    }
};
