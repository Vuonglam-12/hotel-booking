<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itinerary', function (Blueprint $table) {
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedTinyInteger('adults')->nullable();
            $table->json('children')->nullable();
            $table->json('infants')->nullable();
            $table->string('stay_mode', 24)->nullable();
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('activity_window_start')->nullable();
            $table->time('activity_window_end')->nullable();
            $table->boolean('activity_window_confirmed')->default(false);
            $table->date('lodging_check_in')->nullable();
            $table->date('lodging_check_out')->nullable();
            $table->integer('hotel_id')->nullable();
            $table->foreign('hotel_id', 'itinerary_trip_hotel_fk')->references('id')->on('hotel')->restrictOnDelete();
            $table->string('transport', 100)->nullable();
            $table->string('pace', 20)->nullable();
            $table->json('interests')->nullable();
            $table->json('food_restrictions')->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('contingency_amount', 12, 2)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['customer_id', 'schema_version', 'status'], 'itinerary_trip_owner_status');
        });

        // Support is explicit. Existing catalog IDs are enabled once; future locations
        // are not automatically supported. No names or external region IDs are invented.
        Schema::create('itinerary_destination', function (Blueprint $table) {
            $table->integer('location_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->foreign('location_id')->references('id')->on('location')->restrictOnDelete();
        });
        DB::table('itinerary_destination')->insertUsing(['location_id', 'enabled'], DB::table('location')->select('id')->selectRaw('1'));

        Schema::create('itinerary_region_mapping', function (Blueprint $table) {
            $table->id();
            $table->integer('location_id');
            $table->string('provider', 32);
            $table->string('provider_region_id', 255)->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->json('boundary')->nullable();
            $table->json('verification_evidence')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreign('location_id')->references('id')->on('location')->restrictOnDelete();
            $table->unique(['location_id', 'provider'], 'itinerary_region_provider_unique');
        });

        Schema::create('itinerary_calendar_day', function (Blueprint $table) {
            $table->id();
            $table->integer('itinerary_id');
            $table->date('date');
            $table->boolean('selected_for_planning')->default(false);
            $table->string('planning_status', 20)->default('free');
            $table->decimal('free_day_allowance', 12, 2)->nullable();
            $table->foreign('itinerary_id')->references('id')->on('itinerary')->cascadeOnDelete();
            $table->unique(['itinerary_id', 'date'], 'itinerary_calendar_date_unique');
        });

        Schema::create('itinerary_place_snapshot', function (Blueprint $table) {
            $table->id();
            $table->integer('itinerary_id');
            $table->unsignedBigInteger('attraction_id')->nullable();
            $table->unsignedBigInteger('region_mapping_id')->nullable();
            $table->string('provider', 32);
            $table->string('provider_place_id', 255)->collation('utf8mb4_bin');
            $table->json('snapshot');
            $table->timestamp('region_verified_at')->nullable();
            $table->foreign('itinerary_id')->references('id')->on('itinerary')->cascadeOnDelete();
            $table->foreign('attraction_id')->references('id')->on('attraction')->nullOnDelete();
            $table->foreign('region_mapping_id')->references('id')->on('itinerary_region_mapping')->nullOnDelete();
            $table->unique(['itinerary_id', 'provider', 'provider_place_id'], 'itinerary_place_source_unique');
        });

        Schema::table('itinerary_item', function (Blueprint $table) {
            $table->unsignedBigInteger('calendar_day_id')->nullable();
            $table->unsignedBigInteger('place_snapshot_id')->nullable();
            $table->foreign('calendar_day_id')->references('id')->on('itinerary_calendar_day')->nullOnDelete();
            $table->foreign('place_snapshot_id')->references('id')->on('itinerary_place_snapshot')->nullOnDelete();
        });

        Schema::create('itinerary_lodging_room', function (Blueprint $table) {
            $table->id();
            $table->integer('itinerary_id');
            $table->date('night');
            $table->integer('room_type_id');
            $table->unsignedInteger('quantity');
            $table->json('verified_party')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->timestamp('policy_verified_at')->nullable();
            $table->foreign('itinerary_id')->references('id')->on('itinerary')->cascadeOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_type')->restrictOnDelete();
            $table->unique(['itinerary_id', 'night', 'room_type_id'], 'itinerary_room_night_unique');
        });

        Schema::create('itinerary_cost_line', function (Blueprint $table) {
            $table->id();
            $table->integer('itinerary_id');
            $table->unsignedBigInteger('calendar_day_id')->nullable();
            $table->integer('item_id')->nullable();
            $table->unsignedBigInteger('lodging_room_id')->nullable();
            $table->string('category', 20); // lodging | activity (including local transport)
            $table->string('price_status', 20)->default('unknown'); // known | estimated | unknown
            $table->string('unit', 24); // person | group | vehicle | room_night
            $table->char('currency', 3)->default('VND');
            $table->decimal('unit_amount', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('source', 100)->nullable();
            $table->string('source_reference', 512)->nullable();
            $table->timestamp('priced_at')->nullable();
            $table->foreign('itinerary_id')->references('id')->on('itinerary')->cascadeOnDelete();
            $table->foreign('calendar_day_id')->references('id')->on('itinerary_calendar_day')->nullOnDelete();
            $table->foreign('item_id')->references('id')->on('itinerary_item')->nullOnDelete();
            $table->foreign('lodging_room_id')->references('id')->on('itinerary_lodging_room')->nullOnDelete();
        });

        Schema::create('itinerary_write_request', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id');
            $table->string('request_key', 64)->collation('utf8mb4_bin');
            $table->string('operation', 80);
            $table->char('payload_hash', 64);
            $table->integer('itinerary_id')->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('customer_id')->references('id')->on('customer')->restrictOnDelete();
            $table->foreign('itinerary_id')->references('id')->on('itinerary')->nullOnDelete();
            $table->unique(['customer_id', 'request_key'], 'itinerary_write_request_unique');
        });
    }

    public function down(): void
    {
        throw new LogicException('Forward-only itinerary migration: preserve existing trips and columns.');
    }
};
