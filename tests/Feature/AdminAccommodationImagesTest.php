<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\AdminAccommodationImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\AdminImagesFixture;
use Tests\TestCase;

class AdminAccommodationImagesTest extends TestCase
{
    private $db;
    private array $types;

    protected function setUp(): void
    {
        parent::setUp(); [$this->db, $this->types] = AdminImagesFixture::initialize();
        AdminImagesFixture::routes(); Storage::fake('public'); $this->withoutVite();
    }

    private function admin(string $role = 'admin', array $abilities = ['admin']): void
    {
        $admin = Customer::findOrFail(9004); $admin->role = $role;
        $admin->withAccessToken(new PersonalAccessToken(['name' => 'admin_token', 'abilities' => $abilities]));
        $this->actingAs($admin, 'admin');
    }

    private function upload(string $url, array $changes = [], array $attributes = []): \Illuminate\Testing\TestResponse
    {
        return $this->post($url, [...$attributes, '_method' => 'PUT', 'image_changes' => json_encode($changes),
            'images' => [UploadedFile::fake()->image('new.jpg')]], ['Accept' => 'application/json']);
    }

    public function test_no_selection_preserves_both_galleries_and_uploads_append_with_primary(): void
    {
        $this->admin();
        foreach ([['hotel_image', '/api/admin/hotels/1'], ['room_type_image', '/api/admin/hotels/1/room-types/' . $this->types[0]]] as [$table, $url]) {
            $before = $this->db->table($table)->get()->toArray();
            $this->putJson($url, ['name' => 'Updated image fixture'])->assertOk();
            $this->assertEquals($before, $this->db->table($table)->get()->toArray());
            $this->upload($url, ['primary_new' => 0])->assertOk();
            $rows = $this->getJson($url . '/images')->assertOk()->assertJsonPath('image_validation.max_bytes', 2097152)->json('images');
            $this->assertCount(2, $rows); $this->assertEquals(1, $rows[0]['is_primary']);
            $this->assertStringStartsWith('storage/accommodation/', $rows[0]['image_url']);
            Storage::disk('public')->assertExists(substr($rows[0]['image_url'], 8));
            $this->putJson($url, ['image_changes' => ['primary_id' => $rows[1]['id']]])->assertOk();
            $this->getJson($url . '/images')->assertJsonPath('images.0.id', $rows[1]['id']);
        }
    }

    public function test_deleting_links_keeps_shared_files_legacy_rows_and_other_objects(): void
    {
        $this->admin();
        // Own the shared asset instead of depending on a sample removed from the public gallery.
        $sharedImage = UploadedFile::fake()->image('shared.jpg');
        Storage::disk('public')->put('shared-existing.jpg', file_get_contents($sharedImage->getRealPath()));
        foreach (['hotel_image', 'room_type_image', 'room_image'] as $table) {
            $this->db->table($table)->update(['image_url' => 'storage/shared-existing.jpg']);
        }
        $legacy = $this->db->table('room_image')->get()->toArray();
        $asset = Storage::disk('public')->path('shared-existing.jpg'); $hash = hash_file('sha256', $asset);
        $hotelId = $this->db->table('hotel_image')->where('hotel_id', 1)->value('id');
        $typeId = $this->db->table('room_type_image')->where('room_type_id', $this->types[0])->value('id');
        $this->putJson('/api/admin/hotels/1', ['image_changes' => ['delete_ids' => [$hotelId]]])->assertOk();
        $this->putJson('/api/admin/hotels/1/room-types/' . $this->types[0], ['image_changes' => ['delete_ids' => [$typeId]]])->assertOk();
        $this->assertSame($hash, hash_file('sha256', $asset));
        $this->assertEquals($legacy, $this->db->table('room_image')->get()->toArray());
        $this->assertSame(1, $this->db->table('room_type_image')->where('room_type_id', $this->types[2])->count());
        $this->getJson('/api/admin/hotels/1/images')->assertJsonCount(0, 'images');
    }

