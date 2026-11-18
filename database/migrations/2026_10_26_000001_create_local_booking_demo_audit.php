<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('booking_demo')) {
            Schema::create('booking_demo', function (Blueprint $t) {
                $t->integer('booking_id')->primary();
                $t->integer('customer_id');
                $t->string('request_key', 64);
                $t->string('request_hash', 64);
                $t->string('room_name', 100);
                $t->unsignedBigInteger('expires_at_epoch')->index();
                $t->unsignedBigInteger('created_at_epoch');
                $t->unique(['customer_id', 'request_key'], 'uq_demo_customer_request');
                $t->foreign('booking_id')->references('id')->on('booking')->restrictOnDelete();
                $t->foreign('customer_id')->references('id')->on('customer')->restrictOnDelete();
            });
        }
        if (!Schema::hasTable('booking_demo_event')) {
            Schema::create('booking_demo_event', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->integer('booking_id');
                $t->integer('payment_id');
                $t->string('outcome', 24);
                $t->string('actor_kind', 12);
                $t->integer('actor_id')->nullable();
                $t->unsignedBigInteger('created_at_epoch');
                $t->unique(['payment_id', 'outcome'], 'uq_demo_payment_outcome');
                $t->foreign('booking_id')->references('id')->on('booking')->restrictOnDelete();
                $t->foreign('payment_id')->references('id')->on('payment')->restrictOnDelete();
            });
        }
        foreach (['booking_demo'=>['booking_id','customer_id','request_key','request_hash','room_name','expires_at_epoch','created_at_epoch'],
            'booking_demo_event'=>['id','booking_id','payment_id','outcome','actor_kind','actor_id','created_at_epoch']] as $table=>$columns) {
            if (!Schema::hasColumns($table, $columns)) throw new RuntimeException('Partial local demo schema requires inspection: '.$table);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_demo') && \Illuminate\Support\Facades\DB::table('booking_demo')->exists()) {
            throw new RuntimeException('Demo booking history exists; preserve records and use a reviewed forward fix.');
        }
        Schema::dropIfExists('booking_demo_event');
        Schema::dropIfExists('booking_demo');
    }
};
