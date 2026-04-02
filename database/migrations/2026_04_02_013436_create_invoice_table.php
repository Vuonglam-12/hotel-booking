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
        Schema::create('invoice', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('invoice_no', 50)->unique('uq_invoice_no');
            $table->integer('booking_id')->index('idx_invoice_booking');
            $table->integer('payment_id')->index('idx_invoice_payment');
            $table->integer('customer_id')->index('idx_invoice_customer');
            $table->decimal('subtotal', 12);
            $table->decimal('service_total', 12)->default(0);
            $table->decimal('discount', 12)->default(0);
            $table->decimal('tax', 12)->default(0);
            $table->decimal('total', 12);
            $table->text('notes')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->timestamp('issued_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice');
    }
};
