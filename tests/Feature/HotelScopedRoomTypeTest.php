<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotelRoomType;
use App\Services\AdminAccommodationImageService;
use App\Services\BookingService;
use App\Services\HotelRoomTypeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class HotelScopedRoomTypeTest extends TestCase
{
    private $db;
    protected function setUp(): void { parent::setUp();$this->db=HotelRoomTypeFixture::initialize();HotelRoomTypeFixture::migrate();Storage::fake('public');Mail::fake();$this->withoutVite(); }
    private function admin(string $role='admin', array $abilities=['admin']): void {
        $user=Customer::findOrFail(1);$user->role=$role;$user->withAccessToken(new PersonalAccessToken(['name'=>'admin_token','abilities'=>$abilities]));$this->actingAs($user,'admin');
    }
    private function url(int $hotel=1,int $type=1): string { return '/api/admin/hotels/'.$hotel.'/room-types/'.$type; }
    private function upload(int $hotel=1,array $changes=[],int $count=2): \Illuminate\Testing\TestResponse {
        $files=[];for($i=0;$i<$count;$i++)$files[]=UploadedFile::fake()->image('same-name.jpg');
        return $this->post($this->url($hotel),['_method'=>'PUT','inventory_model'=>'scoped','image_changes'=>json_encode($changes),'images'=>$files],['Accept'=>'application/json']);
    }
    private function booking(int $quantity=2,int $guests=6,?int $roomId=null): int {
        $customer=$this->db->table('customer')->insertGetId(['name'=>'Booking fixture','email'=>bin2hex(random_bytes(6)).'@example.test','role'=>'user']);
        $id=$this->db->table('booking')->insertGetId(['customer_id'=>$customer,'hotel_id'=>1,'check_in'=>now()->addDays(5)->toDateString(),'check_out'=>now()->addDays(7)->toDateString(),'num_guests'=>$guests,'total_price'=>'1234567.89','status'=>'confirmed']);
        $this->db->table('booking_room')->insert(['booking_id'=>$id,'room_type_id'=>1,'room_id'=>$roomId,'quantity'=>$quantity,'nights'=>2,'price_at_booking'=>'617283.94']);return $id;
    }

    public function test_migration_is_additive_idempotent_and_preserves_edited_metadata(): void {
        $rooms=$this->db->table('room')->get()->toArray();$id=$this->booking();$history=$this->db->table('booking_room')->get()->toArray();
        $this->admin();$this->putJson($this->url(),['name'=>'Deluxe A','amenities'=>['Balcony']])->assertOk();
        HotelRoomTypeFixture::migrate();HotelRoomTypeFixture::migrate();
        $this->assertSame(2,$this->db->table('hotel_room_type')->count());$this->assertSame('Deluxe A',$this->db->table('hotel_room_type')->where('hotel_id',1)->value('name'));
        $this->assertEquals($rooms,$this->db->table('room')->get()->toArray());$this->assertEquals($history,$this->db->table('booking_room')->get()->toArray());
        $this->assertFalse(Schema::hasColumn('room_type','hotel_id'));$this->assertFalse(Schema::hasTable('room_type_image'));
    }

    public function test_shared_deluxe_changes_only_hotel_a_and_customer_reads_its_metadata(): void {
        $this->admin();$this->putJson($this->url(),['name'=>'Deluxe A','description'=>'Private A','base_price'=>'900000.13','capacity'=>3,'bed_type'=>'Twin','amenities'=>['Balcony','Bathtub'],'status'=>'active'])
            ->assertOk()->assertJsonPath('room_type.hotel_id',1)->assertJsonPath('room_type.total_rooms',5);
        $this->getJson($this->url(2))->assertOk()->assertJsonPath('room_type.name','Deluxe')->assertJsonPath('room_type.base_price','800000.25');
        $this->assertSame('Deluxe',$this->db->table('room_type')->where('id',1)->value('name'));
        $this->assertSame(['800000.25'],$this->db->table('room')->where('hotel_id',2)->pluck('price')->unique()->values()->all());
        $this->getJson('/api/hotels/1/rooms')->assertOk()->assertJsonPath('0.room_type.name','Deluxe A')->assertJsonPath('0.room_type.capacity',3)->assertJsonPath('0.room_type.amenities.0','Balcony');
        $this->getJson('/api/hotels/2/rooms')->assertJsonPath('0.room_type.name','Deluxe');
        $this->get('/hotels/1')->assertOk()->assertSee('Deluxe A')->assertSee('Balcony');
        $this->getJson('/api/hotels')->assertOk();$this->getJson('/api/hotels/1')->assertOk()->assertJsonPath('rooms.0.room_type.name','Deluxe A');
    }

    public function test_foreign_hotel_type_or_image_cannot_be_read_or_written(): void {
        $this->admin();$this->upload(2)->assertOk();$image=$this->getJson($this->url(2).'/images')->json('images.0.id');
        foreach([$this->url(3),$this->url(1,999)] as $url){$this->getJson($url)->assertNotFound();$this->getJson($url.'/images')->assertNotFound();$this->putJson($url,['name'=>'Bad'])->assertNotFound();
            $this->post($url,['_method'=>'PUT','images'=>[UploadedFile::fake()->image('bad-owner.jpg')]],['Accept'=>'application/json'])->assertNotFound();}
        foreach([['delete_ids'=>[$image]],['primary_id'=>$image],['order'=>['s-'.$image]]] as $changes)$this->putJson($this->url(),['image_changes'=>$changes])->assertUnprocessable();
        $this->getJson($this->url(2).'/images')->assertJsonCount(2,'images');$this->assertCount(2,Storage::disk('public')->allFiles());
    }

    public function test_upload_cover_reorder_delete_and_customer_gallery_are_hotel_owned(): void {
        $this->admin();$this->upload(1,['primary_new'=>1])->assertOk();$this->upload(2)->assertOk();
        $gallery=$this->getJson($this->url().'/images')->assertOk()->assertJsonPath('image_ordering',true)->json('images');
        $paths=array_column($gallery,'image_url');$this->assertNotSame($paths[0],$paths[1]);
        $this->putJson($this->url(),['image_changes'=>['order'=>['s-'.$gallery[1]['id'],'s-'.$gallery[0]['id']],'primary_id'=>$gallery[0]['id']]])->assertOk();
        $this->getJson($this->url().'/images')->assertJsonPath('images.0.id',$gallery[1]['id'])->assertJsonPath('images.1.is_primary',1);
        $public=$this->getJson('/api/hotels/1/rooms')->assertOk()->json('0.images');$this->assertSame([$paths[1],$paths[0]],array_column($public,'image_url'));
        $this->putJson($this->url(),['image_changes'=>['delete_ids'=>[$gallery[0]['id']],'order'=>['s-'.$gallery[1]['id']]]])->assertOk();
        $this->getJson($this->url().'/images')->assertJsonCount(1,'images')->assertJsonPath('images.0.is_primary',1);
        Storage::disk('public')->assertMissing(substr($paths[0],8));Storage::disk('public')->assertExists(substr($paths[1],8));
        $this->getJson($this->url(2).'/images')->assertJsonCount(2,'images');$this->assertSame(0,$this->db->table('roomtype_asset_cleanup')->count());
    }

    public function test_invalid_fields_files_and_order_preserve_catalog_and_storage(): void {
        $this->admin();foreach([['capacity'=>0],['base_price'=>'1e6'],['name'=>str_repeat('x',101)],['status'=>'bad'],['amenities'=>['same','same']],['hotel_id'=>2]] as $input)$this->putJson($this->url(),$input)->assertUnprocessable();
        foreach([UploadedFile::fake()->create('bad.svg',1,'image/svg+xml'),UploadedFile::fake()->image('large.jpg')->size(2049),UploadedFile::fake()->createWithContent('fake.jpg','broken')] as $file)
            $this->post($this->url(),['_method'=>'PUT','images'=>[$file]],['Accept'=>'application/json'])->assertUnprocessable();
        $this->upload(1,['order'=>['n-0']])->assertUnprocessable();
        $this->assertSame('Deluxe',$this->db->table('hotel_room_type')->where('hotel_id',1)->value('name'));$this->assertSame([],Storage::disk('public')->allFiles());
    }

    public function test_create_immediately_has_owned_metadata_images_and_edit_support(): void {
        $this->admin();$response=$this->post('/api/admin/hotels/3/room-types',['inventory_model'=>'scoped','name'=>'New A','base_price'=>'700000.13','capacity'=>2,'room_count'=>2,'floor_start'=>2,'amenities'=>['Desk'],'status'=>'active','images'=>[UploadedFile::fake()->image('cover.jpg')]],['Accept'=>'application/json'])->assertCreated()->assertJsonPath('rooms_created',2);
        $type=$response->json('room_type.id');$url=$this->url(3,$type);
        $this->getJson($url.'/images')->assertJsonCount(1,'images');$this->putJson($url,['name'=>'Edited new A'])->assertOk();
        $this->getJson('/api/admin/hotels/3/room-types?inventory_model=auto')->assertJsonPath('inventory_model','scoped')->assertJsonPath('room_types.0.name','Edited new A');
        $this->getJson($this->url(1,$type))->assertNotFound();
    }

    public function test_capacity_reduction_respects_future_bookings_and_price_snapshots(): void {
        $this->admin();$id=$this->booking();$before=$this->db->table('booking_room')->get()->toArray();
        $this->putJson($this->url(),['capacity'=>2])->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->putJson($this->url(),['capacity'=>3,'base_price'=>'1000000.99'])->assertOk();
        $this->assertEquals($before,$this->db->table('booking_room')->get()->toArray());$this->assertSame('1234567.89',$this->db->table('booking')->where('id',$id)->value('total_price'));
        $this->getJson('/api/hotels/1/rooms?check_in='.now()->addDays(5)->toDateString().'&check_out='.now()->addDays(7)->toDateString())->assertJsonPath('0.total_rooms',5)->assertJsonPath('0.available_rooms',3);
    }

    public function test_resize_preserves_linked_rooms_reuses_retired_rooms_and_rejects_shortage(): void {
        $this->admin();$this->booking(3,9);$this->putJson($this->url(),['total_rooms'=>2])->assertUnprocessable();
        $linked=$this->db->table('room')->where('hotel_id',1)->value('id');$this->booking(1,2,$linked);
        $this->putJson($this->url(),['total_rooms'=>4])->assertOk()->assertJsonPath('room_type.total_rooms',4);
        $this->assertSame(5,$this->db->table('room')->where('hotel_id',1)->count());$this->assertSame(0,$this->db->table('room')->where('id',$linked)->value('catalog_retired'));
        $this->putJson($this->url(),['total_rooms'=>5])->assertOk();$this->assertSame(5,$this->db->table('room')->where('hotel_id',1)->count());
    }

    public function test_inactive_stops_new_selection_without_mutating_bookings_or_physical_status(): void {
        $this->admin();$this->booking();$rows=$this->db->table('room')->get()->toArray();$history=$this->db->table('booking_room')->get()->toArray();
        $this->putJson($this->url(),['status'=>'inactive'])->assertOk();$this->getJson('/api/hotels/1/rooms')->assertJsonCount(0);$this->getJson('/api/hotels/2/rooms')->assertJsonCount(5);
        $this->assertEquals($rows,$this->db->table('room')->get()->toArray());$this->assertEquals($history,$this->db->table('booking_room')->get()->toArray());
        $this->putJson($this->url(),['status'=>'active'])->assertOk();$this->getJson('/api/hotels/1/rooms')->assertJsonCount(5);
    }

    public function test_booking_service_uses_scoped_price_while_checkout_gate_is_unchanged(): void {
        $this->admin();$this->putJson($this->url(),['base_price'=>'900000.13','name'=>'Deluxe A'])->assertOk();
        $booking=app(BookingService::class)->create(2,['hotel_id'=>1,'room_type_id'=>1,'quantity'=>1,'num_guests'=>2,'check_in'=>now()->addDays(10)->toDateString(),'check_out'=>now()->addDays(12)->toDateString(),'payment_method'=>'cash']);
        $this->assertEquals('900000.13',$booking->bookingRooms[0]->price_at_booking);$this->assertEquals('1800000.26',$booking->total_price);
        $this->assertSame('Deluxe A',$booking->bookingRooms[0]->roomType->name);
        $this->assertSame('Deluxe A',app(BookingService::class)->getDetail($booking->id,2)->bookingRooms[0]->roomType->name);
        $this->actingAs(Customer::findOrFail(2),'sanctum')->postJson('/api/bookings',[])->assertStatus(503);
    }

    public function test_failed_transaction_removes_its_new_files_and_rolls_back_images(): void {
        $this->admin();$record=HotelRoomType::where('hotel_id',1)->first();$service=app(AdminAccommodationImageService::class);
        try{$service->atomic($this->db,function($uploads)use($service,$record){$service->apply($this->db,'hotel_room_type_image','hotel_room_type_id',$record->id,[],[UploadedFile::fake()->image('new.jpg')],$uploads);throw new \RuntimeException('Synthetic failure');});$this->fail();}catch(\RuntimeException $e){$this->assertSame('Synthetic failure',$e->getMessage());}
        $this->assertSame([],Storage::disk('public')->allFiles());$this->assertSame(0,$this->db->table('hotel_room_type_image')->count());
    }

    public function test_existing_physical_image_links_are_imported_once_and_do_not_resurrect_after_delete(): void {
        $this->admin();$room=$this->db->table('room')->where('hotel_id',1)->value('id');
        $this->db->table('room_image')->insert(['room_id'=>$room,'image_url'=>'image/placeholders/accommodation.svg','is_primary'=>true]);
        HotelRoomTypeFixture::migrate();$gallery=$this->getJson($this->url().'/images')->assertJsonCount(1,'images')->json('images');
        $this->putJson($this->url(),['image_changes'=>['delete_ids'=>[$gallery[0]['id']]]])->assertOk();HotelRoomTypeFixture::migrate();
        $this->getJson($this->url().'/images')->assertJsonCount(0,'images');$this->assertSame(1,$this->db->table('room_image')->count());
        $this->getJson('/api/hotels/1/rooms')->assertJsonCount(0,'0.images');
    }

    public function test_guest_customer_and_missing_admin_ability_cannot_manage_metadata_or_images(): void {
        $this->getJson($this->url())->assertUnauthorized();$this->putJson($this->url(),['name'=>'Bad'])->assertUnauthorized();$this->getJson($this->url().'/images')->assertUnauthorized();
        foreach([['user',['admin']],['admin',['customer']]] as [$role,$abilities]){$this->admin($role,$abilities);$this->getJson($this->url())->assertForbidden();$this->upload()->assertForbidden();}
    }

    public function test_empty_amenities_multipart_and_shared_asset_cleanup_are_safe(): void {
        $this->admin();$this->putJson($this->url(),['amenities'=>['Desk']])->assertOk();
        $this->post($this->url(),['_method'=>'PUT','amenities'=>'[]','images'=>[UploadedFile::fake()->image('owned.jpg')]],['Accept'=>'application/json','Content-Type'=>'multipart/form-data'])
            ->assertOk()->assertJsonPath('room_type.amenities',[]);
        $image=$this->getJson($this->url().'/images')->json('images.0');
        $this->db->table('hotel_image')->insert(['hotel_id'=>2,'image_url'=>$image['image_url'],'is_primary'=>true]);
        $this->putJson($this->url(),['image_changes'=>['delete_ids'=>[$image['id']]]])->assertOk();
        Storage::disk('public')->assertExists(substr($image['image_url'],8));$this->assertSame(1,$this->db->table('hotel_image')->count());
        $this->assertSame(1,$this->db->table('roomtype_asset_cleanup')->count());
    }
}
