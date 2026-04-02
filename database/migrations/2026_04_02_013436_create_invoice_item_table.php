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
        Schema::create('invoice_item', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('invoice_id')->index('idx_ii_invoice');
            $table->string('description', 300);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12);
            $table->decimal('amount', 12);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item');
    }
};
