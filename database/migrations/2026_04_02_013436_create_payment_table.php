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
        Schema::create('payment', function (Blueprint $table) {
            $table->comment('Thanh toán');
            $table->integer('id', true);
            $table->integer('booking_id')->index('idx_payment_booking');
            $table->decimal('amount', 12);
            $table->string('payment_method', 50)->nullable()->comment('cash/card/momo/vnpay/zalopay/banking');
            $table->enum('payment_status', ['pending', 'success', 'failed', 'expired', 'refunded'])->default('pending')->index('idx_payment_status')->comment('pending/success/failed/expired/refunded');
            $table->decimal('refund_amount', 12)->nullable()->comment('Số tiền hoàn — NULL nếu chưa refund, < amount nếu partial refund');
            $table->timestamp('refunded_at')->nullable()->comment('Thời điểm hoàn tiền');
            $table->string('refund_note', 300)->nullable()->comment('Lý do hoàn tiền');
            $table->string('transaction_id', 100)->nullable()->unique('uq_payment_transaction')->comment('Mã GD từ VNPay/Momo — dùng để đối soát');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};
