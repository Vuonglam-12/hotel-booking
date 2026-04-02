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
        Schema::create('customer', function (Blueprint $table) {
            $table->comment('Khách hàng');
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('email', 100)->unique('uq_customer_email');
            $table->string('phone', 20)->nullable();
            $table->string('password_hash')->comment('bcrypt/argon2 — KHÔNG lưu plaintext');
            $table->string('avatar_url', 500)->nullable();
            $table->integer('preferred_location_id')->nullable()->index('idx_customer_location');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer');
    }
};
