<?php

namespace Tests\Feature;

use App\Services\RoomTypeV2BackfillService;
use App\Services\RoomTypeV2RemapService;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2Integration;
use App\Support\RoomTypeV2TestDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\RoomTypeV2ReadinessFixture;
use Tests\TestCase;

class RoomTypeV2ApiReadinessTest extends TestCase
{
    private $db;
    private int $type;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->db=RoomTypeV2ReadinessFixture::initialize();
        app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        RoomTypeV2Integration::backend();
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function customer(): void { $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum'); }
    private function admin(): void
    {
        $admin=\App\Models\Customer::findOrFail(9004); $admin->role='admin';
        $admin->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']])); $this->actingAs($admin,'admin');
    }
    private function data(array $x=[]): array { return array_replace(['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>1,'num_guests'=>2,'check_in'=>'2026-10-15','check_out'=>'2026-10-17'],$x); }
    private function input(array $x=[]): array { return array_replace(['name'=>'Synthetic new type','base_price'=>'777777.13','capacity'=>3,'total_rooms'=>10,'status'=>'active','bed_type'=>'Twin','description'=>'Synthetic description'],$x); }

    public function test_public_contract_has_only_owned_types_images_and_date_inventory(): void
    {
        $a=$this->getJson('/api/hotels/1/room-types')->assertOk()->json('room_types');
        $this->assertCount(5,$a); foreach($a as $type) { $this->assertSame(1,$type['hotel_id']); $this->assertArrayNotHasKey('room_number',$type); $this->assertNull($type['available_rooms']); $this->assertFalse($type['bookable']); }
        $this->getJson('/api/hotels/1/room-types?check_in=2026-10-15&check_out=2026-10-17&quantity=2&num_guests=4')->assertOk()->assertJsonPath('room_types.0.available_rooms',5)->assertJsonPath('room_types.0.bookable',true);
        $this->getJson('/api/hotels/1/room-types?check_in=2026-10-15&check_out=2026-10-17&num_guests=99')->assertOk()->assertJsonPath('room_types.0.bookable',false);
        $this->assertCount(1,$a[0]['images']); $this->assertSame('500000.00',$a[0]['base_price']);
    }
    public function test_public_invalid_inputs_missing_dates_and_inactive_are_clear(): void
    {
        foreach (['quantity=0','quantity=11','num_guests=0','check_in=2026-10-15','check_out=2026-10-17','check_in=2026-02-30&check_out=2026-03-02'] as $query) $this->getJson('/api/hotels/1/room-types?'.$query)->assertUnprocessable();
        $this->db->table('room_type')->where('id',$this->type)->update(['status'=>'inactive']);
        $this->getJson('/api/hotels/1/room-types')->assertOk()->assertJsonCount(4,'room_types');
        $this->db->table('hotel')->where('id',1)->update(['status'=>'inactive']); $this->getJson('/api/hotels/1/room-types')->assertNotFound();
        $this->getJson('/api/hotels/9999/room-types')->assertNotFound();
    }
    public function test_api_create_one_to_ten_and_foreign_type_is_rejected(): void
    {
        $this->customer(); $this->admin(); $this->putJson('/api/admin/hotels/1/room-types/'.$this->type,['total_rooms'=>10])->assertOk();
        $this->customer();
        for($q=1;$q<=10;$q++) {
            $in=Carbon::parse('2026-11-01')->addDays($q*3)->toDateString(); $out=Carbon::parse($in)->addDays(2)->toDateString();
            $b=$this->postJson('/api/bookings',$this->data(['quantity'=>$q,'num_guests'=>$q*2,'check_in'=>$in,'check_out'=>$out]))->assertCreated()->json('booking');
            $line=$this->db->table('booking_room')->where('booking_id',$b['id'])->first(); $this->assertNull($line->room_id); $this->assertSame($q,$line->quantity);
        }
        $other=$this->db->table('room_type_legacy_map')->where('hotel_id',2)->where('old_room_type_id',1)->value('new_room_type_id');
        $this->postJson('/api/bookings',$this->data(['room_type_id'=>$other]))->assertUnprocessable();
        $this->postJson('/api/bookings',$this->data(['room_id'=>1]))->assertUnprocessable();
    }
    public function test_admin_create_edits_status_without_physical_rooms_or_snapshot_changes(): void
    {
        $this->admin(); $rooms=$this->db->table('room')->count(); $id=$this->postJson('/api/admin/hotels/1/room-types',$this->input())->assertCreated()->json('room_type.id');
        $this->assertSame($rooms,$this->db->table('room')->count()); $this->customer();
        $b=$this->postJson('/api/bookings',$this->data(['room_type_id'=>$id,'quantity'=>2,'num_guests'=>6]))->assertCreated()->json('booking');
        $this->assertSame('3111108.52',$b['total_price']);
        $this->admin(); $this->putJson('/api/admin/hotels/1/room-types/'.$id,['name'=>'Synthetic edit','base_price'=>'999999.99','capacity'=>2,'description'=>null])->assertOk();
        $this->assertSame('777777.13',$this->db->table('booking_room')->where('booking_id',$b['id'])->value('price_at_booking'));
        $this->putJson('/api/admin/hotels/1/room-types/'.$id,['hotel_id'=>2])->assertUnprocessable();
        foreach ([null,'',[]] as $owner) $this->putJson('/api/admin/hotels/1/room-types/'.$id,['hotel_id'=>$owner])->assertUnprocessable();
        $this->assertSame(1,$this->db->table('room_type')->where('id',$id)->value('hotel_id'));
        $this->deleteJson('/api/admin/hotels/1/room-types/'.$id)->assertOk()->assertJsonPath('room_type.status','inactive');
        $this->assertTrue($this->db->table('room_type')->where('id',$id)->exists()); $this->assertSame(1,$this->db->table('booking')->count());
    }
    public function test_admin_stock_update_is_atomic_and_protects_ongoing_far_future(): void
    {
        $this->customer(); $this->postJson('/api/bookings',$this->data(['quantity'=>4,'check_in'=>'2035-01-01','check_out'=>'2035-01-03']))->assertCreated();
        $this->admin(); $name=$this->db->table('room_type')->where('id',$this->type)->value('name');
        $this->putJson('/api/admin/hotels/1/room-types/'.$this->type,['name'=>'Should rollback','total_rooms'=>3])->assertUnprocessable();
        $this->assertSame($name,$this->db->table('room_type')->where('id',$this->type)->value('name'));
        $this->putJson('/api/admin/hotels/1/room-types/'.$this->type,['total_rooms'=>4])->assertOk();
        $this->customer(); $this->postJson('/api/bookings',$this->data(['quantity'=>5,'check_in'=>'2035-01-01','check_out'=>'2035-01-03']))->assertUnprocessable();
    }
    public function test_gallery_is_type_scoped_deduplicated_and_keeps_original_files(): void
    {
        $this->admin(); $source=$this->db->table('room_image')->count(); $prefix='/api/admin/hotels/1/room-types/'.$this->type.'/images';
        $this->postJson($prefix,['image_url'=>'image/img_room/room (1).jpg','is_primary'=>true])->assertOk()->assertJsonCount(1,'images');
        $this->postJson($prefix,['image_url'=>'image/img_room/room (2).jpg','is_primary'=>true])->assertOk()->assertJsonCount(2,'images');
        $this->assertSame(1,$this->db->table('room_type_image')->where('room_type_id',$this->type)->where('is_primary',true)->count());
        $foreign=$this->db->table('room_type_image')->where('room_type_id','!=',$this->type)->value('id');
        $this->deleteJson($prefix.'/'.$foreign)->assertUnprocessable();
        $this->postJson($prefix,['image_url'=>'https://example.invalid/fake.jpg'])->assertUnprocessable();
        $this->assertSame($source,$this->db->table('room_image')->count());
    }
    public function test_admin_authorization_reuses_existing_role_and_session_ability(): void
    {
        $this->postJson('/api/admin/hotels/1/room-types',$this->input())->assertUnauthorized();
        $user=\App\Models\Customer::findOrFail(9001); $this->actingAs($user,'admin');
        $this->postJson('/api/admin/hotels/1/room-types',$this->input())->assertForbidden();
        $user->role='admin'; $this->actingAs($user,'admin'); $this->postJson('/api/admin/hotels/1/room-types',$this->input())->assertForbidden();
        $this->admin(); $this->postJson('/api/admin/hotels/2/room-types/'.$this->type.'/images',['image_url'=>'image/img_room/room (1).jpg'])->assertUnprocessable();
    }
    public function test_sanctum_real_synthetic_login_logout_preserves_admin_and_customer_sessions(): void
    {
        $this->db->table('customer')->where('id',9004)->update(['role'=>'admin','password_hash'=>Hash::make('synthetic-password')]);
        $this->db->table('customer')->where('id',9001)->update(['password_hash'=>Hash::make('synthetic-password')]);
        $this->app['auth']->forgetGuards();
        $admin=$this->postJson('/api/admin/login',['email'=>'fixture-9004@example.test','password'=>'synthetic-password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards(); $customer=$this->postJson('/api/login',['email'=>'fixture-9001@example.test','password'=>'synthetic-password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/register',['name'=>'Synthetic registered customer','email'=>'registered@example.test','password'=>'synthetic-password','role'=>'admin'])
            ->assertCreated()->assertJsonPath('user.role','user');
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$customer])->postJson('/api/admin/hotels/1/room-types',$this->input())->assertForbidden();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$admin])->getJson('/api/admin/me')->assertOk();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$customer])->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$admin])->getJson('/api/admin/me')->assertOk();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$admin])->postJson('/api/admin/logout')->assertOk();
    }
    public function test_remap_history_adapter_keeps_legacy_room_and_new_type_quantities(): void
    {
        $this->customer(); $b=app(\App\Services\BookingService::class)->create(9001,$this->data(['room_type_id'=>1,'quantity'=>2]));
        $this->db->table('booking_room')->where('booking_id',$b->id)->update(['room_id'=>null]);
        $path=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/remap-'.bin2hex(random_bytes(6)).'.json';
        $service=app(RoomTypeV2RemapService::class); $service->prepare('mysql',RoomTypeV2Plan::prepared(),$path); $service->run('mysql',$path);
        $r=$this->getJson('/api/bookings/'.$b->id)->assertOk()->json('booking'); $this->assertEquals('2000000.00',$r['total_price']);
        $this->assertSame(2,$r['booking_rooms'][0]['quantity']); $this->assertNull($r['booking_rooms'][0]['room_id']);
        $this->assertSame($this->type,$r['booking_rooms'][0]['room_type_id']); $this->assertSame(0,$service->run('mysql',$path)['remapped_rooms']);
    }
    public function test_guarded_provider_defaults_off_and_requires_schema_before_activation(): void
    {
        $this->assertFalse(config('roomtype-v2.prepared_routes'));
        config(['roomtype-v2.prepared_routes'=>true]); $this->app->register(\App\Providers\RoomTypeV2PreparedProvider::class);
        $this->getJson('/api/hotels/1/room-types')->assertOk();
        config(['database.connections.mysql.database'=>'hotel_booking']); DB::shouldReceive('connection')->never();
        try { RoomTypeV2Integration::backend(); $this->fail('Primary must be refused.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('refused before',$e->getMessage()); }
    }
    public function test_prepared_expiry_command_releases_stock_and_repeats_safely(): void
    {
        $this->customer(); $b=$this->postJson('/api/bookings',$this->data())->assertCreated()->json('booking');
        $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->assertSame(0,Artisan::call('bookings:v2-expire-prepared')); $this->assertSame('cancelled',$this->db->table('booking')->value('status'));
        $this->assertSame(0,Artisan::call('bookings:v2-expire-prepared'));
        $this->assertSame(0,$this->db->table('room_availability')->count());
    }
}
