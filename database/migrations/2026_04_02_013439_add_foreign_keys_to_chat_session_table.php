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
        Schema::table('chat_session', function (Blueprint $table) {
            $table->foreign(['customer_id'], 'fk_chat_session_customer')->references(['id'])->on('customer')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_session', function (Blueprint $table) {
            $table->dropForeign('fk_chat_session_customer');
        });
    }
};
