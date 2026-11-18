<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_receipt', function (Blueprint $t) {
            $t->integer('id',true); $t->integer('payment_id'); $t->string('provider',20);
            $t->string('provider_transaction_id',100)->nullable(); $t->string('merchant_ref',100);
            $t->decimal('amount',12,2); $t->string('response_code',2); $t->string('transaction_status',2);
            $t->string('outcome',40); $t->json('payload'); $t->char('event_hash',64); $t->timestamp('created_at')->useCurrent();
            $t->unique(['payment_id','event_hash'],'uq_gateway_event');
            $t->unique(['provider','provider_transaction_id'],'uq_gateway_transaction');
            $t->foreign('payment_id','fk_receipt_payment')->references('id')->on('payment');
        });
        Schema::create('booking_effect', function (Blueprint $t) {
            $t->integer('id',true); $t->integer('booking_id'); $t->integer('payment_id');
            $t->string('event_key',100)->unique(); $t->string('state',20)->default('pending'); $t->integer('attempts')->default(0);
            $t->char('lease_token',36)->nullable(); $t->timestamp('lease_expires_at')->nullable();
            $t->integer('invoice_id')->nullable(); $t->string('last_error',100)->nullable(); $t->timestamp('delivered_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('booking_id','fk_effect_booking')->references('id')->on('booking');
            $t->foreign('payment_id','fk_effect_payment')->references('id')->on('payment');
            $t->foreign('invoice_id','fk_effect_invoice')->references('id')->on('invoice');
        });
    }
    public function down(): void
    {
        if (DB::table('payment_gateway_receipt')->exists() || DB::table('booking_effect')->exists()) throw new RuntimeException('Rollback refused: retain financial receipts/effects.');
        Schema::drop('booking_effect'); Schema::drop('payment_gateway_receipt');
    }
};
