<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(Schema::hasTable('booking_demo_payment_notice'))return;
        Schema::create('booking_demo_payment_notice',function(Blueprint $table){
            $table->bigIncrements('id');
            $table->integer('payment_id');
            $table->char('event_key',64)->unique();
            $table->decimal('received_amount',12,2);
            $table->json('result')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('payment_id')->references('id')->on('payment')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if(DB::table('booking_demo_payment_notice')->exists())throw new RuntimeException('Retain payment notification audit; use a forward migration.');
        Schema::dropIfExists('booking_demo_payment_notice');
    }
};
