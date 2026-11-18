<?php

namespace Tests\Feature;

use App\Services\BookingServiceV2;
use App\Services\BookingLifecycleServiceV2;
use App\Services\BookingEffectServiceV2;
use App\Services\VnpayPaymentServiceV2;
use App\Services\RoomTypeV2BackfillService;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2Integration;
use App\Support\RoomTypeV2TestDatabase;
use App\Support\VnpayProtocol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\RoomTypeV2ReadinessFixture;
use Tests\TestCase;

class RoomTypeV2PaymentReadinessTest extends TestCase
{
    private $db;
    private int $type;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); Mail::fake();
        $plan=RoomTypeV2ReadinessFixture::plan();
        try {
            $this->db=RoomTypeV2ReadinessFixture::initialize($plan,true);
            $hotelImagesBefore=$this->db->table('hotel_image')->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
            app(RoomTypeV2BackfillService::class)->run('mysql',$plan);
            $this->assertSame($hotelImagesBefore,$this->db->table('hotel_image')->orderBy('id')->get()->map(fn($row)=>(array)$row)->all());
        } catch (\Throwable $exception) {
            RoomTypeV2ReadinessFixture::cleanup();
            throw $exception;
        }
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
    }
    protected function tearDown(): void { RoomTypeV2ReadinessFixture::cleanup(); Carbon::setTestNow(); parent::tearDown(); }
    private function data(array $x=[]): array { return array_replace(['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>1,'num_guests'=>2,'check_in'=>'2026-10-15','check_out'=>'2026-10-17'],$x); }
    private function create(int $customer=9001): array { return app(BookingServiceV2::class)->create('mysql',$customer,$this->data()); }
    private function life(): BookingLifecycleServiceV2 { return app(BookingLifecycleServiceV2::class); }
    private function payment(array $b): array { return app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],$b['customer_id'],'127.0.0.1'); }
    private function query(array $p,array $x=[]): array
    {
        $q=array_replace(['vnp_TxnRef'=>$p['txn_ref'],'vnp_TmnCode'=>'TEST0001','vnp_Amount'=>(string)(int)str_replace('.','',$p['amount']),
            'vnp_ResponseCode'=>'00','vnp_TransactionStatus'=>'00','vnp_TransactionNo'=>(string)(100000+$p['payment_id']),'vnp_PayDate'=>'20261012150000'],$x);
        $q['vnp_SecureHash']=(new VnpayProtocol(config('services.vnpay')))->sign($q); return $q;
    }
    private function receive(array $q): array { return app(VnpayPaymentServiceV2::class)->receive('mysql',$q); }
    private function reject(callable $call,string $field): void { try { $call(); $this->fail('Expected validation error.'); } catch (ValidationException $e) { $this->assertArrayHasKey($field,$e->errors()); } }
    private function rows(string $table): array { return $this->db->table($table)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all(); }

    public function test_on_time_ipn_is_verified_atomic_and_idempotent(): void
    {
        $b=$this->create(); $p=$this->payment($b); $q=$this->query($p);
        $this->assertSame('success',$this->receive($q)['outcome']);
        $this->assertSame('confirmed',$this->db->table('booking')->value('status'));
        $this->assertTrue($this->receive($q)['duplicate']); $this->assertTrue($this->receive($q)['duplicate']);
        $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count()); $this->assertSame(1,$this->db->table('booking_effect')->count());
        $this->assertSame(0,$this->db->table('invoice')->count()); Mail::assertNothingSent();
    }
    public function test_cancelled_late_success_is_retained_for_reconciliation_without_overbooking(): void
    {
        $this->db->table('room_type')->where('id',$this->type)->update(['total_rooms'=>1]);
        $b=$this->create(); $p=$this->payment($b); $this->life()->cancel('mysql',$b['id'],9001); $this->create(9002);
        $r=$this->receive($this->query($p)); $this->assertSame('late_reconciliation',$r['outcome']);
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$b['id'])->value('status'));
        $this->assertSame('success',$this->db->table('payment')->where('id',$p['payment_id'])->value('payment_status'));
        $this->assertSame(0,$this->db->table('booking_effect')->count());
        $this->assertSame(1,$this->db->table('booking')->whereIn('status',['pending','confirmed','completed'])->count());
        $this->assertTrue($this->receive($this->query($p))['duplicate']);
    }
    public function test_expired_and_pending_past_deadline_never_auto_confirm(): void
    {
        $a=$this->create(); $p=$this->payment($a); $this->db->table('booking')->where('id',$a['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->assertSame('late_reconciliation',$this->receive($this->query($p))['outcome']);
        $this->assertSame('pending',$this->db->table('booking')->where('id',$a['id'])->value('status')); $this->life()->expire('mysql',$a['id']);
        $b=$this->create(9002); $p=$this->payment($b); $this->db->table('booking')->where('id',$b['id'])->update(['status'=>'expired']);
        $this->assertSame('late_reconciliation',$this->receive($this->query($p))['outcome']);
        $this->assertSame('expired',$this->db->table('booking')->where('id',$b['id'])->value('status'));
    }
    public function test_bad_signature_and_callback_shape_are_rejected_but_signed_amount_mismatch_is_reconciled(): void
    {
        $p=$this->payment($this->create()); $before=$this->rows('payment');
        $q=$this->query($p); $q['vnp_SecureHash']=str_repeat('0',128); $this->reject(fn()=>$this->receive($q),'signature');
        $this->assertSame('amount_mismatch_reconciliation',$this->receive($this->query($p,['vnp_Amount'=>'1']))['outcome']);
        $this->reject(fn()=>$this->receive($this->query($p,['vnp_TmnCode'=>'FAKE0001'])),'signature');
        $q=$this->query($p); $q['vnp_Amount']=['100']; $this->reject(fn()=>$this->receive($q),'signature');
        $this->reject(fn()=>$this->receive($this->query($p,['vnp_PayDate'=>'20260230150000'])),'callback');
        $this->assertSame($before,$this->rows('payment'));
        $this->assertSame('amount_mismatch_reconciliation',$this->db->table('payment_gateway_receipt')->value('outcome'));
        $this->assertSame('pending',$this->db->table('payment')->where('id',$p['payment_id'])->value('payment_status'));
    }
    public function test_transaction_failure_rolls_back_booking_payment_receipt_and_effect(): void
    {
        $p=$this->payment($this->create()); $booking=$this->rows('booking'); $payments=$this->rows('payment');
        $this->db->unprepared("CREATE TRIGGER fail_effect BEFORE INSERT ON booking_effect FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Effect test failure'");
        try { $this->receive($this->query($p)); $this->fail('Expected SQL error.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('Effect test failure',$e->getMessage()); }
        $this->assertSame($booking,$this->rows('booking')); $this->assertSame($payments,$this->rows('payment')); $this->assertSame(0,$this->db->table('payment_gateway_receipt')->count());
        $this->db->unprepared('DROP TRIGGER fail_effect'); $this->assertSame('success',$this->receive($this->query($p))['outcome']);
    }
    public function test_retry_reuses_pending_attempt_and_preserves_failed_attempt(): void
    {
        $b=$this->create(); $first=$this->payment($b); $this->assertSame($first,$this->payment($b));
        $this->receive($this->query($first,['vnp_ResponseCode'=>'24','vnp_TransactionStatus'=>'02','vnp_TransactionNo'=>'0']));
        $second=$this->payment($b); $this->assertNotSame($first['txn_ref'],$second['txn_ref']); $this->assertSame($second,$this->payment($b));
        $this->assertSame('failed',$this->db->table('payment')->where('id',$first['payment_id'])->value('payment_status'));
        $this->assertSame(1,$this->db->table('booking')->count()); $this->assertSame(1,$this->db->table('booking_room')->count());
        $this->life()->cancel('mysql',$b['id'],9001); $this->reject(fn()=>$this->payment($b),'booking_id');
    }
    public function test_url_matches_official_contract_and_return_url_is_read_only(): void
    {
        $p=$this->payment($this->create()); parse_str(parse_url($p['payment_url'],PHP_URL_QUERY),$fields);
        $this->assertSame('2.1.0',$fields['vnp_Version']); $this->assertSame('100000000',$fields['vnp_Amount']);
        $this->assertSame('20261012150000',$fields['vnp_CreateDate']); $this->assertSame('20261012151500',$fields['vnp_ExpireDate']);
        $this->assertSame((new VnpayProtocol(config('services.vnpay')))->sign($fields),$fields['vnp_SecureHash']);
        $before=$this->rows('payment'); $r=app(VnpayPaymentServiceV2::class)->result('mysql',$this->query($p));
        $this->assertSame('awaiting_ipn',$r['outcome']); $this->assertSame($before,$this->rows('payment')); $this->assertSame('pending',$this->db->table('booking')->value('status'));
    }
    public function test_payment_overlay_routes_are_bound_and_authorized_without_changing_defaults(): void
    {
        $audit=Artisan::call('route:list',['--json'=>true]); $this->assertSame(0,$audit);
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/legacy-route-list.json',Artisan::output());
        RoomTypeV2Integration::payments(); $this->app['router']->getRoutes()->refreshNameLookups(); $this->app['router']->getRoutes()->refreshActionLookups();
        foreach ($this->app['router']->getRoutes() as $route) if (str_contains($route->getActionName(),'PreparedV2\\PaymentController')) {
            [$class,$method]=explode('@',$route->getActionName()); $this->assertTrue(method_exists($class,$method));
        }
        $b=$this->create(); $this->postJson('/api/payments/retry',['booking_id'=>$b['id']])->assertUnauthorized();
        $this->actingAs(\App\Models\Customer::findOrFail(9002),'sanctum')->postJson('/api/payments/retry',['booking_id'=>$b['id']])->assertUnprocessable();
        $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum')->postJson('/api/payments/retry',[])->assertUnprocessable();
        $p=$this->postJson('/api/payments/create',['booking_id'=>$b['id']])->assertOk()->json();
        $matched=$this->app['router']->getRoutes()->match(\Illuminate\Http\Request::create('/api/payments/vnpay-return','GET'));
        $this->assertStringContainsString('PreparedV2',$matched->getActionName());
        $response=$this->getJson('/api/payments/vnpay-return?'.http_build_query($this->query($p)));
        $this->assertSame(200,$response->status(),$response->getContent()); $response->assertJsonPath('outcome','awaiting_ipn');
        $this->getJson('/api/payments/vnpay-ipn?'.http_build_query($this->query($p)))->assertOk()->assertJsonPath('RspCode','00');
        $this->getJson('/api/payments/vnpay-ipn?'.http_build_query($this->query($p)))->assertOk()->assertJsonPath('RspCode','02');
    }
    public function test_cancel_rebook_cancel_and_multiple_expired_histories_are_retained(): void
    {
        for ($i=0;$i<3;$i++) { $b=$this->create(); $this->life()->cancel('mysql',$b['id'],9001); }
        for ($i=0;$i<3;$i++) { $b=$this->create(); $this->db->table('booking')->where('id',$b['id'])->update(['status'=>'expired']); }
        $this->assertSame(6,$this->db->table('booking')->count()); $this->assertSame(6,$this->db->table('booking_room')->count());
        $this->assertSame(0,$this->db->table('booking')->whereNotNull('active_duplicate_key')->count());
    }
    public function test_active_duplicate_and_restore_collision_are_prevented_by_application_and_database(): void
    {
        $a=$this->create(); $this->reject(fn()=>$this->create(),'booking'); $this->life()->cancel('mysql',$a['id'],9001); $b=$this->create();
        $this->reject(fn()=>$this->life()->transition('mysql',$a['id'],'confirmed'),'status');
        try { $this->db->table('booking')->where('id',$a['id'])->update(['status'=>'confirmed']); $this->fail('Expected active constraint.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame(1062,$e->errorInfo[1]); }
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$a['id'])->value('status'));
    }
    public function test_unique_migration_down_restores_schema_only_when_history_is_representable(): void
    {
        $m=require base_path('database/migrations/2026_10_12_000005_replace_booking_status_unique.php'); $m->down(); $m->up();
        $a=$this->create(); $this->life()->cancel('mysql',$a['id'],9001); $b=$this->create(); $this->life()->cancel('mysql',$b['id'],9001);
        try { $m->down(); $this->fail('Rollback must refuse lossy history.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback refused',$e->getMessage()); }
        $this->assertSame(2,$this->db->table('booking')->count()); $this->assertTrue($this->db->getSchemaBuilder()->hasColumn('booking','active_duplicate_key'));
    }
    public function test_unique_expand_refuses_existing_cross_status_active_conflict_atomically(): void
    {
        $m=require base_path('database/migrations/2026_10_12_000005_replace_booking_status_unique.php'); $m->down();
        $b=$this->create(); $raw=$b; unset($raw['id']); $raw['status']='confirmed'; $this->db->table('booking')->insert($raw);
        try { $m->up(); $this->fail('Expected active preflight conflict.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('Conflicting active',$e->getMessage()); }
        $this->assertFalse($this->db->getSchemaBuilder()->hasColumn('booking','active_duplicate_key')); $this->assertSame(2,$this->db->table('booking')->count());
    }
    public function test_after_commit_effect_retry_keeps_one_invoice_and_stable_delivery_key(): void
    {
        $p=$this->payment($this->create()); $this->receive($this->query($p)); $id=$this->db->table('booking_effect')->value('id'); $keys=[];
        $sender=function ($event) use (&$keys) { $this->assertSame(0,$this->db->transactionLevel()); $keys[]=$event['event_key']; throw new \RuntimeException('Synthetic sender failure'); };
        try { app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender); $this->fail('Expected sender failure.'); } catch (\RuntimeException $e) { $this->assertSame('Synthetic sender failure',$e->getMessage()); }
        $sender=function ($event) use (&$keys) { $this->assertSame(0,$this->db->transactionLevel()); $keys[]=$event['event_key']; };
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender)); $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
        $this->assertSame($keys[0],$keys[1]); $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame(1,$this->db->table('invoice_item')->count());
        $this->assertSame(2,$this->db->table('booking_effect')->value('attempts')); $this->assertSame('1000000.00',$this->db->table('invoice')->value('total'));
    }
    public function test_effect_does_not_send_confirmation_after_booking_is_cancelled(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->receive($this->query($p)); $this->life()->cancel('mysql',$b['id'],9001);
        $called=0; $id=$this->db->table('booking_effect')->value('id');
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,function () use (&$called) { $called++; }));
        $this->assertSame(0,$called); $this->assertSame(0,$this->db->table('invoice')->count()); $this->assertSame('blocked',$this->db->table('booking_effect')->value('state'));
    }
    public function test_two_successful_attempts_are_retained_without_duplicate_invoice_or_effect(): void
    {
        $b=$this->create(); $a=$this->payment($b); $this->receive($this->query($a,['vnp_ResponseCode'=>'24','vnp_TransactionStatus'=>'02','vnp_TransactionNo'=>'0']));
        $p=$this->payment($b); $this->receive($this->query($p));
        $this->assertSame('duplicate_payment_reconciliation',$this->receive($this->query($a))['outcome']);
        $this->assertSame(1,$this->db->table('booking_effect')->count()); $this->assertSame(2,$this->db->table('payment')->where('payment_method','vnpay')->where('payment_status','success')->count());
        $this->assertSame(1,$this->db->table('booking')->count());
    }
    public function test_legacy_type_payment_uses_same_safe_writer_without_cutover(): void
    {
        $b=app(\App\Services\BookingService::class)->create(9001,$this->data(['room_type_id'=>1]));
        $p=$this->payment($b->getAttributes()); $this->life()->cancel('mysql',$b->id,9001);
        $this->assertSame('late_reconciliation',$this->receive($this->query($p))['outcome']);
        $this->assertSame('cancelled',$b->fresh()->status); $this->assertSame(1,$b->bookingRooms->first()->room_type_id);
    }

    private function contend(array $requests): array
    {
        $paths=[];$processes=[];$runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
        $this->db->beginTransaction(); $this->db->table('hotel')->where('id',1)->lockForUpdate()->first();
        try {
            foreach ($requests as $request) {
                $path=$runtime.'/ready-worker-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($path,json_encode($request)); $paths[]=$path;
                $p=new Process([PHP_BINARY,base_path('tests/Support/roomtype_v2_readiness_worker.php'),$path],base_path(),['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'AUREL_ROOMTYPE_V2_CASE_DATABASE'=>$this->db->getDatabaseName()]); $p->setTimeout(35); $p->start();$processes[]=$p;
            }
            $deadline=microtime(true)+15;
            do { $n=(int)array_values((array)$this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'"))[1]; if ($n>=2) break;
                foreach($processes as $p) if (!$p->isRunning()) throw new \RuntimeException($p->getErrorOutput()); usleep(20000);
            } while(microtime(true)<$deadline);
            $this->assertGreaterThanOrEqual(2,$n);
        } catch (\Throwable $e) { foreach($processes as $p) if ($p->isRunning()) $p->stop(1); throw $e; }
        finally { $this->db->commit(); }
        $results=[];
        try { foreach($processes as $i=>$p) { $p->wait();$this->assertSame(0,$p->getExitCode(),$p->getErrorOutput());$results[]=json_decode(file_get_contents($paths[$i].'.result'),true); } }
        finally { foreach($processes as $p) if($p->isRunning()) $p->stop(1); }
        $this->assertCount(2,array_unique(array_column($results,'connection_id')));
        file_put_contents($runtime.'/readiness-concurrency-'.bin2hex(random_bytes(6)).'.json',json_encode(['database'=>$this->db->getDatabaseName(),'waiters'=>$n,'requests'=>$requests,'results'=>$results],JSON_PRETTY_PRINT)); return $results;
    }
    public function test_concurrent_duplicate_create_is_rejected_even_when_stock_is_sufficient(): void
    {
        $r=$this->contend([['action'=>'create','customer_id'=>9001,'data'=>$this->data()],['action'=>'create','customer_id'=>9001,'data'=>$this->data()]]);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['success']))); $this->assertSame(1,$this->db->table('booking')->count());
    }
    public function test_verified_ipn_competes_with_cancel_without_restoring(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->contend([['action'=>'cancel','id'=>$b['id'],'customer_id'=>9001],['action'=>'ipn','query'=>$this->query($p)]]);
        $this->assertSame('cancelled',$this->db->table('booking')->value('status')); $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count());
    }
    public function test_verified_ipn_competes_with_expiry_rechecking_both_rows(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->contend([['action'=>'expire','id'=>$b['id']],['action'=>'ipn','query'=>$this->query($p)]]);
        $this->assertSame('cancelled',$this->db->table('booking')->value('status')); $this->assertSame('late_reconciliation',$this->db->table('payment_gateway_receipt')->value('outcome'));
    }
    public function test_verified_ipn_competes_with_admin_restore_under_inventory_locks(): void
    {
        $b=$this->create(); $p=$this->payment($b); $this->life()->cancel('mysql',$b['id'],9001);
        $this->contend([['action'=>'restore','id'=>$b['id']],['action'=>'ipn','query'=>$this->query($p)]]);
        $this->assertSame('confirmed',$this->db->table('booking')->value('status')); $this->assertSame(1,$this->db->table('payment_gateway_receipt')->count());
        $this->assertSame(1,$this->db->table('booking_room')->count());
    }
    public function test_primary_database_refused_before_routes_or_payment_connection_open(): void
    {
        config(['database.connections.mysql.database'=>'hotel_booking']); DB::shouldReceive('connection')->never();
        foreach ([fn()=>RoomTypeV2Integration::payments(),fn()=>$this->receive([]),fn()=>app(BookingEffectServiceV2::class)->deliver('mysql',1,fn()=>null)] as $call) {
            try { $call(); $this->fail('Expected preconnection guard.'); } catch (\RuntimeException $e) { $this->assertStringContainsString('refused before',$e->getMessage()); }
        }
    }
}
