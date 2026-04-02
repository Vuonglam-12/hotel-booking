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
        Schema::create('map_search_log', function (Blueprint $table) {
            $table->comment('Log tìm kiếm trên map — biết user hay tìm vùng nào');
            $table->integer('id', true);
            $table->integer('customer_id')->nullable()->index('idx_map_log_customer');
            $table->string('session_token', 100)->nullable()->comment('Nếu chưa login');
            $table->decimal('search_lat', 10, 7)->comment('Tọa độ user lúc tìm kiếm');
            $table->decimal('search_lng', 10, 7);
            $table->integer('radius_km')->default(10);
            $table->integer('result_count')->nullable()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['search_lat', 'search_lng'], 'idx_map_log_coords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('map_search_log');
    }
};
