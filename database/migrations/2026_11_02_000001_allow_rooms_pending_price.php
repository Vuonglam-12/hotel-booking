<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Inventory may be configured before a genuine price is available.
        // Unknown is NULL, never a fabricated zero price. Existing values are preserved.
        DB::statement('ALTER TABLE room MODIFY price DECIMAL(12,2) NULL');
    }

    public function down(): void
    {
        if (DB::table('room')->whereNull('price')->exists()) {
            throw new RuntimeException('Cannot discard rooms with an unknown price. Complete pricing before reversing.');
        }
        DB::statement('ALTER TABLE room MODIFY price DECIMAL(12,2) NOT NULL');
    }
};
