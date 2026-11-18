<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_unmatched_event', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('provider', 20);
            $table->string('merchant_ref', 100);
            $table->string('provider_transaction_id', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('response_code', 2);
            $table->string('transaction_status', 2);
            $table->string('outcome', 40);
            $table->json('payload');
            $table->char('event_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'event_hash'], 'uq_unmatched_gateway_event');
            $table->index(['provider', 'outcome'], 'idx_unmatched_gateway_reconciliation');
        });
    }

    public function down(): void
    {
        if (DB::table('payment_gateway_unmatched_event')->exists()) {
            throw new RuntimeException('Rollback refused: retain unmatched gateway receipts.');
        }
        Schema::drop('payment_gateway_unmatched_event');
    }
};
