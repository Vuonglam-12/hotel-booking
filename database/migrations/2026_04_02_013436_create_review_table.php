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
        Schema::create('review', function (Blueprint $table) {
            $table->comment('Đánh giá KS và địa điểm');
            $table->integer('id', true);
            $table->integer('customer_id')->index('idx_review_customer');
            $table->integer('booking_id')->nullable()->index('fk_review_booking')->comment('Verify đã ở thật mới review được');
            $table->integer('hotel_id')->nullable()->index('idx_review_hotel')->comment('Review KS — 1 trong 2 phải có');
            $table->integer('destination_id')->nullable()->index('idx_review_destination')->comment('Review địa điểm — 1 trong 2 phải có');
            $table->tinyInteger('rating');
            $table->tinyInteger('rating_cleanliness')->nullable();
            $table->tinyInteger('rating_service')->nullable();
            $table->tinyInteger('rating_location')->nullable();
            $table->text('comment')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review');
    }
};
