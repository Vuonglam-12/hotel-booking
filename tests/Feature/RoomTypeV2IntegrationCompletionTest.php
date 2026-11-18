<?php

namespace Tests\Feature;

use App\Services\BookingServiceV2;
use App\Services\BookingLifecycleServiceV2;
use App\Services\BookingEffectServiceV2;
use App\Services\VnpayPaymentServiceV2;
use App\Services\RoomTypeV2BackfillService;
use App\Services\RoomTypeV2RemapService;
use App\Services\RoomTypeAdminServiceV2;
use App\Services\PreparedV2\BookingMailSender;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2Integration;
use App\Support\VnpayProtocol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Tests\Support\RoomTypeV2ReadinessFixture;
use Tests\Support\RoomTypeV2CutoverFixture;
use Tests\TestCase;

class RoomTypeV2IntegrationCompletionTest extends TestCase
{
    private $db;
    private int $type;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->withoutVite();
        \Tests\Support\CustomerAccountTestAssets::activate();
        $this->db=RoomTypeV2ReadinessFixture::initialize(RoomTypeV2Plan::prepared()); RoomTypeV2CutoverFixture::presentation();
        app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $path=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/remap-'.bin2hex(random_bytes(6)).'.json';
        app(RoomTypeV2RemapService::class)->prepare('mysql',RoomTypeV2Plan::prepared(),$path);
        app(RoomTypeV2RemapService::class)->run('mysql',$path);
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function enable(): void
    {
        config(['roomtype-v2.prepared_routes'=>true,'roomtype-v2.integration_complete'=>true]);
        $this->app->register(\App\Providers\RoomTypeV2PreparedProvider::class);
    }
    private function data(array $changes=[]): array { return array_replace(['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>2,'num_guests'=>4,'check_in'=>'2026-10-15','check_out'=>'2026-10-17'],$changes); }
    private function create(): array { return app(BookingServiceV2::class)->create('mysql',9001,$this->data()); }
    private function query(array $p): array
    {
        $q=['vnp_TxnRef'=>$p['txn_ref'],'vnp_TmnCode'=>'TEST0001','vnp_Amount'=>(string)(int)str_replace('.','',$p['amount']),
            'vnp_ResponseCode'=>'00','vnp_TransactionStatus'=>'00','vnp_TransactionNo'=>(string)(100000+$p['payment_id']),'vnp_PayDate'=>'20261012150000'];
        $q['vnp_SecureHash']=(new VnpayProtocol(config('services.vnpay')))->sign($q); return $q;
    }
    private function payment(array $b): array { return app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],9001,'127.0.0.1'); }
    private function refuse(callable $call): void
    {
        try { $call(); $this->fail('Expected write fence refusal'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('writer refused',$e->getMessage()); }
    }
    public function test_overlay_replaces_routes_without_normalized_duplicates_and_keeps_default_off(): void
    {
        $this->assertFalse(config('roomtype-v2.integration_complete')); $this->enable();
        $seen=[];
        foreach (app('router')->getRoutes() as $route) {
            $key=implode('|',$route->methods()).' '.preg_replace('/\{[^}]+\}/','{}',$route->uri());
            $this->assertArrayNotHasKey($key,$seen); $seen[$key]=true;
        }
        foreach ([['GET','/hotels/1'],['GET','/payment/result'],['POST','/api/bookings'],['PUT','/api/admin/bookings/1/status'],
            ['POST','/api/admin/hotels/1/room-types'],['POST','/api/payments/manual'],['GET','/api/payments/vnpay-ipn']] as [$method,$url]) {
            $route=app('router')->getRoutes()->match(\Illuminate\Http\Request::create($url,$method));
            $this->assertStringContainsString('PreparedV2\\',$route->getActionName());
        }
        $this->assertSame(0,Artisan::call('route:list',['--json'=>true]));
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/complete-route-list.json',Artisan::output());
        $this->assertInstanceOf(\App\Services\PreparedV2\BookingBridge::class,app(\App\Services\BookingService::class));
    }
    public function test_legacy_raw_bulk_eloquent_and_duplicate_service_writes_are_fenced(): void
    {
        $b=$this->create(); $this->enable();
        $this->refuse(fn()=>$this->db->table('booking')->where('id',$b['id'])->update(['status'=>'confirmed']));
        $this->refuse(fn()=>\App\Models\Booking::whereKey($b['id'])->update(['status'=>'confirmed']));
        $this->refuse(fn()=>app(\App\Services\Admin\BookingService::class)->updateStatus($b['id'],'confirmed'));
        require_once app_path('Services/PaymentService.php'); // Legacy namespace/path mismatch; not autoloadable through PSR-4.
        $this->refuse(fn()=>app(\App\Services\Payment\PaymentService::class)->createManual(9001,$b['id'],'banking'));
        $this->refuse(fn()=>$this->db->statement('UPDATE `room_type` SET total_rooms=0 WHERE id=?',[$this->type]));
        $this->assertSame('pending',$this->db->table('booking')->value('status'));
        $this->assertSame(0,$this->db->table('invoice')->count());
    }
    public function test_legacy_web_callback_is_fenced_even_if_invoked_directly(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable();
        require_once app_path('Http/Controllers/Webcontroller.php');
        $response=app(\App\Http\Controllers\WebController::class)->paymentResult(\Illuminate\Http\Request::create('/payment/result','GET',$this->query($p)));
        $this->assertSame(503,$response->getStatusCode());
        $this->assertSame('pending',$this->db->table('booking')->value('status'));
        $this->assertSame('pending',$this->db->table('payment')->value('payment_status'));
        $this->assertSame(0,$this->db->table('payment_gateway_receipt')->count());
    }
    public function test_container_bridge_create_cancel_uses_v2_without_physical_room_id(): void
    {
        $this->enable(); $bridge=app(\App\Services\BookingService::class);
        $booking=$bridge->create(9001,$this->data()); $this->assertNull($booking->bookingRooms->first()->room_id);
        $this->assertSame(2,$booking->bookingRooms->first()->quantity);
        $this->assertSame('cancelled',$bridge->cancel($booking->id,9001)->status);
        $this->assertSame(336,$this->db->table('room')->count());
    }
    public function test_existing_scheduler_name_uses_v2_expiry_and_is_idempotent(): void
    {
        $b=$this->create(); $this->enable(); Carbon::setTestNow('2026-10-12 08:16:00');
        $this->assertSame(0,Artisan::call('bookings:cancel-expired'));
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$b['id'])->value('status'));
        $this->assertSame(0,Artisan::call('bookings:cancel-expired')); $this->assertSame('0',trim(Artisan::output()));
        $this->assertSame(0,$this->db->table('room_availability')->count());
        $this->assertInstanceOf(\App\Console\Commands\PreparedV2CancelExpiredBookings::class,app(\App\Console\Commands\CancelExpiredBookings::class));
        $commands=array_values(array_filter(array_map(fn($event)=>$event->command,app(\Illuminate\Console\Scheduling\Schedule::class)->events()),
            fn($command)=>str_contains($command, 'bookings:')));
        $this->assertCount(2,$commands); $this->assertStringContainsString('bookings:v2-deliver-prepared',$commands[1]);
    }
    public function test_html_detail_has_owned_type_price_image_and_checkout_stays_disabled(): void
    {
        $this->enable(); $queries=[];
        $this->db->listen(function ($q) use (&$queries) { $queries[]=$q->sql; });
        $response=$this->get('/hotels/1')->assertOk();
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/prepared-hotel-detail.html',$response->getContent());
        $response->assertSee('500000.00',false)->assertSee('/room-types?',false)->assertSee('min="1" max="10"',false);
        $this->assertMatchesRegularExpression('/id="submit-booking-btn"[^>]*disabled/s',$response->getContent());
        $this->assertStringNotContainsString('LEG-1',$response->getContent());
        $this->assertFalse((bool)array_filter($queries,fn($q)=>preg_match('/\bfrom\s+`room`/i',$q)),'Detail must never query physical Room rows');
        $this->getJson('/api/hotels/1/room-types?check_in=2026-10-15&check_out=2026-10-17&quantity=2&num_guests=4')->assertOk()->assertJsonPath('room_types.0.available_rooms',5);
    }
    public function test_html_return_is_read_only_and_mail_status_is_truthful(): void
    {
        $b=$this->create(); $p=$this->payment($b); $q=$this->query($p); $this->enable();
        $url='/payment/result?'.http_build_query($q);
        $this->get($url)->assertOk()->assertSee('data-payment-state="awaiting_ipn"',false);
        $this->assertSame('pending',$this->db->table('booking')->value('status')); $this->assertSame(0,$this->db->table('booking_effect')->count());
        $this->getJson('/api/payments/vnpay-ipn?'.http_build_query($q))->assertOk()->assertJsonPath('RspCode','00');
        $this->get($url)->assertOk()->assertSee('Email xác nhận đang chờ gửi.');
        $this->assertSame(0,Artisan::call('bookings:v2-deliver-prepared'));
        $this->get($url)->assertOk()->assertSee('Email xác nhận đã được gửi.');
        $this->assertSame(1,$this->db->table('invoice')->count());
    }
    public function test_late_success_html_requires_reconciliation_without_confirmation_or_mail(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable();
        app(BookingLifecycleServiceV2::class)->cancel('mysql',$b['id'],9001);
        app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p));
        $this->get('/payment/result?'.http_build_query($this->query($p)))->assertOk()->assertSee('data-payment-state="late_reconciliation"',false);
        $this->assertSame('cancelled',$this->db->table('booking')->value('status')); $this->assertSame(0,$this->db->table('booking_effect')->count());
    }
    public function test_real_mail_blade_pdf_pipeline_is_after_commit_and_repeat_safe_in_memory(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable(); app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p));
        $id=$this->db->table('booking_effect')->value('id');
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,app(BookingMailSender::class)));
        $messages=Mail::mailer('array')->getSymfonyTransport()->messages(); $this->assertCount(1,$messages);
        $message=$messages->first()->getOriginalMessage(); $this->assertStringContainsString('2',$message->getHtmlBody());
        $this->assertCount(1,$message->getAttachments()); $this->assertSame('%PDF-',substr($message->getAttachments()[0]->getBody(),0,5));
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,app(BookingMailSender::class)));
        $this->assertCount(1,Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum')->getJson('/api/invoices/'.$b['id'])->assertOk()->assertJsonPath('invoice.total','2000000.00');
        $invoiceBefore=$this->db->table('invoice')->first();
        $this->get('/api/invoices/'.$b['id'].'/pdf')->assertOk()->assertHeader('content-type','application/pdf');
        $this->assertEquals($invoiceBefore,$this->db->table('invoice')->first());
    }
    public function test_sender_failure_retry_reuses_invoice_and_pending_effect(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable(); app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p));
        $id=$this->db->table('booking_effect')->value('id');
        try { app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=>throw new \RuntimeException('Synthetic before-send fault')); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic before-send fault',$e->getMessage()); }
        $this->assertSame('pending',$this->db->table('booking_effect')->value('state'));
        $this->assertSame(0,Artisan::call('bookings:v2-deliver-prepared')); $this->assertSame(0,Artisan::call('bookings:v2-deliver-prepared'));
        $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame(2,$this->db->table('booking_effect')->value('attempts'));
    }
    public function test_missing_credentials_and_non_array_sender_fail_without_fake_success(): void
    {
        $b=$this->create(); $this->enable(); config(['services.vnpay'=>[]]);
        try { $this->payment($b); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('Missing verified VNPAY',$e->getMessage()); }
        $this->assertSame('pending',$this->db->table('booking')->value('status')); $this->assertSame(0,$this->db->table('booking_effect')->count());
        config(['mail.mailers.array.transport'=>'smtp']);
        try { app(BookingMailSender::class)(['booking_id'=>$b['id'],'event_key'=>'test']); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('array mail transport',$e->getMessage()); }
    }
    public function test_complete_activation_refuses_main_before_opening_connection(): void
    {
        config(['database.connections.mysql.database'=>'hotel_booking']); DB::shouldReceive('connection')->never();
        try { RoomTypeV2Integration::complete(); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('refused before',$e->getMessage()); }
    }
    public function test_signed_redelivery_with_additional_metadata_remains_idempotent(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable();
        $q=$this->query($p); app(VnpayPaymentServiceV2::class)->receive('mysql',$q);
        $q['vnp_BankCode']='NCB'; $q['vnp_BankTranNo']='SYNTHETIC_BANK_REF';
        $q['vnp_SecureHash']=(new VnpayProtocol(config('services.vnpay')))->sign($q);
        $this->getJson('/api/payments/vnpay-ipn?'.http_build_query($q))->assertOk()->assertJsonPath('RspCode','02');
        $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count()); $this->assertSame(1,$this->db->table('booking_effect')->count());
        $q['vnp_TransactionNo']='999999999'; $q['vnp_SecureHash']=(new VnpayProtocol(config('services.vnpay')))->sign($q);
        $this->getJson('/api/payments/vnpay-ipn?'.http_build_query($q))->assertOk()->assertJsonPath('RspCode','99');
        $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count());
    }
    public function test_complete_admin_form_quantity_and_snapshot_contracts_with_authorization(): void
    {
        $this->enable(); $page=$this->get('/admin/hotels')->assertOk()->assertSee('id="rtCount"',false)->assertSee('id="rtPrice"',false);
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/prepared-admin-hotels.html',$page->getContent());
        $this->postJson('/api/admin/hotels/1/room-types',[])->assertUnauthorized();
        $admin=\App\Models\Customer::findOrFail(9004); $admin->role='admin';
        $admin->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($admin,'admin');
        $id=$this->postJson('/api/admin/hotels/1/room-types',['name'=>'Prepared quantity fixture','capacity'=>2,'base_price'=>'777777.13','total_rooms'=>10,'status'=>'active'])->assertCreated()->json('room_type.id');
        $rooms=$this->db->table('room')->count();
        $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum');
        for ($q=1;$q<=10;$q++) {
            $in=Carbon::parse('2030-01-01')->addDays($q*3)->toDateString(); $out=Carbon::parse($in)->addDays(2)->toDateString();
            $this->postJson('/api/bookings',$this->data(['room_type_id'=>$id,'quantity'=>$q,'num_guests'=>$q*2,'check_in'=>$in,'check_out'=>$out]))->assertCreated()
                ->assertJsonPath('booking.booking_rooms.0.room_id',null)->assertJsonPath('booking.booking_rooms.0.quantity',$q);
        }
        $this->actingAs($admin,'admin');
        $this->putJson('/api/admin/hotels/1/room-types/'.$id,['base_price'=>'999999.99','total_rooms'=>9])->assertUnprocessable();
        $this->putJson('/api/admin/hotels/1/room-types/'.$id,['base_price'=>'999999.99'])->assertOk();
        $this->assertSame('777777.13',$this->db->table('booking_room')->where('room_type_id',$id)->value('price_at_booking'));
        $this->assertSame($rooms,$this->db->table('room')->count());
        $this->getJson('/api/admin/hotels?per_page=30')->assertOk()->assertJsonPath('data.20.rooms_count',26);
        $this->getJson('/api/admin/rooms/availability?hotel_id=1&room_type_id='.$id.'&check_in=2030-02-01&check_out=2030-02-03&quantity=1&num_guests=2')->assertOk();
        $this->deleteJson('/api/admin/hotels/1/room-types/'.$id)->assertOk();
        $this->getJson('/api/admin/rooms/availability?hotel_id=1&room_type_id='.$id.'&check_in=2030-02-01&check_out=2030-02-03&quantity=1&num_guests=2')->assertUnprocessable();
        $this->getJson('/api/hotels/1/rooms')->assertStatus(409); $this->get('/booking/1')->assertStatus(409);
    }
    public function test_complete_login_logout_keeps_other_actor_token_valid(): void
    {
        $this->db->table('customer')->where('id',9004)->update(['role'=>'admin','password_hash'=>\Illuminate\Support\Facades\Hash::make('synthetic-password')]);
        $this->db->table('customer')->where('id',9001)->update(['password_hash'=>\Illuminate\Support\Facades\Hash::make('synthetic-password')]);
        $this->enable();
        $a=$this->postJson('/api/admin/login',['email'=>'fixture-9004@example.test','password'=>'synthetic-password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards(); $c=$this->postJson('/api/login',['email'=>'fixture-9001@example.test','password'=>'synthetic-password'])->assertOk()->json('token');
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$c])->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$a])->getJson('/api/admin/dashboard')->assertOk();
        $this->app['auth']->forgetGuards(); $this->withHeaders(['Authorization'=>'Bearer '.$a])->postJson('/api/admin/logout')->assertOk();
    }

    private function contend(array $requests): array
    {
        $paths=[]; $processes=[]; $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
        $this->db->beginTransaction(); $this->db->table('hotel')->where('id',1)->lockForUpdate()->first();
        try {
            foreach ($requests as $request) {
                $path=$runtime.'/ready-worker-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($path,json_encode($request)); $paths[]=$path;
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/roomtype_v2_integration_worker.php'),$path],base_path(),
                    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'AUREL_ROOMTYPE_V2_CASE_DATABASE'=>$this->db->getDatabaseName()]);
                $p->setTimeout(40); $p->start(); $processes[]=$p;
            }
            $deadline=microtime(true)+15; $n=0;
            do {
                $n=(int)array_values((array)$this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'"))[1];
                if ($n>=2) break;
                foreach ($processes as $p) if (!$p->isRunning()) throw new \RuntimeException($p->getErrorOutput());
                usleep(20000);
            } while (microtime(true)<$deadline);
            $this->assertGreaterThanOrEqual(2,$n,'Both independent connections must actually wait on Hotel');
        } catch (\Throwable $e) { foreach ($processes as $p) if ($p->isRunning()) $p->stop(1); throw $e; }
        finally { $this->db->commit(); }
        $results=[];
        try {
            foreach ($processes as $i=>$p) { $p->wait(); $this->assertSame(0,$p->getExitCode(),$p->getErrorOutput()); $results[]=json_decode(file_get_contents($paths[$i].'.result'),true); }
        } finally { foreach ($processes as $p) if ($p->isRunning()) $p->stop(1); }
        $this->assertCount(2,array_unique(array_column($results,'connection_id')));
        file_put_contents($runtime.'/b4-concurrency-'.bin2hex(random_bytes(6)).'.json',json_encode(['database'=>$this->db->getDatabaseName(),'waiters'=>$n,'results'=>$results],JSON_PRETTY_PRINT));
        return $results;
    }
    public function test_complete_wiring_concurrent_duplicate_ipn_commits_one_receipt_and_effect(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable();
        $r=$this->contend([['action'=>'ipn','query'=>$this->query($p)],['action'=>'ipn','query'=>$this->query($p)]]);
        $this->assertTrue($r[0]['success']); $this->assertTrue($r[1]['success']);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['result']['duplicate'])));
        $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count()); $this->assertSame(1,$this->db->table('booking_effect')->count());
    }
    public function test_complete_wiring_two_delivery_workers_claim_only_one_effect(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->enable(); app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p));
        $id=$this->db->table('booking_effect')->value('id');
        $r=$this->contend([['action'=>'deliver','id'=>$id],['action'=>'deliver','id'=>$id]]);
        $this->assertTrue($r[0]['success']); $this->assertTrue($r[1]['success']);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['result']===true)));
        $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame(1,$this->db->table('booking_effect')->value('attempts'));
        $this->assertSame('delivered',$this->db->table('booking_effect')->value('state'));
    }
}
