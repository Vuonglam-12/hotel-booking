<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hotel')
            ->where('check_in_time', '07:00:00')
            ->update(['check_in_time' => '14:00:00']);
    }

    public function down(): void
    {
        // Keep the corrected check-in times; they cannot be distinguished from existing 14:00 policies.
    }
};
