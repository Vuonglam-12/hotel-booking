<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Missing research facts must remain NULL, never a fabricated price or occupancy.
        // Physical Room, Booking, pricing rules, and existing values are untouched.
        DB::statement('ALTER TABLE room_type MODIFY base_price DECIMAL(12,2) NULL, MODIFY capacity INT NULL DEFAULT 2');
        DB::statement('ALTER TABLE hotel_room_type MODIFY base_price DECIMAL(12,2) NULL, MODIFY capacity INT NULL');
        if (!Schema::hasColumn('hotel_room_type', 'area_min_m2')) {
            Schema::table('hotel_room_type', function (Blueprint $table) {
                $table->decimal('area_min_m2', 10, 2)->nullable();
                $table->decimal('area_max_m2', 10, 2)->nullable();
            });
        }
        if (!Schema::hasTable('hotel_room_type_evidence')) {
            Schema::create('hotel_room_type_evidence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hotel_room_type_id');
                $table->string('source_key', 150);
                $table->text('source_url');
                $table->date('checked_on');
                $table->json('verified_fields');
                $table->json('image_sources');
                $table->json('issues');
                $table->unique(['hotel_room_type_id','source_key'], 'room_type_evidence_owner_key_unique');
                $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_type')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Additive research data must be reviewed before reversal; do not drop evidence or replace missing facts with defaults.');
    }
};
