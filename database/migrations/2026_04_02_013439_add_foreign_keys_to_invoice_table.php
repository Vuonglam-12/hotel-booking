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
        Schema::table('invoice', function (Blueprint $table) {
            $table->foreign(['booking_id'], 'fk_invoice_booking')->references(['id'])->on('booking')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['customer_id'], 'fk_invoice_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['payment_id'], 'fk_invoice_payment')->references(['id'])->on('payment')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice', function (Blueprint $table) {
            $table->dropForeign('fk_invoice_booking');
            $table->dropForeign('fk_invoice_customer');
            $table->dropForeign('fk_invoice_payment');
        });
    }
};
