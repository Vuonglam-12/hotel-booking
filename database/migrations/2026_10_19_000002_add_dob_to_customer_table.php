<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No default or backfill: existing customers retain a NULL birth date.
        // An existing dob column must be reviewed before applying this migration.
        Schema::table('customer', function (Blueprint $table) {
            $table->date('dob')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropColumn('dob');
        });
    }
};
