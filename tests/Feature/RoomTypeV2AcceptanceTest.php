<?php

namespace Tests\Feature;

use App\Services\BookingServiceV2;
use App\Services\BookingEffectServiceV2;
use App\Services\ManualBankingPaymentServiceV2;
use App\Support\RoomTypeV2Integration;
use App\Support\RoomTypeV2BankingPolicy;
use App\Support\BookingDeliveryPreSendFailure;
use App\Support\RoomTypeV2PaymentConfiguration;
use Carbon\Carbon;
use Tests\Support\RoomTypeV2AcceptanceFixture;
use Tests\TestCase;

class RoomTypeV2AcceptanceTest extends TestCase
{
    private $db; private int $type;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->withoutVite();
        $delivery=$this->name()!=='test_delivery_safety_migration_has_ran_ledger_and_preserves_history_on_down';
        $this->db=RoomTypeV2AcceptanceFixture::initialize($delivery);
        $this->type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        if ($delivery) RoomTypeV2Integration::acceptance(); else RoomTypeV2Integration::finalize();
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function booking(): array
    {
        return app(BookingServiceV2::class)->create('mysql',9001,['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>1,'num_guests'=>2,
            'check_in'=>'2026-10-15','check_out'=>'2026-10-17','payment_method'=>'banking']);
    }
    private function verify(array $b): array
    {
        $p=$this->db->table('payment')->where('booking_id',$b['id'])->first();
        return app(ManualBankingPaymentServiceV2::class)->verify('mysql',$b['id'],$p->id,9004,[
            'reference'=>'ACCEPTANCE_'.$p->id,'amount'=>$p->amount,'received_at'=>'2026-10-12T08:00:00+00:00',
            'funds_received'=>true,'evidence_source'=>'bank_statement']);
    }
    private function effect(): int { $b=$this->booking(); $this->verify($b); return $this->db->table('booking_effect')->value('id'); }
    private function failSend(int $id,\Throwable $failure): void
    {
        try { app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=>throw $failure); $this->fail('Expected sender failure.'); }
        catch (\RuntimeException $e) { $this->assertSame($failure,$e); }
    }
    public function test_deadline_separates_review_from_inventory_and_never_restores_late_funds(): void
    {
        config(['roomtype-v2.manual_verification_minutes'=>30]); $b=$this->booking();
        Carbon::setTestNow('2026-10-12 08:15:00');
        $policy=RoomTypeV2BankingPolicy::describe((object)$b);
        $this->assertFalse($policy['confirmation_allowed']); $this->assertFalse($policy['verification_window_elapsed']);
        $this->assertSame('holding',$policy['inventory_reservation']); $this->assertTrue($policy['inventory_release_pending']);
        $this->assertSame('late_reconciliation',$this->verify($b)['outcome']);
        $this->assertFalse(app(\App\Services\BookingLifecycleServiceV2::class)->expire('mysql',$b['id']));
        Carbon::setTestNow('2026-10-12 08:15:01');
        $this->assertTrue(app(\App\Services\BookingLifecycleServiceV2::class)->expire('mysql',$b['id']));
        $after=$this->db->table('booking')->where('id',$b['id'])->first();
        $this->assertSame('released',RoomTypeV2BankingPolicy::describe($after)['inventory_reservation']);
        $this->assertSame(0,$this->db->table('booking_effect')->count());
    }
    public function test_confirmation_at_deadline_minus_one_second_and_duplicate_have_one_event(): void
    {
        $b=$this->booking(); Carbon::setTestNow('2026-10-12 08:14:59');
        $this->assertSame('success',$this->verify($b)['outcome']); $this->assertTrue($this->verify($b)['duplicate']);
        $this->assertSame(1,$this->db->table('booking_effect')->count());
        $id=$this->db->table('booking_effect')->value('id'); $sent=0;
        $sender=function () use (&$sent) { $sent++; $this->assertSame(0,$this->db->transactionLevel()); };
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
        $this->assertSame(1,$sent); $this->assertSame(1,$this->db->table('invoice')->count());
        $this->assertSame(1,$this->db->table('customer_notifications')->count());
        $this->assertSame('delivered',$this->db->table('booking_effect')->value('state'));
    }
    public function test_pre_send_failure_has_backoff_and_finite_retries(): void
    {
        $id=$this->effect();
        for ($attempt=1;$attempt<=3;$attempt++) {
            $this->failSend($id,new BookingDeliveryPreSendFailure('Template failure'));
            $e=$this->db->table('booking_effect')->first(); $this->assertSame($attempt,$e->attempts);
            $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('Not due / exhausted')));
            $this->assertSame($attempt===3?'failed':'pending',$e->state);
            if ($e->next_attempt_at) Carbon::setTestNow($e->next_attempt_at);
        }
        $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame(1,$this->db->table('booking_effect_notification')->count());
        $this->assertSame(6,$this->db->table('booking_delivery_audit')->count());
    }
    public function test_timeout_is_uncertain_without_automatic_resend(): void
    {
        $id=$this->effect(); $this->failSend($id,new \RuntimeException('Timeout with unknown acceptance'));
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('Must reconcile')));
        $this->assertSame('uncertain',$this->db->table('booking_effect')->value('state'));
        $this->assertNull($this->db->table('booking_effect')->value('delivered_at'));
    }
    public function test_after_acceptance_crash_requires_audited_manual_review(): void
    {
        $id=$this->effect(); $sent=0;
        try { app(BookingEffectServiceV2::class)->deliver('mysql',$id,function () use (&$sent) { $sent++; throw new \RuntimeException('Crash after accepted'); }); }
        catch (\RuntimeException $e) { $this->assertSame('Crash after accepted',$e->getMessage()); }
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('No automatic replay')));
        $result=app(BookingEffectServiceV2::class)->review('mysql',$id,9004,['action'=>'confirm_delivered','reason'=>'Synthetic transport acceptance receipt checked','provider_checked'=>true]);
        $this->assertSame('delivered',$result['state']); $this->assertSame(1,$sent);
        $this->assertSame(9004,$this->db->table('booking_delivery_audit')->orderByDesc('id')->value('actor_id'));
        $this->assertSame(1,$this->db->table('invoice')->count());
    }
    public function test_manual_retry_keeps_event_invoice_and_deleted_notification_marker(): void
    {
        $id=$this->effect(); $this->failSend($id,new \RuntimeException('Unknown SMTP result'));
        $invoice=$this->db->table('invoice')->value('id'); $event=$this->db->table('booking_effect')->value('event_key');
        $this->db->table('customer_notifications')->delete();
        app(BookingEffectServiceV2::class)->review('mysql',$id,9004,['action'=>'retry','reason'=>'Operator checked capture; accepted duplicate risk','provider_checked'=>true,'duplicate_risk_accepted'=>true]);
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=>null));
        $this->assertSame($invoice,$this->db->table('invoice')->value('id')); $this->assertSame($event,$this->db->table('booking_effect')->value('event_key'));
        $this->assertSame(0,$this->db->table('customer_notifications')->count()); $this->assertNull($this->db->table('booking_effect_notification')->value('notification_id'));
    }
    public function test_expired_processing_lease_quarantines_and_two_claims_cannot_send(): void
    {
        $id=$this->effect();
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,function () use ($id) {
            $this->assertSame('processing',$this->db->table('booking_effect')->value('state'));
            $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('Second claim')));
        }));
        $this->assertSame(1,$this->db->table('invoice')->count());
        // Test-only crash fixture through PDO on the already verified owned schema. This also documents the Laravel fence limit.
        $statement=$this->db->getPdo()->prepare("UPDATE booking_effect SET state='processing',delivered_at=NULL,lease_token=?,lease_expires_at=? WHERE id=?");
        $statement->execute(['abandoned-worker','2026-10-12 07:59:59',$id]);
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('Expired lease must not resend')));
        $this->assertSame('uncertain',$this->db->table('booking_effect')->value('state')); $this->assertNull($this->db->table('booking_effect')->value('delivered_at'));
    }
    public function test_missing_or_unverified_configuration_fails_closed_without_secret_output(): void
    {
        $this->assertNull(RoomTypeV2PaymentConfiguration::banking());
        config(['roomtype-v2.banking_instructions'=>['bank_name'=>'Unverified']]); $this->assertNull(RoomTypeV2PaymentConfiguration::banking());
        $r=RoomTypeV2PaymentConfiguration::readiness(); $this->assertSame('BLOCKED',$r['vnpay']['status']); $this->assertSame('BLOCKED',$r['smtp']['status']);
        $this->assertStringNotContainsString(config('services.vnpay.hash_secret'),json_encode($r));
    }
    public function test_smtp_recipient_allowlist_and_remote_tls_are_required(): void
    {
        config(['roomtype-v2.staging_mail'=>['approved'=>true,'host'=>'127.0.0.1','port'=>2525,'from_address'=>'staging@example.test','recipients'=>['fixture-9001@example.test']]]);
        $this->assertSame(2525,RoomTypeV2PaymentConfiguration::smtp('fixture-9001@example.test')['port']);
        try { RoomTypeV2PaymentConfiguration::smtp('real-customer@example.com'); $this->fail('Recipient rejected'); } catch (\RuntimeException $e) { $this->assertStringContainsString('approved',$e->getMessage()); }
        config(['roomtype-v2.staging_mail.host'=>'smtp.example.test']);
        try { RoomTypeV2PaymentConfiguration::smtp(); $this->fail('TLS required'); } catch (\RuntimeException $e) { $this->assertStringContainsString('TLS',$e->getMessage()); }
    }

    public function test_local_smtp_socket_accepts_template_pdf_once_for_approved_synthetic_recipient(): void
    {
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
        $process=new \Symfony\Component\Process\Process(['node',base_path('tests/Support/roomtype_v2_smtp_capture.cjs'),$runtime]);
        $process->setTimeout(45); $process->start();
        try {
            $this->assertTrue($process->waitUntil(fn($type,$output)=>str_contains($output,'PORT=')));
            preg_match('/PORT=(\d+)/',$process->getOutput(),$m); $this->assertNotEmpty($m);
            config(['roomtype-v2.staging_mail'=>['approved'=>true,'host'=>'127.0.0.1','port'=>(int)$m[1],
                'from_address'=>'staging@example.test','from_name'=>'AUREL synthetic staging','recipients'=>['fixture-9001@example.test']]]);
            $id=$this->effect(); $sender=app(\App\Services\PreparedV2\BookingMailSender::class);
            $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
            $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,$sender));
            $receipt=json_decode(file_get_contents($runtime.'/smtp-capture-result.json'),true);
            $mime=file_get_contents($runtime.'/smtp-captured.eml');
            $this->assertTrue($receipt['accepted']); $this->assertSame('fixture-9001@example.test',$receipt['recipient']);
            $this->assertStringContainsString('application/pdf',$mime); $this->assertStringContainsString('Message-ID:',$mime);
            $this->assertStringNotContainsString('Phòng số',$mime); $this->assertSame('delivered',$this->db->table('booking_effect')->value('state'));
            $this->assertSame(1,$this->db->table('invoice')->count());
        } finally { $process->stop(0); }
    }

    public function test_cancel_before_delivery_preserves_funds_without_confirmation_mail(): void
    {
        $id=$this->effect(); $b=$this->db->table('booking_effect')->value('booking_id');
        app(\App\Services\BookingLifecycleServiceV2::class)->cancel('mysql',$b,9001);
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=> $this->fail('Cancelled confirmation')));
        $this->assertSame('blocked',$this->db->table('booking_effect')->value('state'));
        $this->assertSame('success',$this->db->table('payment')->value('payment_status')); $this->assertSame(0,$this->db->table('invoice')->count());
    }

    public function test_admin_delivery_review_rejects_customer_missing_attestation_and_exhaustion(): void
    {
        $id=$this->effect(); $this->failSend($id,new \RuntimeException('Ambiguous'));
        foreach ([['actor'=>9001,'data'=>['action'=>'retry','reason'=>'Checked','provider_checked'=>true,'duplicate_risk_accepted'=>true]],
            ['actor'=>9004,'data'=>['action'=>'retry','reason'=>'Checked','provider_checked'=>true]]] as $case) {
            try { app(BookingEffectServiceV2::class)->review('mysql',$id,$case['actor'],$case['data']); $this->fail('Unauthorized/unsafe retry'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertSame('uncertain',$this->db->table('booking_effect')->value('state'));
        $this->getJson('/api/admin/reconciliation')->assertUnauthorized();
        $customer=\App\Models\Customer::findOrFail(9001); $this->actingAs($customer,'sanctum');
        $this->getJson('/api/admin/reconciliation')->assertUnauthorized();
    }

    public function test_production_and_staging_guards_refuse_primary_before_opening_connection(): void
    {
        config(['app.debug'=>false]); // This case isolates manifest/target refusal; debug has its own negative test.
        config(['database.connections.mysql.database'=>'hotel_booking','roomtype-v2.staging_checkout'=>true]);
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        try { \App\Support\RoomTypeV2CheckoutGate::staging(); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('refused before',$e->getMessage()); }
        config(['roomtype-v2.production_manifest'=>storage_path('app/private/missing-approval.json')]);
        try { \App\Support\RoomTypeV2ProductionGuard::connection('mysql'); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('manifest',$e->getMessage()); }
        $providers=file_get_contents(config_path('app.php')); $this->assertStringNotContainsString('RoomTypeV2ProductionProvider',$providers);
    }

    public function test_production_manifest_requires_target_frozen_writers_backup_and_checkout_off(): void
    {
        $connection=config('database.connections.mysql');
        $manifest=['approved'=>true,'state'=>'ready','checkout_enabled'=>false,'writers_drained'=>true,'callback_replay_ready'=>true,
            'session_timezone'=>'+00:00','historical_timestamps_verified'=>true,
            'restore_proof_sha256'=>str_repeat('a',64),'source_manifest_sha256'=>str_repeat('b',64),'schema_sha256'=>str_repeat('c',64),
            'server_uuid'=>\App\Support\RoomTypeV2TestDatabase::settings()['server_uuid'],'mapping_count'=>105,'expires_at'=>gmdate('Y-m-d\TH:i:s+00:00',time()+3600),
            'target'=>array_intersect_key($connection,array_flip(['host','port','database','username']))];
        \App\Support\RoomTypeV2ProductionGuard::validate($connection,$manifest,'production'); $this->assertTrue(true);
        foreach (['approved'=>false,'writers_drained'=>false,'callback_replay_ready'=>false,'checkout_enabled'=>true,'restore_proof_sha256'=>'','mapping_count'=>0] as $key=>$value) {
            try { \App\Support\RoomTypeV2ProductionGuard::validate($connection,array_replace($manifest,[$key=>$value]),'production'); $this->fail('Approval gate: '.$key); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('approval',$e->getMessage()); }
        }
        $bad=$manifest; $bad['target']['database']='hotel_booking';
        try { \App\Support\RoomTypeV2ProductionGuard::validate($connection,$bad,'production'); $this->fail(); } catch (\RuntimeException $e) { $this->assertStringContainsString('target',$e->getMessage()); }
        \App\Support\RoomTypeV2SchemaReadiness::assert($this->db,105); $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/',\App\Support\RoomTypeV2SchemaReadiness::fingerprint($this->db));
    }

    public function test_deals_staging_checkout_and_reconciliation_render_without_false_promotions(): void
    {
        $html=$this->get('/deals')->assertOk()->getContent(); $this->assertStringNotContainsString('discount-badge',$html);
        $this->assertStringNotContainsString('price-original',$html); $this->assertStringNotContainsString('voucher-card',$html);
        $this->get('/hotels/1')->assertOk()->assertDontSee('id="v2-checkout"',false);
        config(['roomtype-v2.staging_checkout'=>true]);
        $this->get('/hotels/1')->assertOk()->assertSee('id="v2-checkout"',false)->assertSee('payment_method',false);
        $this->get('/admin/reconciliation')->assertOk()->assertSee('delivery-review-dialog',false);
        $this->assertSame(0,\Illuminate\Support\Facades\Artisan::call('route:list',['--json'=>true]));
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/phase2c-route-list.json',\Illuminate\Support\Facades\Artisan::output());
    }

    public function test_preflight_reads_current_counts_fks_money_and_preserves_every_business_checksum(): void
    {
        $this->effect(); $before=\App\Support\RoomTypeV2Preflight::inspect($this->db->getPdo());
        $after=\App\Support\RoomTypeV2Preflight::inspect($this->db->getPdo());
        $this->assertSame($before,$after); $this->assertTrue($before['read_only']);
        $this->assertSame(336,$before['tables']['room']['count']); $this->assertSame(111,$before['tables']['room_type']['count']);
        $this->assertSame(0,$before['checks']['active_duplicate_groups']); $this->assertSame(0,$before['checks']['booking_money_mismatches']);
        foreach ($before['fk_orphans'] as $n) $this->assertSame(0,$n);
        $this->assertArrayHasKey('booking_delivery_audit',$before['schema_definitions']);
        $this->assertCount(count($before['tables']),$before['schema_definitions']);
        $this->assertSame(hash('sha256',json_encode($before['schema_definitions'],JSON_THROW_ON_ERROR)),$before['schema_sha256']);
        $this->assertSame(\App\Support\RoomTypeV2SchemaReadiness::fingerprint($this->db),$before['schema_sha256']);
        file_put_contents(dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/phase2c-owned-preflight.json',json_encode($before,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }

    public function test_delivery_audit_financial_and_asset_backup_restores_on_separate_owned_schema(): void
    {
        $id=$this->effect(); app(BookingEffectServiceV2::class)->deliver('mysql',$id,app(\App\Services\PreparedV2\BookingMailSender::class));
        \Illuminate\Support\Facades\Artisan::call('migrate:install');
        $proof=\Tests\Support\RoomTypeV2BackupRehearsal::run();
        $this->assertTrue($proof['restored_matching']); $this->assertFalse($proof['primary_backup_verified']); $this->assertCount(5,$proof['assets']);
        foreach (['manual_payment_verification','booking_effect','booking_delivery_audit','invoice','invoice_item','booking_effect_notification'] as $table) $this->assertGreaterThan(0,$proof['tables'][$table]['count']);
        $this->assertSame(336,$proof['tables']['room']['count']); $this->assertSame(336,$proof['tables']['room_image']['count']);
        $this->assertNotSame($proof['source'],$proof['restore']);
    }

    private function contend(array $requests): array
    {
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))); $paths=[]; $processes=[];
        $this->db->beginTransaction(); $this->db->table('hotel')->where('id',1)->lockForUpdate()->first();
        try {
            foreach ($requests as $request) {
                $file=$runtime.'/ready-worker-'.bin2hex(random_bytes(6)).'.json'; file_put_contents($file,json_encode($request)); $paths[]=$file;
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tests/Support/roomtype_v2_acceptance_worker.php'),$file],base_path(),
                    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'AUREL_ROOMTYPE_V2_CASE_DATABASE'=>$this->db->getDatabaseName()]);
                $p->setTimeout(60); $p->start(); $processes[]=$p;
            }
            $until=microtime(true)+25; $waiters=0;
            do { $waiters=(int)array_values((array)$this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'"))[1]; if ($waiters>=2) break;
                foreach ($processes as $p) if (!$p->isRunning()) throw new \RuntimeException($p->getErrorOutput()); usleep(20000);
            } while (microtime(true)<$until);
            $this->assertGreaterThanOrEqual(2,$waiters);
        } finally { $this->db->commit(); }
        $results=[];
        try { foreach ($processes as $i=>$p) { $p->wait(); $this->assertSame(0,$p->getExitCode(),$p->getErrorOutput()); $results[]=json_decode(file_get_contents($paths[$i].'.result'),true); } }
        finally { foreach ($processes as $p) if ($p->isRunning()) $p->stop(0); }
        $this->assertCount(2,array_unique(array_column($results,'connection_id')));
        file_put_contents($runtime.'/phase2c-concurrency-'.bin2hex(random_bytes(6)).'.json',json_encode(['database'=>$this->db->getDatabaseName(),'waiters'=>$waiters,'results'=>$results],JSON_PRETTY_PRINT));
        return $results;
    }
    public function test_two_mysql_delivery_workers_claim_one_safe_event(): void
    {
        $id=$this->effect(); $r=$this->contend([['action'=>'deliver','id'=>$id],['action'=>'deliver','id'=>$id]]);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['result']===true))); $this->assertSame(1,$this->db->table('booking_effect')->value('attempts'));
        $this->assertSame('delivered',$this->db->table('booking_effect')->value('state')); $this->assertSame(1,$this->db->table('invoice')->count());
        $this->assertSame(1,$this->db->table('booking_effect_notification')->count());
    }
    public function test_expiry_competes_with_banking_without_restoring_inventory(): void
    {
        $b=$this->booking(); $p=$this->db->table('payment')->first();
        $r=$this->contend([['action'=>'expire','id'=>$b['id'],'now'=>'2026-10-12 08:16:00'],
            ['action'=>'bank','id'=>$b['id'],'payment_id'=>$p->id,'now'=>'2026-10-12 08:16:00','data'=>['reference'=>'RACE_C','amount'=>$p->amount,
                'received_at'=>'2026-10-12T08:00:00+00:00','funds_received'=>true,'evidence_source'=>'bank_statement']]]);
        $this->assertTrue($r[0]['success']); $this->assertTrue($r[1]['success']); $this->assertSame('cancelled',$this->db->table('booking')->value('status'));
        $this->assertSame('late_reconciliation',$this->db->table('manual_payment_verification')->value('outcome')); $this->assertSame(0,$this->db->table('booking_effect')->count());
    }

    public function test_delivery_safety_migration_has_ran_ledger_and_preserves_history_on_down(): void
    {
        $name='2026_10_12_000008_add_booking_delivery_safety';
        $this->assertSame(0,\Illuminate\Support\Facades\Artisan::call('migrate',['--database'=>'mysql','--path'=>'database/migrations/'.$name.'.php','--force'=>true]));
        $this->assertTrue($this->db->table('migrations')->where('migration',$name)->exists()); RoomTypeV2Integration::acceptance();
        $column=$this->db->selectOne("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='booking_delivery_audit' AND COLUMN_NAME='effect_id'",[$this->db->getDatabaseName()]);
        $this->assertSame('int',$column->t);
        $id=$this->effect(); app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=>null);
        $before=\App\Support\RoomTypeV2Preflight::inspect($this->db->getPdo());
        try { (require base_path('database/migrations/'.$name.'.php'))->down(); $this->fail('Audit down must refuse.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('history',$e->getMessage()); }
        $after=\App\Support\RoomTypeV2Preflight::inspect($this->db->getPdo()); $this->assertSame($before,$after);
    }

    public function test_production_provider_rehearsal_uses_owned_target_and_keeps_checkout_locked(): void
    {
        config(['app.debug'=>false]);
        $db=$this->db; $settings=config('database.connections.mysql');
        $root=storage_path('app/private/roomtype-v2-deployment'); if (!is_dir($root)) mkdir($root,0700,true);
        $file=$root.'/synthetic-rehearsal-'.bin2hex(random_bytes(6)).'.json';
        $manifest=['approved'=>true,'synthetic_rehearsal'=>true,'state'=>'active','checkout_enabled'=>false,'writers_drained'=>true,'callback_replay_ready'=>true,
            'session_timezone'=>'+00:00','historical_timestamps_verified'=>true,
            'restore_proof_sha256'=>str_repeat('a',64),'source_manifest_sha256'=>str_repeat('b',64),'schema_sha256'=>\App\Support\RoomTypeV2SchemaReadiness::fingerprint($db),
            'server_uuid'=>\App\Support\RoomTypeV2TestDatabase::settings()['server_uuid'],'mapping_count'=>105,'expires_at'=>gmdate('Y-m-d\TH:i:s+00:00',time()+3600),
            'target'=>array_intersect_key($settings,array_flip(['host','port','database','username']))];
        file_put_contents($file,json_encode($manifest,JSON_THROW_ON_ERROR));
        config(['roomtype-v2.production_manifest'=>$file,'roomtype-v2.production_manifest_sha256'=>hash_file('sha256',$file),
            'roomtype-v2.integration_complete'=>false,'roomtype-v2.final_integration'=>false,'roomtype-v2.delivery_safety'=>false]);
        app()->instance('env','production');
        try {
            try { \App\Support\RoomTypeV2Database::connection('mysql'); $this->fail('Partial lifecycle must not access deployment DB.'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('complete verified',$e->getMessage()); }
            app()->register(\App\Providers\RoomTypeV2ProductionProvider::class);
            $this->assertSame($db,\App\Support\RoomTypeV2ProductionGuard::connection('mysql'));
            $this->assertTrue(config('roomtype-v2.delivery_safety')); $this->assertFalse(config('roomtype-v2.staging_checkout'));
            $this->get('/hotels/1')->assertOk()->assertDontSee('id="v2-checkout"',false);
            $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum');
            $this->postJson('/api/bookings',['hotel_id'=>1,'room_type_id'=>$this->type,'quantity'=>1,'num_guests'=>2,'check_in'=>'2026-10-15','check_out'=>'2026-10-17','payment_method'=>'banking'])->assertForbidden();
            $this->assertSame(0,$db->table('booking')->count());
            $fileData=json_decode(file_get_contents($file),true); $fileData['target']['database']='hotel_booking'; file_put_contents($file,json_encode($fileData));
            try { \App\Support\RoomTypeV2ProductionGuard::connection('mysql'); $this->fail('Drifted approval refused'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('manifest',$e->getMessage()); }
        } finally { app()->instance('env','testing'); config(['roomtype-v2.production_manifest'=>null]); }
    }

    public function test_utc_session_keeps_real_timestamp_epoch_and_payment_deadline_coherent(): void
    {
        $b=$this->booking();
        $r=$this->db->selectOne('SELECT UNIX_TIMESTAMP(created_at) created_epoch, UNIX_TIMESTAMP(expires_at) expiry_epoch FROM booking WHERE id=?',[$b['id']]);
        $this->assertSame(Carbon::parse('2026-10-12 08:00:00','UTC')->timestamp,(int)$r->created_epoch);
        $this->assertSame(900,(int)$r->expiry_epoch-(int)$r->created_epoch);
        $this->assertSame('+00:00',$this->db->selectOne('SELECT @@session.time_zone tz')->tz);
        $this->assertSame('success',$this->verify($b)['outcome']);
    }

    public function test_paid_booking_cancelled_after_delivery_remains_visible_for_funds_reconciliation(): void
    {
        $id=$this->effect(); app(BookingEffectServiceV2::class)->deliver('mysql',$id,fn()=>null);
        $booking=$this->db->table('booking_effect')->value('booking_id');
        app(\App\Services\BookingLifecycleServiceV2::class)->cancel('mysql',$booking,9001);
        $detail=app(\App\Services\BookingReadServiceV2::class)->detail($booking,9001);
        $this->assertSame('reconciliation',$detail->payment->display_status);
        $this->assertSame('success',$this->db->table('manual_payment_verification')->value('outcome'));
        $admin=\App\Models\Customer::findOrFail(9004)->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($admin,'admin');
        $this->getJson('/api/admin/reconciliation')->assertOk()->assertJsonPath('manual.0.outcome','funded_released_reconciliation')->assertJsonPath('manual.0.original_outcome','success');
        $this->assertSame(1,$this->db->table('invoice')->count()); $this->assertSame('success',$this->db->table('payment')->value('payment_status'));
        $this->assertSame('cancelled',$detail->status);
    }
}
