<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\HotelRoomType;
use App\Services\BookingService;
use App\Support\AccommodationImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class HotelManagementRequirementsTest extends TestCase
{
    private $db;
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = HotelRoomTypeFixture::initialize();
        HotelRoomTypeFixture::migrate();
        Storage::fake('public'); Mail::fake(); $this->withoutVite();
    }
    private function admin(): void
    {
        $user = Customer::findOrFail(1);
        $user->withAccessToken(new PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($user, 'admin');
    }
    private function url(int $hotel = 1, int $type = 1): string { return "/api/admin/hotels/$hotel/room-types/$type"; }
    private function booking(string $status, ?int $room = null): int
    {
        $id = $this->db->table('booking')->insertGetId(['customer_id'=>2,'hotel_id'=>1,'check_in'=>'2020-01-01',
            'check_out'=>'2020-01-03','num_guests'=>2,'total_price'=>'1600000.50','status'=>$status]);
        $this->db->table('booking_room')->insert(['booking_id'=>$id,'room_type_id'=>1,'room_id'=>$room,'quantity'=>1,'nights'=>2,'price_at_booking'=>'800000.25']);
        return $id;
    }
    public function test_missing_legacy_cover_uses_uploaded_image_in_every_read_and_preserves_primary_choice(): void
    {
        $this->admin();
        $record = HotelRoomType::where('hotel_id',1)->first();
        $this->db->table('hotel_room_type_image')->insert(['hotel_room_type_id'=>$record->id,'image_url'=>'image/missing-demo.jpg','is_primary'=>true]);
        $this->post($this->url(), ['_method'=>'PUT','images'=>[UploadedFile::fake()->image('first.jpg'),UploadedFile::fake()->image('second.jpg')]], ['Accept'=>'application/json'])->assertOk();
        $gallery = $this->getJson($this->url().'/images')->assertOk()->json('images');
        $expected = $gallery[1]['display_url'];
        $this->assertNotSame(AccommodationImage::placeholder(), $expected);
        $this->getJson('/api/admin/hotels/1/room-types?inventory_model=auto')->assertJsonPath('room_types.0.cover_image_url',$expected);
        $this->getJson($this->url())->assertJsonPath('room_type.cover_image_url',$expected);
        $this->getJson('/api/hotels/1/rooms')->assertJsonPath('0.cover_image_url',$expected);
        $this->get('/hotels/1')->assertOk()->assertSee($expected, false);
        $this->putJson($this->url(),['image_changes'=>['primary_id'=>$gallery[2]['id']]])->assertOk();
        $this->getJson($this->url())->assertJsonPath('room_type.cover_image_url',$gallery[2]['display_url']);
        $this->getJson('/api/hotels/1/rooms')->assertJsonPath('0.cover_image_url',$gallery[2]['display_url']);
        $this->getJson($this->url(2))->assertJsonPath('room_type.cover_image_url',AccommodationImage::placeholder());
        $this->putJson($this->url(),['image_changes'=>['delete_ids'=>array_column($gallery,'id')]])->assertOk();
        $this->getJson($this->url())->assertJsonPath('room_type.cover_image_url',AccommodationImage::placeholder());
    }
    public function test_deleting_unbooked_type_retires_inventory_without_deleting_physical_rooms_or_other_hotel(): void
    {
        $this->admin();
        $rooms = $this->db->table('room')->count();
        $this->db->table('room_availability')->insert(['hotel_id'=>1,'room_type_id'=>1,'date'=>now()->addDay()->toDateString(),'total_rooms'=>5,'available_count'=>5]);
        $this->deleteJson($this->url())->assertOk()->assertJsonPath('archived',true);
        $this->assertSame($rooms,$this->db->table('room')->count());
        $this->assertSame(5,$this->db->table('room')->where('hotel_id',1)->where('catalog_retired',true)->count());
        $this->getJson('/api/admin/hotels/1/room-types?inventory_model=auto')->assertJsonCount(0,'room_types');
        $this->getJson('/api/hotels/1/rooms')->assertJsonCount(0);
        $this->getJson('/api/hotels/2/rooms')->assertJsonCount(5);
        $this->getJson($this->url())->assertNotFound();
        $this->putJson($this->url(),['status'=>'active'])->assertNotFound();
        $this->assertSame(0,$this->db->table('room_availability')->value('available_count'));
    }
    public function test_empty_unused_catalog_entry_can_be_hard_deleted_while_shared_legacy_type_survives(): void
    {
        $this->admin();
        $this->db->table('hotel_room_type')->insert(['hotel_id'=>3,'room_type_id'=>1,'name'=>'Empty','base_price'=>1,'capacity'=>1,'amenities'=>'[]']);
        $this->deleteJson($this->url(3))->assertOk()->assertJsonPath('archived',false);
        $this->assertSame(1,$this->db->table('room_type')->count());
        $this->getJson($this->url(2))->assertOk();
    }
    public function test_completed_history_and_images_survive_archival(): void
    {
        $this->admin(); $id=$this->booking('completed',$this->db->table('room')->where('hotel_id',1)->value('id'));
        $before=$this->db->table('booking_room')->get()->toArray();
        $this->deleteJson($this->url())->assertOk()->assertJsonPath('archived',true);
        $this->assertEquals($before,$this->db->table('booking_room')->get()->toArray());
        $this->assertSame('completed',$this->db->table('booking')->where('id',$id)->value('status'));
        $this->assertSame('Deluxe',app(BookingService::class)->getDetail($id,2)->bookingRooms[0]->roomType->name);
    }
    public function test_unfinished_booking_blocks_deletion_even_after_checkout_date_and_allows_stop_selling(): void
    {
        $this->admin(); $this->booking('confirmed');
        $response=$this->deleteJson($this->url())->assertStatus(409);
        $this->assertStringContainsString('Ngừng bán',$response->json('message'));
        $this->assertSame('active',$this->db->table('hotel_room_type')->where('hotel_id',1)->value('status'));
        $this->putJson($this->url(),['status'=>'inactive'])->assertOk();
        $this->assertSame(1,$this->db->table('booking')->count());
    }
    public function test_delete_checks_auth_hotel_pair_and_physical_room_locks(): void
    {
        $this->deleteJson($this->url())->assertUnauthorized();
        $this->actingAs(Customer::findOrFail(2),'admin')->deleteJson($this->url())->assertForbidden();
        $this->admin();$this->deleteJson($this->url(3))->assertNotFound();$this->deleteJson($this->url(1,999))->assertNotFound();
        $this->db->table('room')->where('hotel_id',1)->limit(1)->update(['status'=>'locked']);
        $this->deleteJson($this->url())->assertStatus(409);
        $this->assertSame(2,$this->db->table('hotel_room_type')->count());
    }
    public function test_custom_amenities_are_normalized_persistent_isolated_and_validated(): void
    {
        $this->admin();
        $this->db->table('hotel_amenity')->insert(['hotel_id'=>2,'amenity'=>'Shared lounge']);
        $this->putJson($this->url(),['amenities'=>['Private balcony']])->assertOk();
        $this->putJson($this->url(),['amenities'=>[' Desk ', 'desk']])->assertUnprocessable();
        $this->putJson('/api/admin/hotels/1',['amenities'=>['WiFi',"  Shared\t lounge  ",'airport']])->assertOk();
        $this->getJson('/api/admin/hotels/1')->assertJsonPath('hotel.amenities.0.amenity','wifi')->assertJsonPath('hotel.amenities.1.amenity','Shared lounge')->assertJsonPath('hotel.amenities.2.amenity','airport_shuttle');
        $this->get('/hotels/1')->assertSee('Shared lounge')->assertSee('WiFi')->assertDontSee('Phòng gym');
        $this->putJson('/api/admin/hotels/1',['amenities'=>['wifi','shared LOUNGE','airport_shuttle']])->assertOk();
        $this->assertSame(3,$this->db->table('hotel_amenity')->where('hotel_id',1)->count());
        foreach ([['Shared lounge',' shared  LOUNGE '],['wifi','WiFi'],['  '],["\u{00a0}"]] as $bad) $this->putJson('/api/admin/hotels/1',['amenities'=>$bad])->assertUnprocessable();
        $this->putJson('/api/admin/hotels/1',['phone'=>'123'])->assertOk();
        $this->assertSame(3,$this->db->table('hotel_amenity')->where('hotel_id',1)->count());
        $this->putJson('/api/admin/hotels/1',['amenities'=>[]])->assertOk();
        $this->assertSame('Shared lounge',$this->db->table('hotel_amenity')->where('hotel_id',2)->value('amenity'));
        $this->getJson($this->url())->assertJsonPath('room_type.amenities.0','Private balcony');
    }
    public function test_shared_file_with_absolute_or_encoded_link_survives_gallery_removal(): void
    {
        $this->admin();
        $this->post($this->url(),['_method'=>'PUT','images'=>[UploadedFile::fake()->image('shared.jpg')]],['Accept'=>'application/json'])->assertOk();
        $image=$this->getJson($this->url().'/images')->json('images.0');
        $shared='http://old-local-host.test/public/'.str_replace('accommodation','%61ccommodation',$image['image_url']);
        $this->db->table('hotel_image')->insert(['hotel_id'=>2,'image_url'=>$shared,'is_primary'=>true]);
        $this->putJson($this->url(),['image_changes'=>['delete_ids'=>[$image['id']]]])->assertOk();
        Storage::disk('public')->assertExists(substr($image['image_url'],8));
        $this->assertSame($shared,$this->db->table('hotel_image')->value('image_url'));
    }
    public function test_future_inventory_hold_blocks_deletion_without_removing_the_hold(): void
    {
        $this->admin();
        $this->db->table('room_availability')->insert(['hotel_id'=>1,'room_type_id'=>1,'date'=>now()->addDay()->toDateString(),'total_rooms'=>5,'available_count'=>4,'booked_count'=>1]);
        $this->deleteJson($this->url())->assertStatus(409);
        $this->assertSame(1,$this->db->table('room_availability')->value('booked_count'));
        $this->assertSame(0,$this->db->table('room')->where('catalog_retired',true)->count());
    }
    public function test_hotel_save_ignores_retired_policy_times_while_booking_dates_and_min_price_still_work(): void
    {
        $this->admin();
        $before=$this->db->table('hotel')->where('id',1)->first();
        $this->putJson('/api/admin/hotels/1',['check_in_time'=>'invalid','check_out_time'=>'','phone'=>'123'])->assertOk();
        $after=$this->db->table('hotel')->where('id',1)->first();
        $this->assertSame($before->check_in_time,$after->check_in_time);$this->assertSame($before->check_out_time,$after->check_out_time);
        $this->postJson('/api/admin/hotels',['name'=>'No policy fields','location_id'=>1,'address'=>'Test','star_rating'=>3])->assertCreated();
        foreach (['/admin/hotels','/admin/hotels/1','/hotels/1'] as $page) $this->get($page)->assertOk()->assertDontSee('type="time"',false)->assertDontSee('Chính sách lưu trú')->assertDontSee('Giờ nhận phòng')->assertDontSee('Giờ trả phòng');
        $in=now()->addDays(5)->toDateString();$out=now()->addDays(7)->toDateString();
        $this->getJson("/api/hotels/1/rooms?check_in=$in&check_out=$out")->assertOk()->assertJsonPath('0.available_rooms',5);
        $this->getJson("/api/hotels/1/rooms?check_in=$out&check_out=$in")->assertUnprocessable();
        $booking=app(BookingService::class)->create(2,['hotel_id'=>1,'room_type_id'=>1,'quantity'=>1,'num_guests'=>2,'check_in'=>$in,'check_out'=>$out,'payment_method'=>'cash']);
        $this->assertSame(2,$booking->bookingRooms[0]->nights);
        $this->assertEquals('1600000.50',$booking->total_price);
        $this->getJson('/api/hotels')->assertJsonPath('data.0.rooms_min_price','800000.25');
    }
}
