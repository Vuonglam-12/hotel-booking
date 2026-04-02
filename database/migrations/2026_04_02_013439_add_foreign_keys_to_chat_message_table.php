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
        Schema::table('chat_message', function (Blueprint $table) {
            $table->foreign(['referenced_destination_id'], 'fk_chat_msg_dest')->references(['id'])->on('destination')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['referenced_hotel_id'], 'fk_chat_msg_hotel')->references(['id'])->on('hotel')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['session_id'], 'fk_chat_msg_session')->references(['id'])->on('chat_session')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_message', function (Blueprint $table) {
            $table->dropForeign('fk_chat_msg_dest');
            $table->dropForeign('fk_chat_msg_hotel');
            $table->dropForeign('fk_chat_msg_session');
        });
    }
};
