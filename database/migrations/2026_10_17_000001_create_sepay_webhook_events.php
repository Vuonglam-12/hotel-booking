<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sepay_webhook_event', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('provider_transaction_id', 100)->unique('uq_sepay_event_transaction');
            $table->integer('payment_id')->nullable();
            $table->string('merchant_ref', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('transfer_type', 3);
            $table->string('outcome', 40);
            $table->json('payload');
            $table->char('event_hash', 64);
            $table->dateTime('received_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payment_id', 'outcome'], 'idx_sepay_event_reconciliation');
            $table->foreign('payment_id', 'fk_sepay_event_payment')->references('id')->on('payment');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sepay_webhook_event') && \Illuminate\Support\Facades\DB::table('sepay_webhook_event')->exists()) {
            throw new RuntimeException('Rollback refused: retain SePay transaction audit.');
        }
        Schema::dropIfExists('sepay_webhook_event');
    }
};
