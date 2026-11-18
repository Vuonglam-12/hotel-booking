<?php

namespace Tests\Feature;

use App\Services\RoomTypeV2BackfillService;
use App\Services\RoomTypeV2RemapService;
use App\Services\BookingServiceV2;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2Integration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\RoomTypeV2CutoverFixture;
use Tests\TestCase;

class RoomTypeV2CutoverDryRunTest extends TestCase
{
    private $db;
    private string $manifest;
    private const EXPAND=['2026_10_11_000002_expand_room_type_for_hotel_inventory','2026_10_11_000003_create_room_type_image_table','2026_10_11_000004_create_room_type_legacy_map_table'];
    private const UNIQUE='2026_10_12_000005_replace_booking_status_unique';
    private const EFFECTS='2026_10_12_000006_create_payment_receipts_and_effects';
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->withoutVite();
        $this->db=RoomTypeV2CutoverFixture::initialize();
        $this->manifest=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/remap-'.bin2hex(random_bytes(6)).'.json';
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function migration(string $name): void
    {
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'mysql','--path'=>'database/migrations/'.$name.'.php','--force'=>true]),Artisan::output());
        $this->assertTrue($this->db->table('migrations')->where('migration',$name)->exists());
    }
    private function expand(): void { foreach (self::EXPAND as $name) $this->migration($name); }
    private function remap(): void
    {
        app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        app(RoomTypeV2RemapService::class)->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest);
        app(RoomTypeV2RemapService::class)->run('mysql',$this->manifest);
    }
    private function rows(string $table): array { return $this->db->table($table)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all(); }
    public function test_complete_dependency_order_preserves_history_images_and_financial_snapshots(): void
    {
        $this->db->table('payment')->insert(['id'=>2901,'booking_id'=>901,'amount'=>'4000667.50','payment_method'=>'banking','payment_status'=>'success','transaction_id'=>'FIXTURE_HISTORY']);
        $this->db->table('invoice')->insert(['id'=>3901,'invoice_no'=>'FIXTURE-OLD','booking_id'=>901,'payment_id'=>2901,'customer_id'=>9001,'subtotal'=>'4000667.50','total'=>'4000667.50']);
        $before=[]; foreach (['hotel','room_image','payment','invoice','invoice_item'] as $table) $before[$table]=$this->rows($table);
        $history=$this->rows('booking_room'); $steps=[];
        $this->expand(); $steps[]='expand';
        $backfill=app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared()); $steps[]='backfill';
        $this->assertSame(105,$backfill['created_groups']); $this->assertSame(336,$backfill['total_rooms_v2']); $this->assertSame(105,$backfill['type_images']);
        $this->assertSame(0,app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared())['created_groups']);
        $service=app(RoomTypeV2RemapService::class); $service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest);
        $remap=$service->run('mysql',$this->manifest); $steps[]='remap';
        $this->assertSame(336,$remap['remapped_rooms']); $this->assertSame(2,$remap['remapped_booking_rooms']);
        $this->assertSame(0,$service->run('mysql',$this->manifest)['remapped_rooms']);
        foreach ($history as $old) {
            $new=(array)$this->db->table('booking_room')->where('id',$old['id'])->first();
            $map=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',$old['room_type_id'])->first();
            $this->assertSame($map->new_room_type_id,$new['room_type_id']); $new['room_type_id']=$old['room_type_id']; $this->assertSame($old,$new);
        }
        $this->migration(self::UNIQUE); $steps[]='active_unique'; $this->migration(self::EFFECTS); $steps[]='receipts_effects';
        RoomTypeV2Integration::backend(); RoomTypeV2Integration::complete(); $steps[]='wiring';
        $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum');
        $this->getJson('/api/bookings/901')->assertOk()->assertJsonPath('booking.total_price','4000667.50')->assertJsonPath('booking.booking_rooms.1.quantity',3);
        $this->getJson('/api/invoices/901')->assertOk()->assertJsonPath('invoice.invoice_no','FIXTURE-OLD');
        $this->get('/hotels/1')->assertOk();
        foreach ($before as $table=>$rows) $this->assertSame($rows,$this->rows($table),'Preserved '.$table);
        $this->assertSame(6,$this->db->table('room_type')->whereNull('hotel_id')->count());
        $this->assertSame(336,$this->db->table('room')->count()); $this->assertSame(336,$this->db->table('room_image')->count());
        $this->assertSame(105,$this->db->table('room_type_legacy_map')->count()); $this->assertSame(105,$this->db->table('room_type_image')->count());
        $dangling=$this->db->selectOne('SELECT COUNT(*) AS n FROM room r LEFT JOIN room_type rt ON rt.id=r.room_type_id WHERE rt.id IS NULL OR rt.hotel_id<>r.hotel_id'); $this->assertSame(0,(int)$dangling->n);
        file_put_contents(dirname($this->manifest).'/cutover-dry-run.json',json_encode(['database'=>$this->db->getDatabaseName(),'steps'=>$steps,'backfill'=>$backfill,'remap'=>$remap,'history_preserved'=>true,'financial_rows_preserved'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
    public function test_backfill_fault_resumes_from_checkpoints_before_remap(): void
    {
        $this->expand();
        $this->db->unprepared("CREATE TRIGGER b4_backfill_fault BEFORE INSERT ON room_type FOR EACH ROW BEGIN IF NEW.hotel_id=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='B4 backfill checkpoint fault'; END IF; END");
        try { app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared()); $this->fail(); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('checkpoint fault',$e->getMessage()); }
        $this->assertSame(5,$this->db->table('room_type_legacy_map')->count());
        $this->db->unprepared('DROP TRIGGER b4_backfill_fault');
        $result=app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        $this->assertSame(100,$result['created_groups']); $this->assertSame(5,$result['skipped_groups']);
        $this->assertSame(105,$this->db->table('room_type_image')->count()); $this->assertSame(336,$this->db->table('room')->count());
    }
    public function test_remap_fault_resumes_per_hotel_without_rewriting_history(): void
    {
        $this->expand(); app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        $service=app(RoomTypeV2RemapService::class); $service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest);
        $this->db->unprepared("CREATE TRIGGER b4_remap_fault BEFORE UPDATE ON room FOR EACH ROW BEGIN IF OLD.hotel_id=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='B4 remap checkpoint fault'; END IF; END");
        try { $service->run('mysql',$this->manifest); $this->fail(); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('checkpoint fault',$e->getMessage()); }
        $this->assertSame(16,$this->db->table('room')->where('hotel_id',1)->where('room_type_id','>',6)->count());
        $this->assertSame(0,$this->db->table('room')->where('hotel_id',2)->where('room_type_id','>',6)->count());
        $this->db->unprepared('DROP TRIGGER b4_remap_fault'); $result=$service->run('mysql',$this->manifest);
        $this->assertSame(320,$result['remapped_rooms']); $this->assertSame(0,$result['remapped_booking_rooms']);
        $this->assertSame(0,$service->run('mysql',$this->manifest)['remapped_rooms']);
    }
    public function test_partial_mysql_ddl_is_visible_and_forward_repair_adds_only_missing_table(): void
    {
        $this->expand(); $this->remap(); $this->migration(self::UNIQUE);
        $fault=true;
        $this->db->beforeExecuting(function ($sql) use (&$fault) {
            if ($fault && str_starts_with($sql,'create table `booking_effect`')) { $fault=false; throw new \RuntimeException('Synthetic DDL interruption'); }
        });
        try { Artisan::call('migrate',['--database'=>'mysql','--path'=>'database/migrations/'.self::EFFECTS.'.php','--force'=>true]); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('DDL interruption',$e->getMessage()); }
        $this->assertTrue($this->db->getSchemaBuilder()->hasTable('payment_gateway_receipt'));
        $this->assertFalse($this->db->getSchemaBuilder()->hasTable('booking_effect'));
        $this->assertFalse($this->db->table('migrations')->where('migration',self::EFFECTS)->exists());
        $receiptSchema=$this->db->select('SHOW CREATE TABLE payment_gateway_receipt');
        // Rehearsal-only forward fix: generate SQL from the exact source migration, execute only the missing table.
        $m=require base_path('database/migrations/'.self::EFFECTS.'.php'); $sql=$this->db->pretend(fn()=>$m->up()); $executed=0;
        foreach ($sql as $query) {
            if (preg_match('/^(create|alter) table `booking_effect` /',$query['query'])) { $this->db->statement($query['query'],$query['bindings']); $executed++; }
        }
        $this->assertGreaterThan(0,$executed); $this->assertTrue($this->db->getSchemaBuilder()->hasTable('booking_effect'));
        $this->assertEquals($receiptSchema,$this->db->select('SHOW CREATE TABLE payment_gateway_receipt'));
        $fks=$this->db->select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='booking_effect'",[$this->db->getDatabaseName()]); $this->assertCount(3,$fks);
        $this->db->table('migrations')->insert(['migration'=>self::EFFECTS,'batch'=>(int)$this->db->table('migrations')->max('batch')+1]);
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'mysql','--path'=>'database/migrations/'.self::EFFECTS.'.php','--force'=>true]));
        $this->assertSame(336,$this->db->table('room')->count()); $this->assertSame(2,$this->db->table('booking_room')->count());
    }
    public function test_new_v2_writes_invalidate_old_manifest_and_require_forward_fix(): void
    {
        $this->expand(); $this->remap(); $this->migration(self::UNIQUE); $this->migration(self::EFFECTS);
        $type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $b=app(BookingServiceV2::class)->create('mysql',9002,['hotel_id'=>1,'room_type_id'=>$type,'quantity'=>1,'num_guests'=>2,'check_in'=>'2026-10-15','check_out'=>'2026-10-17']);
        try { app(RoomTypeV2RemapService::class)->run('mysql',$this->manifest); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertMatchesRegularExpression('/drift|added after manifest/',$e->getMessage()); }
        $this->assertTrue($this->db->table('booking')->where('id',$b['id'])->exists()); $this->assertSame(336,$this->db->table('room')->count());
    }
    public function test_owned_mysql_dump_restores_to_separate_empty_test_schema_with_matching_checksums(): void
    {
        $this->expand(); $this->remap(); $this->migration(self::UNIQUE); $this->migration(self::EFFECTS);
        $type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $booking=app(BookingServiceV2::class)->create('mysql',9002,['hotel_id'=>1,'room_type_id'=>$type,'quantity'=>2,'num_guests'=>4,'check_in'=>'2026-10-15','check_out'=>'2026-10-17']);
        $payment=app(\App\Services\VnpayPaymentServiceV2::class)->start('mysql',$booking['id'],9002,'127.0.0.1');
        $query=['vnp_TxnRef'=>$payment['txn_ref'],'vnp_TmnCode'=>'TEST0001','vnp_Amount'=>(string)(int)str_replace('.','',$payment['amount']),
            'vnp_ResponseCode'=>'00','vnp_TransactionStatus'=>'00','vnp_TransactionNo'=>'123450001','vnp_PayDate'=>'20261012150000'];
        $query['vnp_SecureHash']=(new \App\Support\VnpayProtocol(config('services.vnpay')))->sign($query);
        app(\App\Services\VnpayPaymentServiceV2::class)->receive('mysql',$query);
        app(\App\Services\BookingEffectServiceV2::class)->deliver('mysql',$this->db->table('booking_effect')->value('id'),app(\App\Services\PreparedV2\BookingMailSender::class));
        foreach (['payment','payment_gateway_receipt','booking_effect','invoice','invoice_item'] as $table) $this->assertGreaterThan(0,$this->db->table($table)->count(),'Restore proof needs nonempty '.$table);
        $settings=\App\Support\RoomTypeV2TestDatabase::settings(); $target=null;
        foreach ($settings['schemas'] as $candidate) {
            if ((int)$this->db->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$candidate])->n===0) { $target=$candidate; break; }
        }
        if (!$target) throw new \RuntimeException('Need a separate empty owned schema for restore proof.');
        config(['database.connections.v2_restore'=>array_replace(config('database.connections.mysql'),['database'=>$target])]);
        $restore=\App\Support\RoomTypeV2TestDatabase::connection('v2_restore');
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
        $defaults=$runtime.'/backup-client-'.bin2hex(random_bytes(6)).'.cnf';
        file_put_contents($defaults,"[client]\nhost=127.0.0.1\nport=".$settings['port']."\nuser=".$settings['username']."\npassword=".$settings['password']."\nprotocol=TCP\n");
        $binary='C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';
        $dump=new \Symfony\Component\Process\Process([$binary.'mysqldump.exe','--defaults-file='.$defaults,'--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--skip-comments',$this->db->getDatabaseName()]);
        $dump->setTimeout(40); $dump->mustRun(); $sql=$dump->getOutput();
        $this->assertStringNotContainsString('hotel_booking',$sql); $this->assertStringNotContainsString('CREATE DATABASE',$sql);
        file_put_contents($runtime.'/owned-backup.sql',$sql);
        $import=new \Symfony\Component\Process\Process([$binary.'mysql.exe','--defaults-file='.$defaults,'--default-character-set=utf8mb4',$target]);
        $import->setInput($sql); $import->setTimeout(40); $import->mustRun();
        $hashes=[];
        foreach ($this->db->select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME',[$this->db->getDatabaseName()]) as $table) {
            $sourceRows=$this->db->table($table->name)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
            $restoredRows=$restore->table($table->name)->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
            $this->assertSame($sourceRows,$restoredRows,'Restored '.$table->name); $hashes[$table->name]=RoomTypeV2Plan::hash($sourceRows);
        }
        file_put_contents($runtime.'/owned-backup-restore-proof.json',json_encode(['source'=>$this->db->getDatabaseName(),'restore'=>$target,'port'=>$settings['port'],
            'dump_sha256'=>hash('sha256',$sql),'table_checksums'=>$hashes,'restored_matching'=>true,'primary_backup_verified'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
}
