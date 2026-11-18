<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_image', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('room_type_id');
            $table->string('image_url', 500);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['room_type_id', 'image_url'], 'uq_room_type_image_url');
            $table->foreign('room_type_id', 'fk_rt_image_type')->references('id')->on('room_type')->onUpdate('no action')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (DB::table('room_type_image')->exists()) throw new RuntimeException('Type images exist; refusing a destructive rollback.');
        Schema::drop('room_type_image');
    }
};
