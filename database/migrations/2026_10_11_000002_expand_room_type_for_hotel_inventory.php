<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') throw new RuntimeException('RoomType V2 expand requires MySQL.');
        Schema::table('room_type', function (Blueprint $table) {
            $table->integer('hotel_id')->nullable();
            $table->integer('total_rooms')->nullable();
            $table->string('status', 20)->nullable();
            $table->index('hotel_id', 'idx_room_type_hotel');
            $table->foreign('hotel_id', 'fk_room_type_hotel')->references('id')->on('hotel')->onUpdate('no action')->onDelete('no action');
        });
        DB::statement("ALTER TABLE room_type ADD CONSTRAINT chk_rt_total_rooms CHECK (total_rooms IS NULL OR total_rooms >= 0), ADD CONSTRAINT chk_rt_status CHECK (status IS NULL OR status IN ('active','inactive'))");
    }

    public function down(): void
    {
        if (DB::table('room_type')->whereNotNull('hotel_id')->orWhereNotNull('total_rooms')->orWhereNotNull('status')->exists()) {
            throw new RuntimeException('RoomType V2 data exists; archive/reconcile it before reverting schema.');
        }
        DB::statement('ALTER TABLE room_type DROP CHECK chk_rt_total_rooms, DROP CHECK chk_rt_status');
        Schema::table('room_type', function (Blueprint $table) {
            $table->dropForeign('fk_room_type_hotel');
            $table->dropIndex('idx_room_type_hotel');
            $table->dropColumn(['hotel_id', 'total_rooms', 'status']);
        });
    }
};
