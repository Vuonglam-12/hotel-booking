<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_legacy_map', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hotel_id');
            $table->integer('old_room_type_id');
            $table->integer('new_room_type_id');
            $table->char('plan_checksum', 64);
            $table->char('source_checksum', 64);
            $table->char('target_checksum', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['hotel_id', 'old_room_type_id'], 'uq_rt_map_source');
            $table->unique('new_room_type_id', 'uq_rt_map_target');
            $table->foreign('hotel_id', 'fk_rt_map_hotel')->references('id')->on('hotel')->onUpdate('no action')->onDelete('no action');
            $table->foreign('old_room_type_id', 'fk_rt_map_old')->references('id')->on('room_type')->onUpdate('no action')->onDelete('no action');
            $table->foreign('new_room_type_id', 'fk_rt_map_new')->references('id')->on('room_type')->onUpdate('no action')->onDelete('no action');
        });
    }

    public function down(): void
    {
        if (DB::table('room_type_legacy_map')->exists()) throw new RuntimeException('Backfill checkpoints exist; refusing a destructive rollback.');
        Schema::drop('room_type_legacy_map');
    }
};
