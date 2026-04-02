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
        Schema::create('itinerary', function (Blueprint $table) {
            $table->comment('Lịch trình du lịch');
            $table->integer('id', true);
            $table->integer('customer_id')->index('idx_itinerary_customer');
            $table->integer('session_id')->nullable()->index('idx_itinerary_session')->comment('Phiên chat tạo ra lịch trình này');
            $table->string('title', 200);
            $table->integer('destination_id')->nullable()->index('fk_itinerary_dest')->comment('Điểm đến chính');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('total_days')->nullable();
            $table->decimal('estimated_budget', 12)->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/confirmed/completed');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itinerary');
    }
};
