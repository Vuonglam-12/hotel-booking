<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('booking_demo_reconciliation')) {
            Schema::create('booking_demo_reconciliation', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('booking_id');
                $table->integer('payment_id');
                $table->char('event_key', 64)->unique();
                $table->string('reason', 40);
                $table->decimal('expected_amount', 12, 2);
                $table->decimal('received_amount', 12, 2);
                $table->string('state', 24)->default('open')->index();
                $table->text('note')->nullable();
                $table->string('evidence_reference', 100)->nullable();
                $table->string('evidence_path')->nullable();
                $table->char('evidence_sha256', 64)->nullable();
                $table->integer('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('booking_id')->references('id')->on('booking')->restrictOnDelete();
                $table->foreign('payment_id')->references('id')->on('payment')->restrictOnDelete();
            });
        }
        if (!Schema::hasTable('booking_demo_reconciliation_review')) {
            Schema::create('booking_demo_reconciliation_review', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('reconciliation_id');
                $table->integer('admin_id');
                $table->string('state', 24);
                $table->text('note');
                $table->string('evidence_reference', 100)->nullable();
                $table->string('evidence_path')->nullable();
                $table->char('evidence_sha256', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreign('reconciliation_id', 'fk_demo_reconciliation_review')->references('id')->on('booking_demo_reconciliation')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_demo_reconciliation') && DB::table('booking_demo_reconciliation')->exists()) {
            throw new RuntimeException('Reconciliation history must be preserved; use a forward migration.');
        }
        Schema::dropIfExists('booking_demo_reconciliation_review');
        Schema::dropIfExists('booking_demo_reconciliation');
    }
};
