<?php

namespace Tests\Support;

use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Support\Facades\DB;

final class RoomTypeV2BackendFixture
{
    public static function initialize(?RoomTypeV2Plan $plan = null): \Illuminate\Database\Connection
    {
        $plan ??= RoomTypeV2Plan::prepared();
        $settings = RoomTypeV2TestDatabase::settings();
        $bootstrap = RoomTypeV2TestDatabase::connection('mysql');
        $schema = null;
        foreach ($settings['schemas'] as $candidate) {
            if ((int) $bootstrap->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?', [$candidate])->n === 0) { $schema = $candidate; break; }
        }
        if (!$schema) throw new \RuntimeException('Owned test schemas exhausted; start a new isolated server.');
        config(['database.connections.mysql.database' => $schema]); DB::purge('mysql');
        $db = RoomTypeV2TestDatabase::connection('mysql');
        RoomTypeV2LegacyFixture::schema();
        foreach (['payment', 'invoice', 'invoice_item', 'hotel_image', 'room_availability', 'hotel_amenity'] as $table) {
            (require base_path('database/migrations/2026_04_02_013436_create_' . $table . '_table.php'))->up();
        }
        foreach (['payment', 'invoice', 'invoice_item', 'hotel_image', 'room_availability', 'hotel_amenity'] as $table) {
            (require base_path('database/migrations/2026_04_02_013439_add_foreign_keys_to_' . $table . '_table.php'))->up();
        }
        (require base_path('database/migrations/2026_05_02_141122_create_customer_notifications_table.php'))->up();
        RoomTypeV2LegacyFixture::data($db, $plan);
        foreach (['000002_expand_room_type_for_hotel_inventory', '000003_create_room_type_image_table', '000004_create_room_type_legacy_map_table'] as $name) {
            (require base_path('database/migrations/2026_10_11_' . $name . '.php'))->up();
        }
        foreach ([9001,9002,9003,9004] as $id) $db->table('customer')->insert(['id' => $id, 'name' => 'Isolated booking fixture', 'email' => 'fixture-' . $id . '@example.test']);
        $db->table('hotel_image')->insert(['hotel_id' => 1, 'image_url' => 'image/img_room/room (1).jpg', 'is_primary' => true]);
        return $db;
    }
}
