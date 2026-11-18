<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite accepts these dates already. MySQL TIMESTAMP rejects valid scheduled dates after 2038.
        if (DB::connection()->getDriverName() !== 'mysql' || !Schema::hasColumn('guide_post', 'published_at')) return;
        $column = DB::selectOne("SELECT DATA_TYPE AS data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guide_post' AND COLUMN_NAME = 'published_at'");
        if ($column?->data_type === 'timestamp') DB::statement('ALTER TABLE `guide_post` MODIFY `published_at` DATETIME NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Keep the wider date range: narrowing again can destroy/reject existing scheduled dates.
        // Rolling back the original guide-table migration still removes the table normally.
    }
};
