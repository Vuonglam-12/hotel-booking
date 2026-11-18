<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\RoomTypeV2LegacyFixture;
use Tests\TestCase;

class AdminRoomTypeSourceTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = RoomTypeV2TestDatabase::settings();
        $bootstrap = RoomTypeV2TestDatabase::connection('mysql');
        $schema = null;
        foreach ($settings['schemas'] as $candidate) {
            if ((int) $bootstrap->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?', [$candidate])->n === 0) {
                $schema = $candidate;
                break;
            }
        }
        if (!$schema) throw new \RuntimeException('Owned test schemas exhausted.');
        config(['database.connections.mysql.database' => $schema]);
        DB::purge('mysql');
        $this->db = RoomTypeV2TestDatabase::connection('mysql');
        RoomTypeV2LegacyFixture::schema(); // Deliberately keep the original schema, without V2 tables/columns.
        Schema::table('customer', fn($table) => $table->string('role', 20)->default('user'));
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        $this->db->table('location')->insert(['id' => 1, 'name' => 'Test city']);
        $this->db->table('customer')->insert(['id' => 1, 'name' => 'Test admin', 'email' => 'admin@example.test', 'role' => 'admin']);
        foreach ([1, 2, 3] as $id) $this->db->table('hotel')->insert(['id' => $id, 'name' => 'Hotel ' . $id, 'location_id' => 1, 'latitude' => 0, 'longitude' => 0]);
        $this->db->table('room_type')->insert(['id' => 1, 'name' => 'Shared Standard', 'capacity' => 2, 'base_price' => '500000.25']);
        foreach ([[1, 1], [2, 1], [3, 2]] as [$id, $hotel]) {
            $this->db->table('room')->insert(['id' => $id, 'hotel_id' => $hotel, 'room_type_id' => 1, 'room_number' => (string) $id,
                'price' => '500000.25', 'capacity' => 2, 'status' => 'available']);
        }
    }

    private function admin(): void
    {
        $admin = Customer::findOrFail(1);
        $admin->withAccessToken(new PersonalAccessToken(['name' => 'admin_token', 'abilities' => ['admin']]));
        $this->actingAs($admin, 'admin');
    }

    public function test_auto_reads_legacy_schema_and_scopes_shared_types_to_the_hotel(): void
    {
        $this->admin();
        $url = '/api/admin/hotels/1/room-types?inventory_model=auto';
        $this->getJson($url)->assertOk()->assertJsonPath('inventory_model', 'legacy')->assertJsonCount(1, 'room_types')
            ->assertJsonPath('room_types.0.hotel_id', 1)->assertJsonPath('room_types.0.total_rooms', 2)
            ->assertJsonPath('room_types.0.base_price', '500000.25')->assertJsonPath('room_types.0.status', null);
        $this->getJson('/api/admin/hotels/2/room-types?inventory_model=auto')->assertOk()->assertJsonPath('room_types.0.total_rooms', 1);
        $this->getJson('/api/admin/hotels/3/room-types?inventory_model=auto')->assertOk()->assertJsonCount(0, 'room_types');
        $this->getJson('/api/admin/hotels/999/room-types?inventory_model=auto')->assertNotFound();
        $this->assertFalse(Schema::hasColumn('room_type', 'hotel_id'));
        $this->assertFalse(Schema::hasTable('room_type_image'));
    }

    public function test_existing_array_contract_is_preserved(): void
    {
        $this->admin();
        $this->getJson('/api/admin/hotels/1/room-types')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', 1)->assertJsonPath('0.rooms_count', 2);
    }

    public function test_legacy_creation_uses_real_rooms_and_preserves_shared_types(): void
    {
        $this->admin();
        $response = $this->postJson('/api/admin/hotels/1/room-types', ['inventory_model' => 'legacy', 'name' => 'New Deluxe',
            'base_price' => '700000.25', 'capacity' => 3, 'room_count' => 3, 'floor_start' => 2])->assertCreated()
            ->assertJsonPath('rooms_created', 3);
        $type = $response->json('room_type.id');
        $rooms = $this->db->table('room')->where('room_type_id', $type)->get();
        $this->assertCount(3, $rooms);
        $this->assertSame([1], $rooms->pluck('hotel_id')->unique()->values()->all());
        $this->assertSame([2], $rooms->pluck('floor')->unique()->values()->all());
        $this->assertSame(['700000.25'], $rooms->pluck('price')->unique()->values()->all());
        $this->assertSame(3, $rooms->pluck('room_number')->unique()->count());
        $this->assertSame('500000.25', $this->db->table('room_type')->where('id', 1)->value('base_price'));
        $this->getJson('/api/admin/hotels/2/room-types?inventory_model=auto')->assertJsonCount(1, 'room_types');
    }

    public function test_legacy_create_rejects_v2_fields_instead_of_silently_discarding_them(): void
    {
        $this->admin();
        $this->postJson('/api/admin/hotels/1/room-types', ['inventory_model' => 'legacy', 'name' => 'Invalid', 'base_price' => '500000',
            'capacity' => 2, 'room_count' => 2, 'total_rooms' => 20, 'status' => 'inactive', 'image_changes' => ['primary_id' => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors(['total_rooms', 'status', 'image_changes']);
        $this->assertSame(1, $this->db->table('room_type')->count());
        $this->assertSame(3, $this->db->table('room')->count());
    }

    public function test_explicit_or_configured_v2_still_returns_guard_error_without_fallback(): void
    {
        $this->admin();
        $settingsPath = getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS');
        putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS');
        try {
            $this->getJson('/api/admin/hotels/1/room-types?inventory_model=v2')->assertStatus(503)
                ->assertJsonPath('code', 'room_type_v2_unavailable');
            config(['roomtype-v2.prepared_routes' => true]);
            $this->getJson('/api/admin/hotels/1/room-types?inventory_model=auto')->assertStatus(503)
                ->assertJsonPath('code', 'room_type_v2_unavailable');
        } finally {
            putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settingsPath);
        }
        $this->assertSame(1, $this->db->table('room_type')->count());
    }

    public function test_auto_catalog_still_requires_admin_authentication(): void
    {
        $this->getJson('/api/admin/hotels/1/room-types?inventory_model=auto')->assertUnauthorized();
    }
}
