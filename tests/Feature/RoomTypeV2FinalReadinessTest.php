<?php

namespace Tests\Feature;

use App\Services\BookingServiceV2;
use App\Services\BookingLifecycleServiceV2;
use App\Services\ManualBankingPaymentServiceV2;
use App\Services\VnpayPaymentServiceV2;
use App\Services\BookingEffectServiceV2;
use App\Services\RoomTypeAdminServiceV2;
use App\Services\HotelCatalogServiceV2;
use App\Support\RoomTypeV2Integration;
use App\Support\VnpayProtocol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\RoomTypeV2FinalFixture;
use Tests\TestCase;

class RoomTypeV2FinalReadinessTest extends TestCase
{
    private $db; private int $type;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->withoutVite();
        $this->db=RoomTypeV2FinalFixture::initialize();
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function enable(): void { RoomTypeV2Integration::finalize(); }
    private function data(array $x=[]): array { return array_replace(['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>2,'num_guests'=>4,'check_in'=>'2026-10-15','check_out'=>'2026-10-17','payment_method'=>'banking'],$x); }
    private function create(int $customer=9001,array $x=[]): array { return app(BookingServiceV2::class)->create('mysql',$customer,$this->data($x)); }
    private function p(array $b): object { return $this->db->table('payment')->where('booking_id',$b['id'])->where('payment_method','banking')->first(); }
    private function verification(array $x=[]): array { return array_replace(['reference'=>'TEST_BANK_REF_001','amount'=>'2000000.00','received_at'=>'2026-10-12T08:00:00+00:00','funds_received'=>true,'evidence_source'=>'bank_statement'],$x); }
    private function verify(array $b,array $x=[],int $admin=9004): array { return app(ManualBankingPaymentServiceV2::class)->verify('mysql',$b['id'],$this->p($b)->id,$admin,$this->verification($x)); }
    private function reject(callable $call,string $field): void
    {
        try { $call(); $this->fail('Expected validation error: '.$field); }
        catch (ValidationException $e) { $this->assertArrayHasKey($field,$e->errors()); }
    }
    private function admin(): void
    {
        $a=\App\Models\Customer::findOrFail(9004); $a->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']])); $this->actingAs($a,'admin');
    }
    private function customer(int $id=9001): void { $this->actingAs(\App\Models\Customer::findOrFail($id),'sanctum'); }
    private function query(array $payment,array $x=[]): array
    {
        $q=array_replace(['vnp_TxnRef'=>$payment['txn_ref'],'vnp_TmnCode'=>'TEST0001','vnp_Amount'=>(string)(int)str_replace('.','',$payment['amount']),
            'vnp_ResponseCode'=>'00','vnp_TransactionStatus'=>'00','vnp_TransactionNo'=>(string)(100000+$payment['payment_id']),'vnp_PayDate'=>'20261012150000'],$x);
        $q['vnp_SecureHash']=(new VnpayProtocol(config('services.vnpay')))->sign($q); return $q;
    }
    public function test_banking_creation_and_customer_acknowledgement_never_verify_or_extend(): void
    {
        $this->enable(); $this->customer(); $b=$this->postJson('/api/bookings',$this->data(['total_price'=>'1','price'=>'1']))->assertCreated()->json('booking');
        $this->assertSame('pending',$b['status']); $this->assertSame('pending',$b['payment']['payment_status']); $this->assertSame('2000000.00',$b['total_price']);
        $expiry=$this->db->table('booking')->value('expires_at');
        for ($i=0;$i<2;$i++) $this->postJson('/api/payments/manual',['booking_id'=>$b['id'],'payment_method'=>'banking','payment_status'=>'success'])->assertOk()->assertJsonPath('booking.status','pending');
        $this->assertSame($expiry,$this->db->table('booking')->value('expires_at')); $this->assertSame(1,$this->db->table('payment')->count());
        $this->assertSame(0,$this->db->table('manual_payment_verification')->count()); $this->assertSame(0,$this->db->table('booking_effect')->count());
        $this->assertSame('2026-10-12 08:15:00',$expiry);
        $this->postJson('/api/bookings',$this->data(['payment_method'=>'cash']))->assertUnprocessable();
        $this->reject(fn()=>app(BookingLifecycleServiceV2::class)->manualPayment('mysql',$b['id'],9001,'cash'),'payment');
    }
    public function test_admin_verification_is_atomic_audited_and_idempotent(): void
    {
        $this->enable(); $b=$this->create(); $this->assertSame('success',$this->verify($b)['outcome']); $this->assertTrue($this->verify($b)['duplicate']);
        $this->assertSame('confirmed',$this->db->table('booking')->value('status')); $this->assertSame('success',$this->p($b)->payment_status);
        $audit=$this->db->table('manual_payment_verification')->first(); $this->assertSame(9004,$audit->verified_by); $this->assertSame('TEST_BANK_REF_001',$audit->bank_reference);
        $this->assertSame('pending',$audit->booking_status_before); $this->assertSame('confirmed',$audit->booking_status_after);
        $this->assertSame(1,$this->db->table('booking_effect')->count()); $this->assertSame(0,$this->db->table('invoice')->count());
        $this->assertSame(2,$this->db->table('booking_state_audit')->count());
        $this->assertSame('2026-10-12 08:00:00',$this->db->table('booking_state_audit')->value('created_at'));
    }
    public function test_admin_auth_role_ability_and_payment_booking_ownership(): void
    {
        $this->enable(); $b=$this->create(); $url='/api/admin/bookings/'.$b['id'].'/payments/'.$this->p($b)->id.'/verify-banking';
        $this->postJson($url,$this->verification())->assertUnauthorized();
        $user=\App\Models\Customer::findOrFail(9001); $this->actingAs($user,'admin'); $this->postJson($url,$this->verification())->assertForbidden();
        $this->admin(); $this->postJson($url,$this->verification())->assertOk();
        $other=$this->create(9002); $this->postJson('/api/admin/bookings/'.$other['id'].'/payments/'.$this->p($b)->id.'/verify-banking',$this->verification())->assertUnprocessable();
        $this->reject(fn()=>$this->verify($other,[],9001),'admin');
    }
    public function test_wrong_amount_reference_evidence_and_receipt_time_never_confirm(): void
    {
        $this->enable(); $b=$this->create();
        foreach ([['amount'=>'1.00','field'=>'amount'],['reference'=>'SCREEN SHOT','field'=>'reference'],['evidence_source'=>'customer_screenshot','field'=>'evidence_source'],
            ['funds_received'=>false,'field'=>'funds_received'],['received_at'=>'2026-10-13T08:00:00+00:00','field'=>'received_at']] as $x) {
            $field=$x['field']; unset($x['field']); $this->reject(fn()=>$this->verify($b,$x),$field);
        }
        $this->assertSame('pending',$this->p($b)->payment_status); $this->assertSame(0,$this->db->table('booking_effect')->count());
        $this->verify($b); $this->reject(fn()=>$this->verify($b,['reference'=>'OTHER_REFERENCE']),'reference');
        $other=$this->create(9002); $this->reject(fn()=>$this->verify($other),'reference'); $this->assertSame('pending',$this->p($other)->payment_status);
    }
    public function test_late_banking_after_expiry_retains_funds_without_restore_even_if_paid_earlier(): void
    {
        $this->enable(); $b=$this->create(); Carbon::setTestNow('2026-10-12 08:16:00');
        $this->assertTrue(app(BookingLifecycleServiceV2::class)->expire('mysql',$b['id']));
        $r=$this->verify($b); $this->assertSame('late_reconciliation',$r['outcome']); $this->assertSame('cancelled',$r['booking_status']);
        $this->assertSame('success',$this->p($b)->payment_status); $this->assertSame(0,$this->db->table('booking_effect')->count());
        $this->customer(); $this->getJson('/api/payments/'.$b['id'])->assertOk()->assertJsonPath('payment.display_status','reconciliation');
    }
    public function test_pending_past_deadline_and_cancelled_banking_never_auto_restore(): void
    {
        $this->enable(); $b=$this->create(); Carbon::setTestNow('2026-10-12 08:16:00');
        $this->assertSame('late_reconciliation',$this->verify($b)['outcome']); $this->assertSame('pending',$this->db->table('booking')->value('status'));
        app(BookingLifecycleServiceV2::class)->expire('mysql',$b['id']);
        Carbon::setTestNow('2026-10-12 08:00:00'); $other=$this->create(9002); app(BookingLifecycleServiceV2::class)->cancel('mysql',$other['id'],9002);
        $this->assertSame('late_reconciliation',$this->verify($other,['reference'=>'LATE_BANK_002'])['outcome']);
        $this->assertSame(0,$this->db->table('booking_effect')->count());
    }
    public function test_vnpay_then_banking_double_payment_retains_both_and_one_confirmation(): void
    {
        $this->enable(); $b=$this->create(); $p=app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],9001,'127.0.0.1');
        app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p));
        $this->assertSame('duplicate_payment_reconciliation',$this->verify($b)['outcome']);
        $this->assertSame(2,$this->db->table('payment')->where('payment_status','success')->count()); $this->assertSame(1,$this->db->table('booking_effect')->count());
    }
    public function test_banking_then_vnpay_double_payment_preserves_receipt_without_second_effect(): void
    {
        $this->enable(); $b=$this->create(); $p=app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],9001,'127.0.0.1'); $this->verify($b);
        $this->assertSame('duplicate_payment_reconciliation',app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p))['outcome']);
        $this->assertSame(1,$this->db->table('booking_effect')->count()); $this->assertSame(1,$this->db->table('manual_payment_verification')->count());
    }
    public function test_vnpay_signature_amount_duplicate_and_late_callback_contract(): void
    {
        $this->enable(); $b=$this->create(9001,['payment_method'=>'vnpay']); $p=app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],9001,'127.0.0.1');
        $q=$this->query($p); $bad=$q; $bad['vnp_SecureHash']=str_repeat('0',128);
        $this->reject(fn()=>app(VnpayPaymentServiceV2::class)->receive('mysql',$bad),'signature');
        $this->reject(fn()=>app(VnpayPaymentServiceV2::class)->receive('mysql',$this->query($p,['vnp_Amount'=>'1'])),'amount');
        app(BookingLifecycleServiceV2::class)->cancel('mysql',$b['id'],9001);
        $this->assertSame('late_reconciliation',app(VnpayPaymentServiceV2::class)->receive('mysql',$q)['outcome']);
        $this->assertTrue(app(VnpayPaymentServiceV2::class)->receive('mysql',$q)['duplicate']);
        $this->assertSame(0,$this->db->table('booking_effect')->count());
    }
    public function test_admin_status_override_and_unsigned_prototype_cannot_bypass_payment(): void
    {
        $this->enable(); $b=$this->create(); $this->admin();
        foreach (['confirmed','pending','completed'] as $status) {
            if ($status==='pending') continue;
            $this->putJson('/api/admin/bookings/'.$b['id'].'/status',['status'=>$status])->assertUnprocessable();
        }
        $p=$this->p($b); $this->reject(fn()=>app(BookingLifecycleServiceV2::class)->verifiedPaymentSuccess('mysql',$b['id'],$p->id,$p->transaction_id,$p->amount),'payment');
        $this->verify($b); $this->putJson('/api/admin/bookings/'.$b['id'].'/status',['status'=>'completed'])->assertOk();
        $this->assertSame(3,$this->db->table('booking_state_audit')->count());
    }
    public function test_quantity_one_to_ten_and_foreign_or_physical_type_and_oversell(): void
    {
        $this->enable(); app(RoomTypeAdminServiceV2::class)->save('mysql',1,$this->type,['total_rooms'=>10]); $this->customer();
        for ($q=1;$q<=10;$q++) {
            $in=Carbon::parse('2030-01-01')->addDays($q*3)->toDateString(); $out=Carbon::parse($in)->addDays(2)->toDateString();
            $b=$this->postJson('/api/bookings',$this->data(['quantity'=>$q,'num_guests'=>$q*2,'check_in'=>$in,'check_out'=>$out]))->assertCreated()->json('booking');
            $this->assertSame($q,$b['summary']['lines'][0]['quantity']); $this->assertSame(2,$b['summary']['lines'][0]['nights']); $this->assertNull($b['booking_rooms'][0]['room_id']);
        }
        $this->postJson('/api/bookings',$this->data(['room_id'=>1]))->assertUnprocessable();
        $foreign=$this->db->table('room_type_legacy_map')->where('hotel_id',2)->where('old_room_type_id',1)->value('new_room_type_id');
        $this->postJson('/api/bookings',$this->data(['room_type_id'=>$foreign]))->assertUnprocessable();
        $this->postJson('/api/bookings',$this->data(['num_guests'=>5]))->assertUnprocessable();
        $b=$this->create(9002,['quantity'=>10,'num_guests'=>20]); $this->postJson('/api/bookings',$this->data())->assertUnprocessable();
        $this->assertSame(336,$this->db->table('room')->count());
    }
    public function test_all_catalog_consumers_share_price_without_room_queries(): void
    {
        $this->db->table('hotel')->where('id',1)->update(['star_rating'=>5,'avg_rating'=>'4.8']);
        $this->enable(); $queries=[]; $this->db->listen(function ($q) use (&$queries) { $queries[]=$q->sql; });
        app(RoomTypeAdminServiceV2::class)->save('mysql',1,$this->type,['base_price'=>'650000.00']);
        $this->getJson('/api/hotels?search=Sofitel')->assertOk()->assertJsonPath('data.0.rooms_min_price','650000.00');
        $this->getJson('/api/hotels/1')->assertOk()->assertJsonPath('rooms_min_price','650000.00');
        $this->getJson('/api/hotels?search=Sofitel&max_price=600000')->assertOk()->assertJsonCount(0,'data');
        $map=$this->getJson('/api/hotels/map?city=Isolated&lat=0&lng=0&radius=10')->assertOk()->json();
        $this->assertSame('650000.00',collect($map)->firstWhere('id',1)['rooms_min_price']);
        $this->getJson('/api/locations')->assertOk();
        $this->get('/?search=Sofitel')->assertOk()->assertSee('650.000',false);
        $this->get('/deals')->assertOk()->assertViewHas('featured',fn($rows)=>$rows->firstWhere('id',1)?->rooms_min_price==='650000.00');
        $this->get('/hotels/1')->assertOk()->assertSee('650000.00',false);
        $service=app(\App\Services\Hotel\HotelService::class); $this->assertSame('650000.00',$service->getDetail(1)->minPrice());
        $service->getList(['max_price'=>600000]); $service->getRooms(1,['check_in'=>'2026-10-15','check_out'=>'2026-10-17']); $service->getLocations();
        $this->assertFalse((bool)array_filter($queries,fn($q)=>preg_match('/\b(from|join)\s+`?(room|v_map_hotels)`?(?=\s|$)/i',$q)),'Active catalog must never query physical Room or legacy map view');
    }
    public function test_inactive_missing_dates_and_unmapped_or_negative_inventory_fail_closed(): void
    {
        $this->enable(); app(RoomTypeAdminServiceV2::class)->save('mysql',1,$this->type,['status'=>'inactive']);
        $this->getJson('/api/hotels/1/room-types')->assertOk()->assertJsonCount(4,'room_types');
        $this->getJson('/api/hotels/1')->assertOk()->assertJsonPath('rooms_min_price','800000.00');
        $this->getJson('/api/hotels/1/room-types?check_in=2026-10-15')->assertUnprocessable();
        $this->getJson('/api/hotels/1/room-types')->assertOk()->assertJsonPath('room_types.0.available_rooms',null);
        $this->getJson('/api/hotels/2/room-types')->assertNotFound();
    }
    public function test_history_admin_payment_and_pdf_read_snapshots_without_physical_room(): void
    {
        $this->enable(); $b=$this->create(); $this->verify($b);
        app(RoomTypeAdminServiceV2::class)->save('mysql',1,$this->type,['base_price'=>'999999.99']);
        $queries=[]; $this->db->listen(function ($q) use (&$queries) { $queries[]=$q->sql; });
        $this->customer(); $this->getJson('/api/bookings/'.$b['id'])->assertOk()->assertJsonPath('booking.summary.total','2000000.00')->assertJsonPath('booking.summary.lines.0.unit_price','500000.00');
        $this->getJson('/api/bookings/my')->assertOk(); $this->getJson('/api/payments/'.$b['id'])->assertOk();
        $this->admin(); $this->getJson('/api/admin/bookings?hotel_id=1&customer_id=9001&status=confirmed')->assertOk()->assertJsonPath('data.0.room_quantity',2);
        $this->getJson('/api/admin/hotels')->assertOk(); $this->getJson('/api/admin/customers/9001')->assertOk();
        $id=$this->db->table('booking_effect')->value('id'); app(BookingEffectServiceV2::class)->deliver('mysql',$id,app(\App\Services\PreparedV2\BookingMailSender::class));
        $this->customer(); $this->get('/api/invoices/'.$b['id'].'/pdf')->assertOk();
        $this->assertSame('2000000.00',$this->db->table('invoice')->value('total')); $this->assertSame(1,$this->db->table('customer_notifications')->count());
        $notification=$this->db->table('customer_notifications')->value('id');
        $this->deleteJson('/api/notifications/'.$notification)->assertSuccessful();
        $this->assertNull($this->db->table('booking_effect_notification')->value('notification_id'));
        $this->assertSame(1,$this->db->table('booking_effect_notification')->count());
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,app(\App\Services\PreparedV2\BookingMailSender::class)));
        $this->assertFalse((bool)array_filter($queries,fn($q)=>preg_match('/\b(from|join)\s+`room`(?=\s|$)/i',$q)));
    }
    public function test_sender_crash_after_send_exposes_exactly_once_limit_but_preserves_invoice_and_notification(): void
    {
        $this->enable(); $b=$this->create(); $this->verify($b); $id=$this->db->table('booking_effect')->value('id'); $sender=app(\App\Services\PreparedV2\BookingMailSender::class);
        try { app(BookingEffectServiceV2::class)->deliver('mysql',$id,function ($e) use ($sender) { $sender($e); throw new \RuntimeException('Synthetic crash after transport acceptance'); }); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertSame('Synthetic crash after transport acceptance',$e->getMessage()); }
        $this->customer(); $this->getJson('/api/payments/'.$b['id'])->assertOk()->assertJsonPath('booking.effect_delivery.0.display_state','failed');
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
        $this->assertCount(2,Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame(1,$this->db->table('customer_notifications')->count());
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
    }
    public function test_schema_signed_foreign_keys_rollback_guard_and_history_preservation(): void
    {
        $this->enable(); $b=$this->create(); $this->verify($b);
        $rows=$this->db->select("SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='manual_payment_verification' AND COLUMN_NAME IN ('payment_id','booking_id','verified_by')",[$this->db->getDatabaseName()]);
        foreach ($rows as $r) $this->assertSame('int',$r->t);
        $m=require base_path('database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php');
        try { $m->down(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback refused',$e->getMessage()); }
        $this->assertSame(336,$this->db->table('room')->count()); $this->assertSame(336,$this->db->table('room_image')->count()); $this->assertSame(6,$this->db->table('room_type')->whereNull('hotel_id')->count());
    }
    public function test_rendered_customer_and_admin_templates_preserve_gates_and_auth_data_boundaries(): void
    {
        $this->enable(); $b=$this->create(); $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
        $admin=$this->get('/admin/bookings')->assertOk()->assertSee('bank_statement',false)->assertSee('mountBankingVerification',false);
        $customer=$this->get('/payments/bookings/'.$b['id'])->assertOk();
        file_put_contents($runtime.'/b5-admin-bookings.html',$admin->getContent()); file_put_contents($runtime.'/b5-booking-payment.html',$customer->getContent());
        $customer->assertDontSee('fixture-9001@example.test',false); $this->getJson('/api/payments/'.$b['id'])->assertUnauthorized();
        $this->customer(); $this->getJson('/api/payments/'.$b['id'])->assertOk()->assertJsonPath('banking_instructions_ready',false)->assertJsonPath('vnpay_ready',false);
        config(['roomtype-v2.vnpay_merchant_verified'=>true,'services.vnpay'=>[]]);
        $this->getJson('/api/payments/'.$b['id'])->assertOk()->assertJsonPath('vnpay_ready',false);
        $this->customer(9002); $this->getJson('/api/payments/'.$b['id'])->assertNotFound();
        $this->get('/hotels/1')->assertOk()->assertSee('id="submit-booking-btn"',false);
        $this->assertSame(0,Artisan::call('route:list',['--json'=>true])); file_put_contents($runtime.'/b5-route-list.json',Artisan::output());
    }
    public function test_primary_connection_refused_before_any_final_reader_or_writer_connection(): void
    {
        config(['database.connections.mysql.database'=>'hotel_booking']); DB::shouldReceive('connection')->never();
        foreach ([fn()=>RoomTypeV2Integration::finalize(),fn()=>app(HotelCatalogServiceV2::class)->query(),fn()=>app(ManualBankingPaymentServiceV2::class)->verify('mysql',1,1,9004,[])] as $call) {
            try { $call(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('refused before',$e->getMessage()); }
        }
    }
    public function test_final_admin_crud_gallery_inventory_and_money_are_type_scoped(): void
    {
        $this->enable(); $this->admin();
        $type=$this->postJson('/api/admin/hotels/1/room-types',['name'=>'Final fixture type','description'=>'Synthetic room type','capacity'=>3,'base_price'=>'777777.13','total_rooms'=>5,'status'=>'active'])->assertCreated()->json('room_type.id');
        $prefix='/api/admin/hotels/1/room-types/'.$type;
        $this->postJson($prefix.'/images',['image_url'=>'image/img_room/room (2).jpg','is_primary'=>true])->assertOk()->assertJsonCount(1,'images');
        $this->postJson($prefix.'/images',['image_url'=>'image/img_room/room (2).jpg','is_primary'=>true])->assertOk()->assertJsonCount(1,'images');
        $this->putJson($prefix,['hotel_id'=>3])->assertUnprocessable();
        $this->customer(); $b=$this->postJson('/api/bookings',$this->data(['room_type_id'=>$type,'quantity'=>4,'num_guests'=>12]))->assertCreated()->json('booking');
        $this->assertSame('6222217.04',$b['total_price']); $this->admin();
        $this->putJson($prefix,['total_rooms'=>3,'base_price'=>'9.99'])->assertUnprocessable();
        $this->assertSame('777777.13',$this->db->table('room_type')->where('id',$type)->value('base_price'));
        $this->deleteJson($prefix)->assertOk(); $this->assertTrue($this->db->table('room_type')->where('id',$type)->exists());
        $this->verify($b,['reference'=>'FINAL_NEW_TYPE_REF','amount'=>'6222217.04']);
        $this->assertSame('777777.13',$this->db->table('booking_room')->where('booking_id',$b['id'])->value('price_at_booking'));
        $this->assertSame(336,$this->db->table('room')->count()); $this->assertSame(336,$this->db->table('room_image')->count());
    }
    public function test_unmapped_history_is_refused_before_final_activation_without_hidden_omission(): void
    {
        $b=$this->create(); $this->db->table('booking_room')->where('booking_id',$b['id'])->update(['room_type_id'=>1]);
        try { $this->enable(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('verified Room and BookingRoom remap',$e->getMessage()); }
        try { app(HotelCatalogServiceV2::class)->query(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('Unmapped Booking history',$e->getMessage()); }
        $this->assertSame(1,$this->db->table('booking_room')->value('room_type_id')); $this->assertSame(336,$this->db->table('room')->count());
    }
    public function test_corrupt_overcommitted_inventory_returns_conflict_instead_of_fake_zero(): void
    {
        $b=$this->create(); $this->db->table('room_type')->where('id',$this->type)->update(['total_rooms'=>1]); $this->enable();
        $this->getJson('/api/hotels/1/room-types?check_in=2026-10-15&check_out=2026-10-17')->assertStatus(409);
        $this->assertSame(2,$this->db->table('booking_room')->where('booking_id',$b['id'])->value('quantity'));
    }
    public function test_manual_verification_transaction_failure_rolls_back_all_financial_and_audit_changes(): void
    {
        $this->enable(); $b=$this->create();
        $this->db->unprepared("CREATE TRIGGER b5_effect_fault BEFORE INSERT ON booking_effect FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic banking effect failure'");
        try { $this->verify($b); $this->fail(); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('banking effect failure',$e->getMessage()); }
        $this->assertSame('pending',$this->p($b)->payment_status); $this->assertSame('pending',$this->db->table('booking')->value('status'));
        $this->assertSame(0,$this->db->table('manual_payment_verification')->count()); $this->assertSame(1,$this->db->table('booking_state_audit')->count());
        $this->db->unprepared('DROP TRIGGER b5_effect_fault'); $this->assertSame('success',$this->verify($b)['outcome']);
    }
    private function contend(array $requests,array $hotels=[1]): array
    {
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))); $paths=[]; $processes=[];
        $this->db->beginTransaction(); $this->db->table('hotel')->whereIn('id',$hotels)->orderBy('id')->lockForUpdate()->get();
        try {
            foreach ($requests as $request) {
                $path=$runtime.'/ready-worker-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($path,json_encode($request)); $paths[]=$path;
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/roomtype_v2_final_worker.php'),$path],base_path(),
                    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'AUREL_ROOMTYPE_V2_CASE_DATABASE'=>$this->db->getDatabaseName()]);
                $p->setTimeout(50); $p->start(); $processes[]=$p;
            }
            $until=microtime(true)+20; $waiters=0;
            do {
                $waiters=(int)array_values((array)$this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'"))[1]; if ($waiters>=2) break;
                foreach ($processes as $p) if (!$p->isRunning()) throw new \RuntimeException($p->getErrorOutput());
                usleep(20000);
            } while (microtime(true)<$until);
            $this->assertGreaterThanOrEqual(2,$waiters);
        } catch (\Throwable $e) { foreach ($processes as $p) if ($p->isRunning()) $p->stop(1); throw $e; }
        finally { $this->db->commit(); }
        $result=[];
        try { foreach ($processes as $i=>$p) { $p->wait(); $this->assertSame(0,$p->getExitCode(),$p->getErrorOutput()); $result[]=json_decode(file_get_contents($paths[$i].'.result'),true); } }
        finally { foreach ($processes as $p) if ($p->isRunning()) $p->stop(1); }
        $this->assertCount(2,array_unique(array_column($result,'connection_id')));
        file_put_contents($runtime.'/b5-concurrency-'.bin2hex(random_bytes(6)).'.json',json_encode(['database'=>$this->db->getDatabaseName(),'waiters'=>$waiters,'results'=>$result],JSON_PRETTY_PRINT));
        return $result;
    }
    private function bankRequest(array $b): array { return ['action'=>'bank','id'=>$b['id'],'payment_id'=>$this->p($b)->id,'data'=>$this->verification()]; }
    public function test_two_manual_verifications_serialize_to_one_audit_and_effect(): void
    {
        $this->enable(); $b=$this->create(); $r=$this->contend([$this->bankRequest($b),$this->bankRequest($b)]);
        $this->assertTrue($r[0]['success']); $this->assertTrue($r[1]['success']); $this->assertSame(1,count(array_filter($r,fn($x)=>$x['result']['duplicate'])));
        $this->assertSame(1,$this->db->table('manual_payment_verification')->count()); $this->assertSame(1,$this->db->table('booking_effect')->count());
    }
    public function test_manual_verification_races_cancel_without_resurrection(): void
    {
        $this->enable(); $b=$this->create(); $this->contend([$this->bankRequest($b),['action'=>'cancel','id'=>$b['id'],'customer_id'=>9001]]);
        $this->assertSame('cancelled',$this->db->table('booking')->value('status')); $this->assertSame('success',$this->p($b)->payment_status);
        $this->assertSame(1,$this->db->table('manual_payment_verification')->count());
    }
    public function test_manual_verification_races_expiry_at_deadline_without_confirmation(): void
    {
        $this->enable(); $b=$this->create(); Carbon::setTestNow('2026-10-12 08:16:00');
        $this->contend([array_merge($this->bankRequest($b),['now'=>'2026-10-12 08:16:00']),['action'=>'expire','id'=>$b['id'],'now'=>'2026-10-12 08:16:00']]);
        $this->assertSame('cancelled',$this->db->table('booking')->value('status')); $this->assertSame('late_reconciliation',$this->db->table('manual_payment_verification')->value('outcome'));
        $this->assertSame(0,$this->db->table('booking_effect')->count());
    }
    public function test_manual_verification_races_vnpay_success_with_one_confirmation(): void
    {
        $this->enable(); $b=$this->create(); $p=app(VnpayPaymentServiceV2::class)->start('mysql',$b['id'],9001,'127.0.0.1');
        $this->contend([$this->bankRequest($b),['action'=>'ipn','query'=>$this->query($p)]]);
        $this->assertSame(2,$this->db->table('payment')->where('payment_status','success')->count());
        $this->assertSame(1,$this->db->table('booking_effect')->count()); $this->assertSame('confirmed',$this->db->table('booking')->value('status'));
    }
    public function test_bank_reference_is_unique_across_payments_in_different_hotels(): void
    {
        $this->enable(); $a=$this->create(); $otherType=$this->db->table('room_type_legacy_map')->where('hotel_id',3)->where('old_room_type_id',1)->value('new_room_type_id');
        $b=$this->create(9002,['hotel_id'=>3,'room_type_id'=>$otherType]);
        $r=$this->contend([$this->bankRequest($a),$this->bankRequest($b)],[1,3]);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['success']))); $this->assertSame(1,$this->db->table('manual_payment_verification')->count());
        $this->assertSame(1,$this->db->table('payment')->where('payment_status','success')->count());
    }
}
