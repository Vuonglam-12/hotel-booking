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
        Schema::create('booking', function (Blueprint $table) {
            $table->comment('Đơn đặt phòng');
            $table->integer('id', true);
            $table->integer('customer_id')->index('idx_booking_customer');
            $table->integer('hotel_id')->index('idx_booking_hotel');
            $table->integer('chat_session_id')->nullable()->index('idx_booking_chat')->comment('Booking sinh ra từ chatbot');
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('num_guests')->default(1);
            $table->decimal('total_price', 12);
            $table->string('status', 20)->default('pending')->index('idx_booking_status')->comment('pending/confirmed/cancelled/completed');
            $table->timestamp('expires_at')->nullable()->comment('Hết hạn giữ chỗ 15 phút — cron job tự cancel nếu chưa pay');
            $table->timestamp('confirmed_at')->nullable()->comment('Thời điểm booking được confirm (sau payment success)');
            $table->timestamp('cancelled_at')->nullable()->comment('Thời điểm booking bị cancel');
            $table->string('cancelled_by', 20)->nullable()->comment('customer / staff / system (expired)');
            $table->text('special_request')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['check_in', 'check_out'], 'idx_booking_dates');
            $table->unique(['customer_id', 'hotel_id', 'check_in', 'check_out', 'status'], 'uq_booking_prevent_duplicate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking');
    }
};
