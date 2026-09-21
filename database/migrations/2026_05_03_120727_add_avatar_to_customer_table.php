<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // 2026_05_03_120727_add_avatar_to_customer_table.php

    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email'); // ← avatar_url
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropColumn('avatar_url');
        });
    }
};
