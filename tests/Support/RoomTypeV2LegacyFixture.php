<?php

namespace Tests\Support;

use App\Support\RoomTypeV2Plan;
use Illuminate\Database\Connection;

final class RoomTypeV2LegacyFixture
{
    public static function schema(): void
    {
        // Minimal FK parents; core schemas come from existing source migrations, after the test guard.
        \Illuminate\Support\Facades\Schema::create('location', function ($table) { $table->integer('id', true); $table->string('name', 100); });
        \Illuminate\Support\Facades\Schema::create('customer', function ($table) { $table->integer('id', true); $table->string('name', 100); $table->string('email', 100); });
        \Illuminate\Support\Facades\Schema::create('chat_session', function ($table) { $table->integer('id', true); });
        $names = ['hotel', 'room_type', 'room', 'room_image', 'booking', 'booking_room'];
        foreach ($names as $name) (require base_path('database/migrations/2026_04_02_013436_create_' . $name . '_table.php'))->up();
        foreach (['hotel', 'room', 'room_image', 'booking', 'booking_room'] as $name) {
            (require base_path('database/migrations/2026_04_02_013439_add_foreign_keys_to_' . $name . '_table.php'))->up();
        }
        (require base_path('database/migrations/2026_04_23_151951_add_room_id_to_booking_room_table.php'))->up();
    }

    public static function data(Connection $db, RoomTypeV2Plan $plan): void
    {
        $stamp = '2026-10-09 03:03:26';
        $db->table('location')->insert(['id' => 1, 'name' => 'Isolated fixture location']);
        foreach ($plan->types as $type) $db->table('room_type')->insert(array_merge($type, ['created_at' => $stamp]));
        foreach (collect($plan->groups)->unique('hotel_id') as $group) {
            $db->table('hotel')->insert(['id' => (int) $group['hotel_id'], 'location_id' => 1, 'name' => $group['hotel_name'],
                'latitude' => '0.0000000', 'longitude' => '0.0000000', 'address' => 'Isolated fixture address', 'status' => $group['hotel_status'], 'created_at' => $stamp]);
        }
        foreach ($plan->groups as $group) {
            foreach ($group['room_ids'] as $offset => $id) {
                // Existing numbered rows are fixture input only. The backfill never generates them.
                $db->table('room')->insert(['id' => $id, 'hotel_id' => (int) $group['hotel_id'], 'room_type_id' => (int) $group['legacy_room_type_id'],
                    'room_number' => 'LEG-' . $id, 'floor' => (int) $group['legacy_room_type_id'], 'price' => $group['price_before_room'],
                    'capacity' => (int) $group['capacity_before'], 'status' => 'available', 'created_at' => $stamp]);
                $db->table('room_image')->insert(['id' => $group['image_ids'][$offset], 'room_id' => $id, 'image_url' => $group['source_image_url'], 'is_primary' => true, 'created_at' => $stamp]);
            }
        }
    }

    public static function history(Connection $db): void
    {
        $db->table('customer')->insert(['id' => 9001, 'name' => 'Isolated history fixture', 'email' => 'history@example.test']);
        $db->table('booking')->insert(['id' => 901, 'customer_id' => 9001, 'hotel_id' => 1, 'check_in' => '2026-11-10', 'check_out' => '2026-11-12',
            'num_guests' => 6, 'total_price' => '4000667.50', 'status' => 'confirmed']);
        $db->table('booking_room')->insert([
            ['id' => 1901, 'booking_id' => 901, 'room_type_id' => 1, 'room_id' => 1, 'quantity' => 1, 'nights' => 2, 'price_at_booking' => '500000.00'],
            ['id' => 1902, 'booking_id' => 901, 'room_type_id' => 2, 'room_id' => null, 'quantity' => 3, 'nights' => 2, 'price_at_booking' => '500111.25'],
        ]);
    }
}