    public function test_foreign_hotel_type_image_and_primary_are_rejected_atomically(): void
    {
        $this->admin(); $other = $this->db->table('room_type_image')->where('room_type_id', $this->types[2])->value('id');
        $url = '/api/admin/hotels/1/room-types/' . $this->types[0];
        foreach ([['delete_ids' => [$other]], ['primary_id' => $other]] as $changes) {
            $this->upload($url, $changes, ['name' => 'Must rollback'])->assertUnprocessable();
            $this->assertSame('Image fixture 1', $this->db->table('room_type')->where('id', $this->types[0])->value('name'));
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
        $this->upload('/api/admin/hotels/2/room-types/' . $this->types[0])->assertUnprocessable();
        $this->getJson('/api/admin/hotels/2/room-types/' . $this->types[0] . '/images')->assertUnprocessable();
        $foreignHotelImage = $this->db->table('hotel_image')->insertGetId(['hotel_id' => 2, 'image_url' => 'image/img_room/room (1).jpg']);
        $this->upload('/api/admin/hotels/1', ['delete_ids' => [$foreignHotelImage]], ['name' => 'Must rollback'])->assertUnprocessable();
        $this->assertNotSame('Must rollback', $this->db->table('hotel')->where('id', 1)->value('name'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_files_primary_and_form_fields_do_not_change_galleries(): void
    {
        $this->admin();
        foreach (['/api/admin/hotels/1', '/api/admin/hotels/1/room-types/' . $this->types[0]] as $url) {
            $before = $this->getJson($url . '/images')->json('images');
            foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(2049), UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')] as $file) {
                $this->post($url, ['_method' => 'PUT', 'images' => [$file]], ['Accept' => 'application/json'])->assertUnprocessable();
            }
            $this->upload($url, ['primary_new' => 5])->assertUnprocessable();
            $this->upload($url, [], ['name' => str_repeat('x', 201)])->assertUnprocessable();
            $this->assertSame($before, $this->getJson($url . '/images')->json('images'));
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guest_user_and_non_admin_ability_cannot_read_or_write_images(): void
    {
        $urls = ['/api/admin/hotels/1', '/api/admin/hotels/1/room-types/' . $this->types[0]];
        foreach ($urls as $url) { $this->getJson($url . '/images')->assertUnauthorized(); $this->upload($url)->assertUnauthorized(); }
        foreach ([['user', ['admin']], ['admin', ['customer']]] as [$role, $abilities]) {
            $this->admin($role, $abilities);
            foreach ($urls as $url) { $this->getJson($url . '/images')->assertForbidden(); $this->upload($url)->assertForbidden(); }
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_transaction_failure_removes_only_its_new_uploads_and_rolls_back_links(): void
    {
        $service = app(AdminAccommodationImageService::class); Storage::disk('public')->put('shared.jpg', 'shared existing asset');
        $before = $this->db->table('hotel_image')->get()->toArray();
        try {
            $service->atomic($this->db, function ($uploads) use ($service) {
                $id = $this->db->table('hotel_image')->value('id');
                $service->apply($this->db, 'hotel_image', 'hotel_id', 1, ['delete_ids' => [$id]], [UploadedFile::fake()->image('new.jpg')], $uploads);
                throw new \RuntimeException('Synthetic save failure');
            });
            $this->fail('Expected failure');
        } catch (\RuntimeException $error) { $this->assertSame('Synthetic save failure', $error->getMessage()); }
        $this->assertEquals($before, $this->db->table('hotel_image')->get()->toArray());
        $this->assertSame(['shared.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_create_hotel_with_multiple_images_and_selected_cover_is_atomic(): void
    {
        $this->admin();
        $payload = ['name' => 'New image Hotel', 'location_id' => 1, 'star_rating' => 4, 'address' => 'Synthetic address',
            'status' => 'active', 'image_changes' => json_encode(['primary_new' => 1])];
        $response = $this->post('/api/admin/hotels', [...$payload, 'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')]], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('hotel.id');
        $gallery = $this->getJson('/api/admin/hotels/' . $id . '/images')->assertOk()->assertJsonCount(2, 'images')->json('images');
        $this->assertEquals(1, $gallery[0]['is_primary']);
        $this->assertGreaterThan($gallery[1]['id'], $gallery[0]['id']);
        $before = $this->db->table('hotel')->count();
        $files = Storage::disk('public')->allFiles();
        $this->post('/api/admin/hotels', [...$payload, 'images' => [UploadedFile::fake()->createWithContent('bad.jpg', 'invalid')]], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($before, $this->db->table('hotel')->count());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_quick_roomtype_create_and_public_gallery_reuse_v2_contract_without_duplicate_filtering(): void
    {
        $this->admin();
        $this->db->table('hotel')->where('id', 1)->update(['status' => 'active']);
        $stock = $this->db->table('room')->count();
        $same = UploadedFile::fake()->image('same.jpg');
        $content = file_get_contents($same->getRealPath());
        $response = $this->post('/api/admin/hotels/1/room-types', [
            'inventory_model' => 'v2', 'name' => 'Uploaded RoomType', 'base_price' => '700000.00', 'capacity' => 2,
            'total_rooms' => 3, 'status' => 'active', 'image_changes' => json_encode(['primary_new' => 1]),
            'images' => [UploadedFile::fake()->createWithContent('one.jpg', $content), UploadedFile::fake()->createWithContent('two.jpg', $content)],
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('room_type.id');
        $this->assertEquals(1, $response->json('room_type.hotel_id'));
        $this->assertSame($stock, $this->db->table('room')->count());
        $gallery = $this->getJson('/api/admin/hotels/1/room-types/' . $id . '/images')->assertJsonCount(2, 'images')->json('images');
        $this->assertGreaterThan($gallery[1]['id'], $gallery[0]['id']);
        $public = $this->getJson('/api/hotels/1/room-types')->assertOk()->json('room_types');
        $type = collect($public)->firstWhere('id', $id);
        $this->assertSame(array_column($gallery, 'image_url'), array_column($type['images'], 'image_url'));
        $this->assertFalse(config('roomtype-v2.staging_checkout'));
        // Also exercise the root quick-form delegation, with all existing V2 guards in place.
        $request = \Illuminate\Http\Request::create('/api/admin/hotels/1/room-types', 'GET', ['inventory_model' => 'v2']);
        $request->setUserResolver(fn() => auth('admin')->user());
        $root = app(\App\Http\Controllers\AdminController::class);
        $this->assertContains($id, array_column($root->getRoomTypes($request, 1)->getData(true)['room_types'], 'id'));
        config(['roomtype-v2.prepared_routes' => true]);
        $automatic = \Illuminate\Http\Request::create('/api/admin/hotels/1/room-types', 'GET', ['inventory_model' => 'auto']);
        $automatic->setUserResolver(fn() => Customer::findOrFail(9004));
        $catalog = $root->getRoomTypes($automatic, 1)->getData(true);
        $this->assertSame('v2', $catalog['inventory_model']);
        $this->assertContains($id, array_column($catalog['room_types'], 'id'));
        $request = \Illuminate\Http\Request::create('/api/admin/hotels/1/room-types', 'POST', ['inventory_model'=>'v2', 'name'=>'Root quick Type', 'base_price'=>'100000.00', 'capacity'=>2, 'total_rooms'=>1, 'status'=>'active']);
        $request->setUserResolver(fn() => auth('admin')->user());
        $request->files->set('images', [UploadedFile::fake()->image('root.jpg')]);
        $result = $root->createRoomType($request, 1)->getData(true);
        $this->assertEquals(1, $result['room_type']['hotel_id']);
        $this->getJson('/api/admin/hotels/1/room-types/' . $result['room_type']['id'] . '/images')->assertJsonCount(1, 'images');
    }
}
