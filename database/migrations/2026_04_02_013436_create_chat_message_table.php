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
        Schema::create('chat_message', function (Blueprint $table) {
            $table->comment('Tin nhắn trong phiên chat');
            $table->integer('id', true);
            $table->integer('session_id')->index('idx_chat_msg_session');
            $table->string('role', 10);
            $table->text('content');
            $table->string('intent', 50)->nullable()->index('idx_chat_msg_intent')->comment('find_hotel/plan_trip/book_room/cancel/get_info');
            $table->json('entities_json')->nullable()->comment('{city, check_in, check_out, guests, budget}');
            $table->integer('referenced_hotel_id')->nullable()->index('fk_chat_msg_hotel')->comment('KS bot đang gợi ý');
            $table->integer('referenced_destination_id')->nullable()->index('fk_chat_msg_dest')->comment('Địa điểm bot đang nhắc đến');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message');
    }
};
