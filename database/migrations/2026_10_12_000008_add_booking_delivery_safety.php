<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_effect', function (Blueprint $table) {
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('transport_started_at')->nullable();
            $table->index(['state', 'next_attempt_at'], 'idx_effect_delivery_due');
        });
        Schema::create('booking_delivery_audit', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('effect_id');
            $table->integer('actor_id')->nullable();
            $table->string('action', 40);
            $table->string('state_before', 20);
            $table->string('state_after', 20);
            $table->string('reason', 500)->nullable();
            $table->dateTime('created_at');
            $table->foreign('effect_id', 'fk_delivery_effect')->references('id')->on('booking_effect');
            $table->foreign('actor_id', 'fk_delivery_actor')->references('id')->on('customer');
            $table->index(['effect_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('booking_delivery_audit')->exists() || DB::table('booking_effect')->where('attempts', '>', 0)
            ->orWhereNotNull('next_attempt_at')->orWhereNotNull('transport_started_at')->exists()) {
            throw new RuntimeException('Delivery history must be retained; use an approved forward fix.');
        }
        Schema::drop('booking_delivery_audit');
        Schema::table('booking_effect', function (Blueprint $table) {
            $table->dropIndex('idx_effect_delivery_due');
            $table->dropColumn(['next_attempt_at', 'transport_started_at']);
        });
    }
};
