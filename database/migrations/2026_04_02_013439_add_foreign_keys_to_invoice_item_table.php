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
        Schema::table('invoice_item', function (Blueprint $table) {
            $table->foreign(['invoice_id'], 'fk_ii_invoice')->references(['id'])->on('invoice')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_item', function (Blueprint $table) {
            $table->dropForeign('fk_ii_invoice');
        });
    }
};
