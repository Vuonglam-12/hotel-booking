<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotelAmenity;
use App\Support\HotelAmenityCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class HotelAmenityCatalogTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = HotelRoomTypeFixture::initialize();
        HotelRoomTypeFixture::migrate();
        $this->withoutVite();
    }

    private function admin(): void
    {
        $admin = Customer::findOrFail(1);
        $admin->withAccessToken(new PersonalAccessToken(['name' => 'admin_token', 'abilities' => ['admin']]));
        $this->actingAs($admin, 'admin');
    }

    public function test_catalog_creation_survives_reload_hotel_assignment_and_removal(): void
    {
        $this->admin();
        $this->postJson('/api/admin/amenities', ['name' => "  Vườn\t trên   mái  "])
            ->assertCreated()->assertJsonPath('amenity.key', 'Vườn trên mái');
        $this->getJson('/api/admin/hotels/1')->assertJsonCount(0, 'hotel.amenities');
        $this->getJson('/api/admin/amenities')->assertJsonFragment(['key' => 'Vườn trên mái', 'label' => 'Vườn trên mái']);
        $this->putJson('/api/admin/hotels/1', ['amenities' => ['wifi', 'Vườn trên mái']])->assertOk();
        $this->putJson('/api/admin/hotels/2', ['amenities' => ['Vườn trên mái']])->assertOk();
        $otherHotel = $this->db->table('hotel_amenity')->where('hotel_id', 2)->first();
        $this->putJson('/api/admin/hotels/1', ['amenities' => []])->assertOk();
        $this->assertEquals($otherHotel, $this->db->table('hotel_amenity')->where('hotel_id', 2)->first());
        $this->putJson('/api/admin/hotels/2', ['amenities' => []])->assertOk();
        $this->getJson('/api/admin/amenities')->assertJsonFragment(['key' => 'Vườn trên mái', 'label' => 'Vườn trên mái']);
        $this->assertSame(1, HotelAmenity::catalog()->where('amenity', 'Vườn trên mái')->count());
    }

    public function test_normalized_duplicates_and_default_aliases_are_rejected(): void
    {
        $this->admin();
        $this->postJson('/api/admin/amenities', ['name' => 'Shared lounge'])->assertCreated();
        foreach ([' SHARED  lounge ', "Shared\tlounge", "shared\u{00a0}lounge", 'WiFi', ' WIFI ', 'airport', 'Đưa đón sân bay'] as $name) {
            $this->postJson('/api/admin/amenities', compact('name'))->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->assertSame(1, HotelAmenity::catalog()->where('catalog_identity', hash('sha256', 'shared lounge'))->count());
    }

    public function test_database_unique_key_prevents_duplicate_catalog_records(): void
    {
        $existing = HotelAmenityCatalog::remember('Shared lounge');
        $this->expectException(UniqueConstraintViolationException::class);
        HotelAmenity::create(['amenity' => 'SHARED LOUNGE', 'hotel_id' => null, 'catalog_identity' => $existing->catalog_identity]);
    }

    public function test_validation_rejects_empty_invalid_and_long_names_without_writes(): void
    {
        $this->admin();
        $count = HotelAmenity::catalog()->count();
        foreach ([null, '', " \t\n ", "\u{00a0}", ['wifi'], 123, str_repeat('a', 101)] as $name) {
            $this->postJson('/api/admin/amenities', compact('name'))->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        $this->assertSame($count, HotelAmenity::catalog()->count());
    }

    public function test_catalog_api_requires_an_admin(): void
    {
        $this->getJson('/api/admin/amenities')->assertUnauthorized();
        $this->postJson('/api/admin/amenities', ['name' => 'Shared lounge'])->assertUnauthorized();
        $customer = Customer::findOrFail(2);
        $customer->withAccessToken(new PersonalAccessToken(['name' => 'admin_token', 'abilities' => ['admin']]));
        $this->actingAs($customer, 'admin');
        $this->getJson('/api/admin/amenities')->assertForbidden();
        $this->postJson('/api/admin/amenities', ['name' => 'Shared lounge'])->assertForbidden();
    }

    public function test_migration_retains_existing_links_and_backfills_a_shared_catalog(): void
    {
        $migration = require base_path('database/migrations/2026_10_17_000002_persist_hotel_amenity_catalog.php');
        $migration->down();
        $this->db->table('hotel_amenity')->insert([
            ['hotel_id' => 1, 'amenity' => '  Legacy   lounge ', 'icon' => 'old-icon'],
            ['hotel_id' => 2, 'amenity' => 'LEGACY LOUNGE', 'icon' => 'other-icon'],
        ]);
        $before = $this->db->table('hotel_amenity')->orderBy('id')->get(['id', 'hotel_id', 'amenity', 'icon'])->all();
        $migration->up();
        $this->assertEquals($before, $this->db->table('hotel_amenity')->whereNotNull('hotel_id')->orderBy('id')->get(['id', 'hotel_id', 'amenity', 'icon'])->all());
        $this->assertSame(1, HotelAmenity::catalog()->where('catalog_identity', hash('sha256', 'legacy lounge'))->count());
    }

    public function test_database_failure_returns_a_clear_error_and_keeps_hotel_assignments(): void
    {
        $this->admin();
        $id = $this->db->table('hotel_amenity')->insertGetId(['hotel_id' => 2, 'amenity' => 'wifi', 'icon' => 'retained']);
        $this->db->unprepared("CREATE TRIGGER reject_amenity_catalog BEFORE INSERT ON hotel_amenity FOR EACH ROW BEGIN IF NEW.hotel_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated catalog write failure'; END IF; END");
        try {
            $this->postJson('/api/admin/amenities', ['name' => 'Cannot persist'])->assertStatus(503)
                ->assertJsonPath('message', 'Không thể lưu tiện nghi vào danh mục. Vui lòng thử lại.');
            $this->assertFalse(HotelAmenity::catalog()->where('amenity', 'Cannot persist')->exists());
            $this->assertSame('retained', $this->db->table('hotel_amenity')->where('id', $id)->value('icon'));
        } finally {
            $this->db->unprepared('DROP TRIGGER reject_amenity_catalog');
        }
    }
}
