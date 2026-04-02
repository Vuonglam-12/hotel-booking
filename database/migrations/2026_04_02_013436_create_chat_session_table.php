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
        Schema::create('chat_session', function (Blueprint $table) {
            $table->comment('Phiên hội thoại chatbot');
            $table->integer('id', true);
            $table->integer('customer_id')->nullable()->index('idx_chat_session_customer')->comment('NULL = khách chưa đăng nhập');
            $table->string('session_token', 100)->unique('uq_session_token')->comment('Random token — không expose id thật');
            $table->string('title', 200)->nullable()->comment('VD: Tìm KS Đà Nẵng cuối tuần');
            $table->json('context_json')->nullable()->comment('Bộ nhớ bot: {city, check_in, budget, guests, intent...}');
            $table->string('status', 20)->default('active')->comment('active/closed');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_session');
    }
};
