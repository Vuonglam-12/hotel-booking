<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_payment_verification',function (Blueprint $t) {
            $t->integer('id',true); $t->integer('booking_id'); $t->integer('payment_id')->unique(); $t->integer('verified_by');
            $t->string('bank_reference',100)->unique('uq_manual_bank_reference'); $t->decimal('received_amount',12,2);
            $t->dateTime('received_at'); $t->dateTime('verified_at'); $t->string('evidence_source',30);
            $t->string('outcome',40); $t->string('booking_status_before',20); $t->string('booking_status_after',20);
            $t->string('payment_status_before',20); $t->string('payment_status_after',20);
            $t->foreign('booking_id')->references('id')->on('booking'); $t->foreign('payment_id')->references('id')->on('payment');
            $t->foreign('verified_by')->references('id')->on('customer');
        });
        Schema::create('booking_state_audit',function (Blueprint $t) {
            $t->integer('id',true); $t->integer('booking_id'); $t->integer('actor_id')->nullable(); $t->string('actor_kind',20);
            $t->string('status_before',20)->nullable(); $t->string('status_after',20); $t->string('reason',50);
            $t->timestamp('created_at')->useCurrent(); $t->index(['booking_id','id']);
            $t->foreign('booking_id')->references('id')->on('booking'); $t->foreign('actor_id')->references('id')->on('customer');
        });
        Schema::create('booking_effect_notification',function (Blueprint $t) {
            $t->integer('effect_id')->primary(); $t->unsignedBigInteger('notification_id')->nullable()->unique();
            $t->foreign('effect_id')->references('id')->on('booking_effect');
            $t->foreign('notification_id')->references('id')->on('customer_notifications')->onDelete('set null');
        });
    }
    public function down(): void
    {
        foreach (['manual_payment_verification','booking_state_audit','booking_effect_notification'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) throw new RuntimeException('Rollback refused: preserve verification, status and notification audit.');
        }
        foreach (['booking_effect_notification','booking_state_audit','manual_payment_verification'] as $table) Schema::dropIfExists($table);
    }
};
