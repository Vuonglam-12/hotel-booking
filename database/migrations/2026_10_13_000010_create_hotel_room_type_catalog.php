<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hotel_room_type')) Schema::create('hotel_room_type', function (Blueprint $table) {
            $table->id();
            $table->integer('hotel_id');
            $table->integer('room_type_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->integer('capacity');
            $table->string('bed_type', 50)->nullable();
            $table->json('amenities');
            $table->string('status', 20)->default('active');
            $table->boolean('images_configured')->default(false);
            $table->unique(['hotel_id', 'room_type_id']);
            $table->foreign('hotel_id')->references('id')->on('hotel')->restrictOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_type')->restrictOnDelete();
        });
        if (!Schema::hasTable('hotel_room_type_image')) Schema::create('hotel_room_type_image', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotel_room_type_id');
            $table->string('image_url', 500);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreign('hotel_room_type_id')->references('id')->on('hotel_room_type')->restrictOnDelete();
            $table->index(['hotel_room_type_id', 'sort_order']);
            $table->unique(['hotel_room_type_id', 'image_url'], 'hotel_room_type_image_owner_url_unique');
        });
        if (!Schema::hasColumn('room', 'catalog_retired')) Schema::table('room', fn(Blueprint $table) => $table->boolean('catalog_retired')->default(false));
        if (!Schema::hasTable('roomtype_asset_cleanup')) Schema::create('roomtype_asset_cleanup', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->unique();
        });
        // Preserve every legacy Room/BookingRoom ID and snapshot. Unique pairs make retries safe.
        DB::statement("INSERT IGNORE INTO hotel_room_type (hotel_id,room_type_id,name,description,base_price,capacity,bed_type,amenities,status)
            SELECT pairs.hotel_id,t.id,t.name,t.description,t.base_price,t.capacity,t.bed_type,JSON_ARRAY(),'active'
            FROM (SELECT DISTINCT hotel_id,room_type_id FROM room
                UNION SELECT DISTINCT b.hotel_id,br.room_type_id FROM booking_room br JOIN booking b ON b.id=br.booking_id) pairs
            JOIN room_type t ON t.id=pairs.room_type_id JOIN hotel h ON h.id=pairs.hotel_id");
        // Copy links, never move/delete legacy image rows or files. A configured gallery is never re-imported.
        DB::statement("INSERT IGNORE INTO hotel_room_type_image (hotel_room_type_id,image_url,is_primary,sort_order)
            SELECT h.id,i.image_url,0,MIN(i.id) FROM hotel_room_type h
            JOIN room r ON r.hotel_id=h.hotel_id AND r.room_type_id=h.room_type_id
            JOIN room_image i ON i.room_id=r.id WHERE h.images_configured=0 GROUP BY h.id,i.image_url");
        DB::statement("UPDATE hotel_room_type_image i JOIN
            (SELECT hotel_room_type_id,MIN(sort_order) first_order FROM (SELECT * FROM hotel_room_type_image) existing GROUP BY hotel_room_type_id) first_image
            ON first_image.hotel_room_type_id=i.hotel_room_type_id AND first_image.first_order=i.sort_order
            JOIN hotel_room_type h ON h.id=i.hotel_room_type_id SET i.is_primary=1 WHERE h.images_configured=0");
        DB::statement('UPDATE hotel_room_type h SET images_configured=1 WHERE images_configured=0 AND EXISTS (SELECT 1 FROM hotel_room_type_image i WHERE i.hotel_room_type_id=h.id)');
    }

    public function down(): void
    {
        // Edited catalog/retirement decisions must not be silently discarded by a rollback.
        throw new RuntimeException('Hotel-scoped catalog rollback requires a reviewed data-preserving restore plan.');
    }
};
