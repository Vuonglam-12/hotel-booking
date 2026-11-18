<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\LocalBookingDemoService;
use App\Services\RoomAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class LocalBookingDemoTest extends TestCase
{
    private $db;
    protected function setUp(): void
    {
        parent::setUp();$this->db=HotelRoomTypeFixture::initialize();HotelRoomTypeFixture::migrate();
        (require base_path('database/migrations/2026_10_12_000005_replace_booking_status_unique.php'))->up();
        (require base_path('database/migrations/2026_10_26_000001_create_local_booking_demo_audit.php'))->up();
        (require base_path('database/migrations/2026_10_31_000004_create_booking_demo_reconciliation.php'))->up();
        (require base_path('database/migrations/2026_10_31_000005_create_booking_demo_payment_notices.php'))->up();
        $this->db->table('customer')->insert(['id'=>3,'name'=>'Other customer','email'=>'other@example.test','role'=>'user']);
        config(['booking-demo.local_enabled'=>true,'app.url'=>'http://localhost','app.debug'=>true,'logging.channels.stderr.level'=>'critical']);$this->withoutVite();Mail::fake();Http::preventStrayRequests();$this->actor();
    }
    private function actor(int $id=2,bool $admin=false): void
    {
        $u=Customer::findOrFail($id);$u->withAccessToken(new PersonalAccessToken(['name'=>$admin?'admin_token':'customer_token','abilities'=>$admin?['admin']:['*']]));$this->actingAs($u,$admin?'admin':'sanctum');
    }
    private function data(array $extra=[]): array { return array_replace(['hotel_id'=>1,'room_type_id'=>1,'quantity'=>1,'num_guests'=>2,'check_in'=>now()->addDays(20)->toDateString(),'check_out'=>now()->addDays(22)->toDateString(),'request_key'=>bin2hex(random_bytes(16)),'payment_method'=>'demo'],$extra); }
    private function make(array $extra=[]): int { return $this->postJson('http://localhost/api/bookings',$this->data($extra))->assertCreated()->assertJsonPath('demo',true)->json('booking_id'); }
    private function state(int $id): array { return $this->getJson('http://localhost/api/demo/bookings/'.$id)->assertOk()->json(); }
    public function test_surcharge_quote_change_requires_reconfirmation_and_booked_checkout_invoice_keep_saved_prices(): void
    {
        $pricing=app(\App\Services\RoomPricingService::class);
        $this->db->table('hotel_room_type')->where('hotel_id',1)->where('room_type_id',1)->update(['base_price'=>'6500000.00']);
        $input=['kind'=>'day','start_date'=>'2031-01-01','end_date'=>'2031-01-01','price'=>'100000'];
        $rate=$pricing->saveRate(1,1,$input);
        $data=$this->data(['check_in'=>'2031-01-01','check_out'=>'2031-01-03','quantity'=>2]);
        $quote=$pricing->quote(1,1,$data['check_in'],$data['check_out'],2);
        $this->assertSame('26200000.00',$quote['total_price']);
        $oldId=$this->postJson('http://localhost/api/bookings',$data+['quote_hash'=>$quote['quote_hash']])->assertCreated()->json('booking_id');
        $oldSnapshot=$this->db->table('booking_nightly_rate')->orderBy('id')->get()->toJson();
        $pricing->saveRate(1,1,array_replace($input,['price'=>'200000']),$rate['id']);
        $this->actor(3);
        $changed=$this->data(['check_in'=>'2031-01-01','check_out'=>'2031-01-03','quantity'=>1,'quote_hash'=>$pricing->quote(1,1,'2031-01-01','2031-01-03')['quote_hash']]);
        $pricing->saveRate(1,1,array_replace($input,['price'=>'300000']),$rate['id']);
        $count=$this->db->table('booking')->count();
        $this->postJson('http://localhost/api/bookings',$changed)->assertStatus(409)->assertJsonPath('message','Giá phòng đã thay đổi. Vui lòng xem lại giá từng đêm trước khi đặt.');
        $this->assertSame($count,$this->db->table('booking')->count());
        $changed['quote_hash']=$pricing->quote(1,1,'2031-01-01','2031-01-03')['quote_hash'];
        $this->postJson('http://localhost/api/bookings',$changed)->assertCreated();
        $this->assertSame($oldSnapshot,$this->db->table('booking_nightly_rate')->whereIn('booking_room_id',$this->db->table('booking_room')->where('booking_id',$oldId)->pluck('id'))->orderBy('id')->get()->toJson());
        $this->actor(2);
        $old=$this->state($oldId);
        $this->assertSame('26200000.00',$old['booking']['total_price']);
        $this->assertSame('6500000.00',$old['booking']['nightly_rates'][0]['base_price']);
        $this->assertSame('100000.00',$old['booking']['nightly_rates'][0]['surcharge']);
        $this->act($oldId,'success')->assertOk();
        $invoice=$this->db->table('invoice')->where('booking_id',$oldId)->first();
        $this->assertSame('26200000.00',$invoice->total);
        $items=$this->db->table('invoice_item')->where('invoice_id',$invoice->id)->orderBy('id')->get();
        $this->assertCount(2,$items);
        $this->assertSame('6600000.00',$items[0]->unit_price);
        $this->assertStringContainsString('100.000,00',$items[0]->description);
        $this->assertSame('26200000.00',number_format((float)$items->sum('amount'),2,'.',''));
        $booking=\App\Models\Booking::with(['hotel','customer','bookingRooms.roomType'])->findOrFail($oldId);
        $html=view('pdf.invoice',['invoice'=>\App\Models\Invoice::findOrFail($invoice->id),'booking'=>$booking])->render();
        $this->assertStringContainsString('Giá cơ bản 6.500.000 ₫',$html);
        $this->assertStringContainsString('Phụ thu +100.000 ₫',$html);
        $this->assertStringContainsString('6.600.000 ₫',$html);
        $this->get('http://localhost/api/invoices/'.$oldId.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->get('http://localhost/payments/bookings/'.$oldId)->assertOk();
        $this->assertFalse(config('roomtype-v2.prepared_routes'));
        Http::assertNothingSent();Mail::assertNothingSent();
    }
    private function act(int $id,string $action,?int $payment=null)
    {
        $customer=(int)auth('sanctum')->id();$this->actor(1,true);
        try { return $this->postJson('http://localhost/api/admin/demo/bookings/'.$id.'/simulate',['action'=>$action,'payment_id'=>$payment??$this->db->table('payment')->where('booking_id',$id)->orderByDesc('id')->value('id')]); }
        finally { auth('admin')->forgetUser();$this->actor($customer); }
    }

    private function confirmationMailTransport(): \Illuminate\Mail\Transport\ArrayTransport
    {
        $directory=storage_path('app/private/confirmation-mail-tests');
        if(!is_dir($directory))mkdir($directory,0700,true);
        config(['booking-demo.confirmation_mail_enabled'=>true,'booking-demo.confirmation_mail_path'=>$directory.'/'.bin2hex(random_bytes(6)),
            'mail.default'=>'array','mail.from.address'=>'aurel@example.test','mail.from.name'=>'AUREL']);
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        return Mail::mailer('array')->getSymfonyTransport();
    }

    public function test_confirmation_email_is_sent_after_commit_once_with_one_invoice_and_saved_room_name(): void
    {
        $this->withoutExceptionHandling();
        $transport=$this->confirmationMailTransport();
        $id=$this->make();
        $this->db->table('hotel_room_type')->where('hotel_id',1)->update(['name'=>'Renamed after booking']);
        $this->db->beginTransaction();
        $this->act($id,'success')->assertOk();
        $this->assertCount(0,$transport->messages());
        $this->db->commit();
        $this->assertCount(1,$transport->messages());
        $this->act($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $this->assertCount(1,$transport->messages());
        $message=$transport->messages()->first()->getOriginalMessage();
        $this->assertSame('customer@example.test',$message->getTo()[0]->getAddress());
        $this->assertStringContainsString('Angel',$message->getSubject());
        $this->assertStringContainsString('Deluxe',$message->getHtmlBody());
        $this->assertStringNotContainsString('Renamed after booking',$message->getHtmlBody());
        $this->assertStringNotContainsString('HolidayViet',$message->getHtmlBody());
        $attachments=array_values(array_filter($message->getAttachments(),fn($part)=>$part->getMediaSubtype()==='pdf'));
        $this->assertCount(1,$attachments);
        $this->assertStringStartsWith('%PDF-',$attachments[0]->getBody());
        $this->state($id);$this->assertSame('sent',app(\App\Services\LocalBookingConfirmationMailService::class)->status($id)['status']);
        $this->assertSame(1,$this->db->table('invoice')->count());
        Http::assertNothingSent();
    }

    public function test_confirmation_email_is_not_sent_for_unpaid_booking_or_rolled_back_confirmation(): void
    {
        $transport=$this->confirmationMailTransport();$id=$this->make();
        $service=app(\App\Services\LocalBookingConfirmationMailService::class);
        $this->assertSame('preparation_failed',$service->deliver($id)['status']);
        $this->assertCount(0,$transport->messages());
        $this->db->beginTransaction();$this->act($id,'success')->assertOk();$this->db->rollBack();
        $this->assertCount(0,$transport->messages());
        $this->assertSame('pending',$this->db->table('booking')->where('id',$id)->value('status'));
        $this->assertSame(0,$this->db->table('invoice')->count());
        Http::assertNothingSent();
    }

    public function test_uncertain_confirmation_email_does_not_rollback_payment_or_resend_automatically(): void
    {
        $this->confirmationMailTransport();
        $transport=new class extends \Illuminate\Mail\Transport\ArrayTransport {
            public int $attempts=0;
            public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope=null): ?\Symfony\Component\Mailer\SentMessage
            {
                $this->attempts++;
                throw new \RuntimeException('Transport acknowledgement was interrupted.');
            }
        };
        Mail::mailer('array')->setSymfonyTransport($transport);
        $id=$this->make();$this->act($id,'success')->assertOk();$this->act($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $this->assertSame(1,$transport->attempts);
        $this->assertSame('confirmed',$this->db->table('booking')->where('id',$id)->value('status'));
        $this->assertSame('success',$this->db->table('payment')->value('payment_status'));
        $this->assertSame(1,$this->db->table('invoice')->count());
        $this->assertSame('uncertain',app(\App\Services\LocalBookingConfirmationMailService::class)->status($id)['status']);
        Http::assertNothingSent();
    }

    public function test_confirmation_email_lock_and_interrupted_send_block_duplicate_delivery(): void
    {
        $transport=$this->confirmationMailTransport();$id=$this->make();$this->act($id,'success')->assertOk();
        $service=app(\App\Services\LocalBookingConfirmationMailService::class);
        $path=glob(config('booking-demo.confirmation_mail_path').'/*.json')[0];
        $lock=fopen($path.'.lock','c');$this->assertTrue(flock($lock,LOCK_EX|LOCK_NB));
        try {$this->assertSame('sending',$service->deliver($id)['status']);}
        finally {flock($lock,LOCK_UN);fclose($lock);}
        $record=json_decode(file_get_contents($path),true);$record['status']='sending';
        file_put_contents($path,json_encode($record));
        $this->assertSame('uncertain',$service->deliver($id)['status']);
        $this->assertSame('uncertain',$service->deliver($id)['status']);
        $this->assertCount(1,$transport->messages());
        $this->assertSame(1,$this->db->table('invoice')->count());
        Http::assertNothingSent();
    }

    public function test_confirmation_email_can_retry_a_proven_pre_send_smtp_authentication_failure(): void
    {
        $transport=$this->confirmationMailTransport();
        Mail::mailer('array')->setSymfonyTransport(new class extends \Illuminate\Mail\Transport\ArrayTransport {
            public function send(\Symfony\Component\Mime\RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope=null): ?\Symfony\Component\Mailer\SentMessage
            {
                throw new \Symfony\Component\Mailer\Exception\TransportException('Failed to authenticate on SMTP server',535);
            }
        });
        $id=$this->make();$this->act($id,'success')->assertOk();
        $service=app(\App\Services\LocalBookingConfirmationMailService::class);
        $this->assertSame('preparation_failed',$service->status($id)['status']);
        $this->assertSame('authentication',$service->status($id)['smtp_stage']);
        $this->assertSame(535,$service->status($id)['smtp_code']);
        Mail::mailer('array')->setSymfonyTransport($transport);
        $this->act($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $this->assertCount(1,$transport->messages());
        $this->assertSame('sent',$service->status($id)['status']);
        $this->assertSame(1,$this->db->table('invoice')->count());
        Http::assertNothingSent();
    }

    public function test_create_uses_scoped_price_capacity_and_idempotency_not_client_total(): void
    {
        $this->db->table('hotel_room_type')->where('hotel_id',1)->update(['base_price'=>'123456.13','name'=>'Scoped snapshot','capacity'=>3]);
        $data=$this->data(['quantity'=>2,'num_guests'=>6,'total_price'=>1]);$r=$this->postJson('http://localhost/api/bookings',$data)->assertCreated();$id=$r->json('booking_id');
        $s=$this->state($id);$this->assertSame('493824.52',$s['booking']['total_price']);$this->assertSame('pending',$s['booking']['status']);$this->assertSame('pending',$s['payment']['payment_status']);$this->assertSame('Scoped snapshot',$s['booking']['summary']['lines'][0]['name']);
        $this->postJson('http://localhost/api/bookings',$data)->assertOk()->assertJsonPath('duplicate',true)->assertJsonPath('booking_id',$id);
        $this->postJson('http://localhost/api/bookings',array_replace($data,['quantity'=>1]))->assertConflict();
        $this->assertSame(1,$this->db->table('booking')->count());$this->assertSame(0,$this->db->table('invoice')->count());$this->assertSame(600,(int)$this->db->table('booking_demo')->value('expires_at_epoch')-(int)$this->db->table('booking_demo')->value('created_at_epoch'));Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_capacity_wrong_hotel_bad_dates_and_no_stock_are_rejected(): void
    {
        $this->postJson('http://localhost/api/bookings',$this->data(['num_guests'=>5]))->assertUnprocessable();
        $this->postJson('http://localhost/api/bookings',$this->data(['hotel_id'=>3]))->assertUnprocessable();
        $this->postJson('http://localhost/api/bookings',$this->data(['check_out'=>now()->addDays(19)->toDateString()]))->assertUnprocessable();
        $this->postJson('http://localhost/api/bookings',$this->data(['quantity'=>6]))->assertUnprocessable();
        $this->db->table('hotel_room_type')->where('hotel_id',1)->update(['status'=>'inactive']);$this->postJson('http://localhost/api/bookings',$this->data())->assertUnprocessable();
        $this->assertSame(0,$this->db->table('booking')->count());
    }
    public function test_success_is_atomic_idempotent_and_keeps_saved_room_name_in_invoice(): void
    {
        $id=$this->make();$this->db->table('hotel_room_type')->where('hotel_id',1)->update(['name'=>'Renamed after booking']);
        $this->act($id,'success')->assertOk()->assertJsonPath('outcome','success');$this->act($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $s=$this->state($id);$this->assertSame('confirmed',$s['booking']['status']);$this->assertSame('success',$s['payment']['payment_status']);$this->assertSame(1,$this->db->table('invoice')->count());$this->assertSame(1,$this->db->table('booking_demo_event')->count());$this->assertSame(1,$this->db->table('customer_notifications')->count());
        $this->assertStringStartsWith('DEMO-INV-',$s['invoice']['invoice_no']);$this->assertStringContainsString('Deluxe',$this->db->table('invoice_item')->value('description'));$this->assertStringNotContainsString('Renamed',$this->db->table('invoice_item')->value('description'));
        $this->getJson('http://localhost/api/invoices/'.$id)->assertOk()->assertJsonPath('invoice.total',$s['booking']['total_price']);
        $this->get('http://localhost/api/invoices/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');Mail::assertNothingSent();Http::assertNothingSent();
        $booking=\App\Models\Booking::with(['hotel','customer','bookingRooms.roomType'])->findOrFail($id);
        $html=view('pdf.invoice',['invoice'=>\App\Models\Invoice::with('items')->where('booking_id',$id)->sole(),'booking'=>$booking])->render();
        $this->assertStringContainsString('AUREL-HD-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),$html);
        $this->assertStringNotContainsString('DEMO',$html);$this->assertStringNotContainsString('mô phỏng',$html);
    }
    public function test_failed_retry_and_stale_payment_cannot_settle_new_attempt(): void
    {
        $id=$this->make();$old=$this->state($id)['payment']['id'];$this->act($id,'failed')->assertOk();$this->assertSame(0,$this->db->table('invoice')->count());
        $this->assertSame(0,$this->db->table('customer_notifications')->count());
        $retry=$this->postJson('http://localhost/api/demo/bookings/'.$id.'/retry')->assertOk()->json('payment_id');$this->assertNotSame($old,$retry);
        $this->postJson('http://localhost/api/demo/bookings/'.$id.'/retry')->assertOk()->assertJsonPath('duplicate',true)->assertJsonPath('payment_id',$retry);
        $this->act($id,'success',$old)->assertConflict();$this->act($id,'success',$retry)->assertOk();$this->assertSame(2,$this->db->table('payment')->count());$this->assertSame(1,$this->db->table('invoice')->count());
    }
    public function test_payment_confirmation_notification_is_created_once_and_only_visible_to_owner(): void
    {
        $id=$this->make();$this->getJson('http://localhost/api/notifications/unread-count')->assertOk()->assertJsonPath('count',0);
        $this->act($id,'success')->assertOk();$this->act($id,'success')->assertOk()->assertJsonPath('duplicate',true);
        $notice=$this->getJson('http://localhost/api/notifications?per_page=100')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.type','payment_success')->assertJsonPath('data.0.data.booking_id',$id)->assertJsonPath('data.0.data.title','Thanh toán thành công')->json('data.0');
        $this->getJson('http://localhost/api/notifications/unread-count')->assertOk()->assertJsonPath('count',1);
        $this->actor(3);$this->getJson('http://localhost/api/notifications')->assertOk()->assertJsonCount(0,'data');$this->postJson('http://localhost/api/notifications/'.$notice['id'].'/read')->assertNotFound();
        $this->actor();$this->postJson('http://localhost/api/notifications/'.$notice['id'].'/read')->assertOk();$this->getJson('http://localhost/api/notifications/unread-count')->assertOk()->assertJsonPath('count',0);
        $this->assertSame(1,$this->db->table('customer_notifications')->count());
    }
    public function test_cancellation_releases_stock_and_repeated_cancelled_history_is_supported(): void
    {
        $id=$this->make(['quantity'=>5,'num_guests'=>10]);$d=$this->data();$a=app(RoomAvailabilityService::class);$this->assertSame(0,$a->forType(1,1,$d['check_in'],$d['check_out'])['available_rooms']);
        $this->act($id,'cancelled')->assertOk();$this->assertSame(5,$a->forType(1,1,$d['check_in'],$d['check_out'])['available_rooms']);
        $next=$this->make();$this->act($next,'cancelled')->assertOk();$this->assertSame(2,$this->db->table('booking')->where('status','cancelled')->count());$this->assertSame(0,$this->db->table('invoice')->count());
    }
    public function test_deadline_is_absolute_and_late_success_expires_without_invoice(): void
    {
        $this->db->statement("SET SESSION time_zone='+07:00'");$id=$this->make();$s=$this->state($id);$this->assertEqualsWithDelta(time()+600,strtotime($s['expires_at_utc']),5);
        $this->db->table('booking_demo')->where('booking_id',$id)->update(['expires_at_epoch'=>time()-1]);
        $d=$this->data();$this->assertSame(5,app(RoomAvailabilityService::class)->forType(1,1,$d['check_in'],$d['check_out'])['available_rooms']);
        $this->act($id,'success')->assertOk()->assertJsonPath('outcome','late_reconciliation');$this->assertSame('cancelled',$this->db->table('booking')->find($id)->status);$this->assertSame(1,$this->db->table('booking_demo_reconciliation')->count());$this->assertSame(0,$this->db->table('invoice')->count());$this->make();
    }
    public function test_explicit_expiry_and_pending_history_cancellation(): void
    {
        $id=$this->make();$this->act($id,'expired')->assertOk();$this->assertSame('expired',$this->state($id)['payment']['payment_status']);$next=$this->make();
        $this->postJson('http://localhost/api/bookings/'.$next.'/cancel')->assertOk();$this->assertSame('cancelled',$this->db->table('booking')->find($next)->status);
    }
    public function test_other_customer_admin_permissions_and_remote_hosts_are_refused(): void
    {
        $id=$this->make();$this->actor(3);$this->getJson('http://localhost/api/demo/bookings/'.$id)->assertNotFound();
        $this->postJson('http://localhost/api/demo/bookings/'.$id.'/simulate',['action'=>'success','payment_id'=>$this->db->table('payment')->value('id')])->assertForbidden();
        $this->getJson('http://localhost/api/admin/demo/bookings')->assertUnauthorized();
        $this->actor(3,true);$this->getJson('http://localhost/api/admin/demo/bookings')->assertForbidden();
        $this->actor(1,true);$this->getJson('http://localhost/api/admin/demo/bookings')->assertOk()->assertJsonPath('data.0.id',$id);
        $this->postJson('http://localhost/api/admin/demo/bookings/'.$id.'/simulate',['action'=>'success','payment_id'=>$this->db->table('payment')->value('id')])->assertOk();
        $this->actor();$this->getJson('http://external.example/api/demo/bookings/'.$id)->assertNotFound();
    }
    public function test_amount_mismatch_and_removed_inventory_cannot_be_confirmed(): void
    {
        $id=$this->make();$this->db->table('payment')->where('booking_id',$id)->update(['amount'=>'1.00']);$this->act($id,'success')->assertOk()->assertJsonPath('outcome','amount_mismatch_reconciliation');
        $this->db->table('payment')->where('booking_id',$id)->update(['amount'=>'1600000.50']);$this->db->table('room')->where('hotel_id',1)->update(['status'=>'maintenance']);$this->act($id,'success')->assertUnprocessable();$this->assertSame(0,$this->db->table('invoice')->count());
    }
    public function test_mode_off_stays_closed_and_no_real_gateway_is_enabled(): void
    {
        $this->get('http://localhost/booking/1')->assertRedirect('http://localhost/hotels/1');
        $id=$this->make();foreach(['/api/payments/create','/api/payments/manual','/api/payments/retry'] as $path)$this->postJson('http://localhost'.$path,['booking_id'=>$id,'payment_method'=>'banking'])->assertStatus(503);
        config(['booking-demo.local_enabled'=>false]);$this->postJson('http://localhost/api/bookings',$this->data())->assertStatus(503);$this->getJson('http://localhost/api/demo/bookings/'.$id)->assertNotFound();
        $this->assertSame('pending',$this->db->table('payment')->value('payment_status'));Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_demo_success_is_not_counted_as_real_revenue_and_history_has_resume_link(): void
    {
        $id=$this->make();$this->getJson('http://localhost/api/bookings/my')->assertOk()->assertJsonPath('data.0.is_demo',true)->assertJsonPath('data.0.banking_policy.confirmation_allowed',true);
        $this->act($id,'success')->assertOk();$this->assertSame('0.00',(string)Payment::withoutDemo()->sum('amount')==='0'?'0.00':(string)Payment::withoutDemo()->sum('amount'));
        $this->assertSame('1600000.50',(string)Payment::sum('amount'));
        $this->get('http://localhost/payments/bookings/'.$id)->assertOk()->assertSee('Tải hóa đơn')->assertSee('Mã thanh toán')->assertDontSee('data-demo-action="success"',false)->assertDontSee('KHÔNG CHUYỂN TIỀN THẬT')->assertDontSee('giả lập')->assertDontSee('mô phỏng');
        $notification=json_decode($this->db->table('customer_notifications')->value('data'),true);$this->assertSame('simulation',$notification['payment_mode']);
    }
    public function test_only_admin_tool_can_send_simulated_outcomes_and_customer_only_tracks_state(): void
    {
        $id=$this->make();$payment=$this->db->table('payment')->value('id');
        foreach(['success','failed','cancelled','expired'] as $action)$this->postJson('http://localhost/api/demo/bookings/'.$id.'/simulate',['action'=>$action,'payment_id'=>$payment])->assertForbidden();
        $this->assertSame('pending',$this->state($id)['booking']['status']);$this->assertSame(0,$this->db->table('booking_demo_event')->count());$this->assertSame(0,$this->db->table('invoice')->count());
        $this->get('http://localhost/payments/bookings/'.$id)->assertOk()->assertDontSee('data-demo-action="success"',false);
        $this->get('http://localhost/demo/admin/payments/'.$id)->assertOk()->assertSee('Thông báo thành công')->assertSee('data-demo-action="success"',false)->assertDontSee('giả lập')->assertDontSee('mô phỏng');
        $this->act($id,'success')->assertOk();$this->assertSame('confirmed',$this->state($id)['booking']['status']);$this->assertSame('admin',$this->db->table('booking_demo_event')->value('actor_kind'));
        // The older isolated V2 tool keeps its own presentation and controls.
        $this->assertStringContainsString('data-demo-action="success"',view('demo.payment-panel',['bookingId'=>$id,'adminMode'=>false])->render());
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_customer_refresh_expires_due_booking_without_customer_simulation(): void
    {
        $id=$this->make();$this->db->table('booking_demo')->where('booking_id',$id)->update(['expires_at_epoch'=>time()-1]);
        $s=$this->state($id);$this->assertSame('cancelled',$s['booking']['status']);$this->assertSame('expired',$s['payment']['payment_status']);
        $this->assertSame('system',$this->db->table('booking_demo_event')->value('actor_kind'));$this->assertSame(0,$this->db->table('invoice')->count());
    }
    public function test_migration_reentry_preserves_audit_and_rollback_refuses_used_history(): void
    {
        $id=$this->make();$migration=require base_path('database/migrations/2026_10_26_000001_create_local_booking_demo_audit.php');$migration->up();$this->assertSame(1,$this->db->table('booking_demo')->count());
        $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('history exists');$migration->down();
    }

    public function test_concurrent_buyers_cannot_oversell_the_last_available_room(): void
    {
        $this->db->table('room')->where('hotel_id',1)->where('id','!=',$this->db->table('room')->where('hotel_id',1)->min('id'))->update(['status'=>'maintenance']);
        $workers=[];foreach([2,3] as $actor){$p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/local_booking_demo_worker.php'),$this->db->getDatabaseName(),'create',(string)$actor,json_encode($this->data())],base_path(),['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')]);$p->setTimeout(20);$p->start();$workers[]=$p;}
        $codes=[];foreach($workers as $p){$p->wait();$codes[]=$p->getExitCode();$this->assertContains($p->getExitCode(),[0,3],$p->getErrorOutput());}sort($codes);$this->assertSame([0,3],$codes);$this->assertSame(1,$this->db->table('booking')->count());
    }
    public function test_concurrent_success_creates_one_invoice_event_and_notification(): void
    {
        $id=$this->make();$payment=$this->db->table('payment')->where('booking_id',$id)->value('id');$workers=[];
        foreach([1,2] as $ignored){$p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/local_booking_demo_worker.php'),$this->db->getDatabaseName(),'success','1',json_encode(['booking_id'=>$id,'payment_id'=>$payment])],base_path(),['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')]);$p->setTimeout(20);$p->start();$workers[]=$p;}
        foreach($workers as $p){$p->wait();$this->assertSame(0,$p->getExitCode(),$p->getErrorOutput());}
        $this->assertSame(1,$this->db->table('invoice')->count());$this->assertSame(1,$this->db->table('booking_demo_event')->count());$this->assertSame(1,$this->db->table('customer_notifications')->count());
    }
}
