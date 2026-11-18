<?php

namespace Tests\Feature;

use App\Services\DemoBookingPaymentService;
use App\Support\BookingDemo;
use App\Support\RoomTypeV2Integration;
use Tests\Support\RoomTypeV2AcceptanceFixture;
use Tests\TestCase;
use Symfony\Component\Process\Process;

final class BookingDemoTest extends TestCase
{
    private $db;private int $type;private string $token;
    protected function setUp(): void
    {
        parent::setUp();$this->withoutVite();$this->db=RoomTypeV2AcceptanceFixture::initialize();
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $this->db->table('room_type')->where('id',$this->type)->update(['total_rooms'=>1]);
        RoomTypeV2Integration::acceptance();config(['booking-demo.enabled'=>true]);BookingDemo::activate();
        $this->token=\App\Models\Customer::findOrFail(9001)->createToken('customer_token')->plainTextToken;
    }
    private function data(array $extra=[]): array {return $extra+['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>1,'num_guests'=>2,'check_in'=>today()->addDays(3)->toDateString(),'check_out'=>today()->addDays(5)->toDateString(),'payment_method'=>'banking'];}
    private function actor(string $token): static { $this->app['auth']->forgetGuards();return $this->withToken($token); }
    private function create(array $extra=[]): int {return $this->actor($this->token)->postJson('/api/bookings',$this->data($extra))->assertCreated()->json('booking.id');}
    private function simulate(int $id,string $action){return $this->actor($this->token)->postJson('/api/demo/bookings/'.$id.'/simulate',['action'=>$action]);}

    public function test_pending_snapshot_ignores_client_prices_and_does_not_create_an_invoice(): void
    {
        $id=$this->create(['price'=>1,'total_price'=>1,'amount'=>1]);$b=$this->db->table('booking')->find($id);$p=$this->db->table('payment')->where('booking_id',$id)->first();
        $price=$this->db->table('room_type')->where('id',$this->type)->value('base_price');
        $this->assertSame(number_format((float)$price*2,2,'.',''),$b->total_price);$this->assertSame('pending',$b->status);$this->assertSame('pending',$p->payment_status);
        $this->assertStringStartsWith('DEMO_',$p->transaction_id);$this->assertSame($b->total_price,$p->amount);$this->assertSame(0,$this->db->table('invoice')->where('booking_id',$id)->count());
        $this->actor($this->token)->postJson('/api/bookings',$this->data())->assertUnprocessable();
    }
    public function test_success_is_idempotent_and_creates_one_invoice_notification_and_event(): void
    {
        $id=$this->create();$this->simulate($id,'success')->assertOk()->assertJsonPath('outcome','success');$this->simulate($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $this->assertSame('confirmed',$this->db->table('booking')->find($id)->status);
        foreach (['invoice','payment_gateway_receipt','booking_effect'] as $table)$this->assertSame(1,$this->db->table($table)->where($table==='payment_gateway_receipt'?'provider':'booking_id',$table==='payment_gateway_receipt'?'demo':$id)->count());
        $this->assertSame('delivered',$this->db->table('booking_effect')->where('booking_id',$id)->value('state'));
        $this->simulate($id,'cancelled')->assertConflict();
        $this->actor($this->token)->get('/api/invoices/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringContainsString('HÓA ĐƠN MÔ PHỎNG',view('prepared-v2.invoice-pdf',['booking'=>\App\Models\Booking::with('hotel','customer','bookingRooms.roomType')->find($id),'invoice'=>\App\Models\Invoice::with('items')->where('booking_id',$id)->first()])->render());
    }
    public function test_failure_requires_retry_and_preserves_attempt_history(): void
    {
        $id=$this->create();$this->simulate($id,'failed')->assertOk();$this->simulate($id,'success')->assertConflict();
        $this->actor($this->token)->postJson('/api/demo/bookings/'.$id.'/retry')->assertOk();$this->actor($this->token)->postJson('/api/demo/bookings/'.$id.'/retry')->assertOk()->assertJsonPath('duplicate',true);
        $this->simulate($id,'success')->assertOk();$this->assertSame(2,$this->db->table('payment')->where('booking_id',$id)->count());
        $this->assertSame(1,$this->db->table('invoice')->where('booking_id',$id)->count());
    }
    public function test_cancel_and_expire_release_inventory_without_an_invoice(): void
    {
        foreach (['cancelled','expired'] as $action){$id=$this->create();$this->simulate($id,$action)->assertOk();$this->simulate($id,$action)->assertOk()->assertJsonPath('duplicate',true);
            $this->assertSame('cancelled',$this->db->table('booking')->find($id)->status);$this->assertSame(0,$this->db->table('invoice')->where('booking_id',$id)->count());$this->simulate($id,'success')->assertConflict();}
    }
    public function test_failed_attempt_can_expire_automatically_before_the_next_booking(): void
    {
        $id=$this->create();$this->simulate($id,'failed')->assertOk();\Carbon\Carbon::setTestNow(now()->addMinutes(16));
        try{app(DemoBookingPaymentService::class)->expireDue();$this->assertSame('cancelled',$this->db->table('booking')->find($id)->status);$this->assertSame('expired',$this->db->table('payment')->where('booking_id',$id)->value('payment_status'));$this->create();}finally{\Carbon\Carbon::setTestNow();}
    }
    public function test_deadline_cannot_be_overridden_by_success(): void
    {
        $id=$this->create();\Carbon\Carbon::setTestNow(now()->addMinutes(16));try{$this->simulate($id,'success')->assertOk()->assertJsonPath('outcome','expired');$this->assertSame(0,$this->db->table('invoice')->where('booking_id',$id)->count());}finally{\Carbon\Carbon::setTestNow();}
    }
    public function test_ownership_admin_token_and_disabled_mode_are_enforced(): void
    {
        $id=$this->create();$other=\App\Models\Customer::find(9002)->createToken('customer_token')->plainTextToken;
        $this->actor($other)->getJson('/api/demo/bookings/'.$id)->assertNotFound();$this->actor($other)->postJson('/api/demo/bookings/'.$id.'/simulate',['action'=>'success'])->assertNotFound();
        $this->actor($this->token)->getJson('/api/admin/demo/bookings')->assertForbidden();
        $admin=\App\Models\Customer::find(9004)->createToken('admin_token',['admin'])->plainTextToken;
        $this->actor($admin)->getJson('/api/admin/demo/bookings')->assertOk()->assertJsonPath('data.0.id',$id);
        config(['booking-demo.enabled'=>false]);$this->actor($this->token)->postJson('/api/demo/bookings/'.$id.'/simulate',['action'=>'success'])->assertNotFound();$this->assertSame('pending',$this->db->table('booking')->find($id)->status);
    }
    public function test_demo_never_exposes_bank_accounts_or_gateway_calls(): void
    {
        $id=$this->create();$this->actor($this->token)->getJson('/api/demo/bookings/'.$id)->assertOk()->assertJsonPath('demo',true)->assertJsonMissingPath('banking_instructions');
        foreach (['/api/payments/create','/api/payments/retry','/api/payments/manual','/api/webhooks/sepay'] as $path)$this->actor($this->token)->postJson($path,['booking_id'=>$id])->assertForbidden();
        $this->get('/payments/bookings/'.$id)->assertOk()->assertSee('QR minh họa')->assertDontSee('1234567890');
        $this->assertSame('array',config('mail.default'));$this->assertNull(config('roomtype-v2.banking_instructions'));
    }
    public function test_concurrent_buyers_cannot_oversell_the_last_room(): void
    {
        $payload=json_encode($this->data());$workers=[];
        foreach ([9002,9003] as $customer){$p=new Process([PHP_BINARY,base_path('tests/Support/booking_demo_worker.php'),$this->db->getDatabaseName(),(string)$customer,$payload],base_path(),['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')]);$p->setTimeout(20);$p->start();$workers[]=$p;}
        $codes=[];foreach ($workers as $p){$p->wait();$codes[]=$p->getExitCode();$this->assertContains($p->getExitCode(),[0,3],$p->getErrorOutput());}
        sort($codes);$this->assertSame([0,3],$codes);$this->assertSame(1,$this->db->table('booking')->whereIn('customer_id',[9002,9003])->where('status','pending')->count());
    }
    public function test_concurrent_success_callbacks_create_only_one_financial_effect(): void
    {
        $id=$this->create();$workers=[];
        foreach ([1,2] as $ignored){$p=new Process([PHP_BINARY,base_path('tests/Support/booking_demo_worker.php'),$this->db->getDatabaseName(),'9001',(string)$id,'success'],base_path(),['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')]);$p->setTimeout(40);$p->start();$workers[]=$p;}
        foreach($workers as $p){$p->wait();$this->assertSame(0,$p->getExitCode(),$p->getErrorOutput());}
        $this->assertSame(1,$this->db->table('payment_gateway_receipt')->where('provider','demo')->count());
        $this->assertSame(1,$this->db->table('invoice')->where('booking_id',$id)->count());
        $this->assertSame(1,$this->db->table('booking_effect')->where('booking_id',$id)->count());
        $this->assertSame('delivered',$this->db->table('booking_effect')->where('booking_id',$id)->value('state'));
    }
    public function test_demo_guard_refuses_production_and_the_primary_target_before_connecting(): void
    {
        $this->app->instance('env','production');
        try { BookingDemo::connection();$this->fail('Production must be refused.'); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(404,$e->getStatusCode());}
        finally{$this->app->instance('env','testing');}
        $original=config('database.connections.mysql');
        config(['database.connections.mysql.port'=>3306,'database.connections.mysql.database'=>'hotel_booking']);
        try{BookingDemo::connection();$this->fail('Primary target must be refused before DB::connection.');}
        catch(\RuntimeException $e){$this->assertStringContainsString('refused before opening a connection',$e->getMessage());}
        finally{config(['database.connections.mysql'=>$original]);}
    }
}
