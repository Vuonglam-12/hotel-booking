<?php

use App\Support\HotelAmenityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_amenity', function (Blueprint $table) {
            $table->integer('hotel_id')->nullable()->change();
            $table->string('catalog_identity', 64)->nullable()->unique('hotel_amenity_catalog_identity_unique');
        });

        DB::transaction(function () {
            foreach (array_keys(HotelAmenityCatalog::labels()) as $name) HotelAmenityCatalog::remember($name);
            // Preserve every existing assignment and its icon; only add reusable catalog entries.
            DB::table('hotel_amenity')->whereNotNull('hotel_id')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (HotelAmenityCatalog::normalize($row->amenity) !== '') HotelAmenityCatalog::remember($row->amenity, $row->icon);
                }
            });
        });
    }

    public function down(): void
    {
        DB::table('hotel_amenity')->whereNull('hotel_id')->delete();
        Schema::table('hotel_amenity', function (Blueprint $table) {
            $table->dropUnique('hotel_amenity_catalog_identity_unique');
            $table->dropColumn('catalog_identity');
            $table->integer('hotel_id')->nullable(false)->change();
        });
    }
};
