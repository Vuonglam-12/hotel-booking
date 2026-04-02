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
        Schema::create('staff', function (Blueprint $table) {
            $table->comment('Nhân viên / Admin');
            $table->integer('id', true);
            $table->integer('hotel_id')->nullable()->index('idx_staff_hotel')->comment('NULL = superadmin toàn hệ thống');
            $table->string('name', 100);
            $table->string('email', 100)->unique('uq_staff_email');
            $table->string('phone', 20)->nullable();
            $table->string('password_hash');
            $table->string('role', 30)->default('staff')->comment('superadmin/admin/manager/staff');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
