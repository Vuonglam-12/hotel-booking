<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\RoomTypeV2BackfillService;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RoomTypeV2LegacyFixture;
use Tests\TestCase;

class RoomTypeV2PreparationTest extends TestCase
{
    private Connection $db;
    private RoomTypeV2Plan $plan;
    private array $source;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = RoomTypeV2TestDatabase::settings();
        $bootstrap = RoomTypeV2TestDatabase::connection('mysql');
        $schema = null;
        foreach ($settings['schemas'] as $candidate) {
            if ((int) $bootstrap->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?', [$candidate])->n === 0) {
                $schema = $candidate; break;
            }
        }
        if (!$schema) throw new \RuntimeException('Owned empty test schemas exhausted; initialize another isolated server.');
        config(['database.connections.mysql.database' => $schema]);
        DB::purge('mysql');
        $this->db = RoomTypeV2TestDatabase::connection('mysql');
        RoomTypeV2LegacyFixture::schema();
        $this->plan = RoomTypeV2Plan::prepared();
        RoomTypeV2LegacyFixture::data($this->db, $this->plan);
        $this->source = $this->legacySnapshot();
        $this->migrateV2();
    }

    private function migrateV2(): void
    {
        $paths = [
            'database/migrations/2026_10_11_000002_expand_room_type_for_hotel_inventory.php',
            'database/migrations/2026_10_11_000003_create_room_type_image_table.php',
            'database/migrations/2026_10_11_000004_create_room_type_legacy_map_table.php',
        ];
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'mysql', '--path' => $paths, '--force' => true]), Artisan::output());
    }

    private function backfill(bool $dryRun = false): array
    {
        return app(RoomTypeV2BackfillService::class)->run('mysql', $this->plan, $dryRun);
    }

    private function rows(string $table): array
    {
        return $this->db->table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all();
    }

    private function legacySnapshot(): array
    {
        $types = $this->db->table('room_type')->whereIn('id', [1,2,3,4,5,6])->orderBy('id')->get()
            ->map(fn($row) => array_intersect_key((array) $row, array_flip(['id','name','capacity','base_price','bed_type','description','created_at'])))->all();
        return ['hotel' => $this->rows('hotel'), 'room' => $this->rows('room'), 'room_image' => $this->rows('room_image'),
            'booking' => $this->rows('booking'), 'booking_room' => $this->rows('booking_room'), 'types' => $types];
    }

    public function test_expand_retains_legacy_data_types_keys_and_nullable_fields(): void
    {
        $this->assertSame($this->source, $this->legacySnapshot());
        $columns = $this->db->select("SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='room_type'");
        $byName = array_column(array_map(fn($row)=>(array)$row, $columns), null, 'COLUMN_NAME');
        foreach (['id','hotel_id','total_rooms'] as $field) {
            $this->assertSame('int', $byName[$field]['DATA_TYPE']);
            $this->assertStringNotContainsString('unsigned', $byName[$field]['COLUMN_TYPE']);
        }
        foreach (['hotel_id','total_rooms','status'] as $field) $this->assertSame('YES', $byName[$field]['IS_NULLABLE']);
        $this->assertSame(6, $this->db->table('room_type')->whereNull('hotel_id')->whereNull('total_rooms')->whereNull('status')->count());
        $this->assertSame(3, $this->db->table('migrations')->count());
        $this->assertSame(336, $this->db->table('room')->count());
        $indexes = $this->db->select('SHOW INDEX FROM room');
        $this->assertContains('uq_room', array_map(fn($row)=>$row->Key_name, $indexes));
    }

    public function test_full_backfill_reconciles_all_groups_and_preserves_source(): void
    {
        $result = $this->backfill();
        foreach (['created_groups'=>105,'checkpoints'=>105,'legacy_types'=>6,'owned_types'=>105,'total_rooms_v2'=>336,'type_images'=>105] as $field=>$expected) $this->assertSame($expected,$result[$field]);
        $this->assertSame(111, $this->db->table('room_type')->count());
        $this->assertSame($this->source, $this->legacySnapshot());
        foreach ($this->db->table('room_type_legacy_map')->get() as $map) {
            $legacy = $this->db->table('room_type')->where('id',$map->old_room_type_id)->first();
            $owned = $this->db->table('room_type')->where('id',$map->new_room_type_id)->first();
            foreach (['name','base_price','capacity','bed_type','description','created_at'] as $field) $this->assertSame($legacy->$field,$owned->$field);
            $this->assertSame($map->hotel_id,$owned->hotel_id);
            $this->assertSame('active',$owned->status);
            $this->assertSame($this->db->table('room')->where('hotel_id',$map->hotel_id)->where('room_type_id',$map->old_room_type_id)->count(),$owned->total_rooms);
            $image=$this->db->table('room_type_image')->where('room_type_id',$owned->id)->first();
            $expected=$this->plan->groups[$map->hotel_id.':'.$map->old_room_type_id]['source_image_url'];
            $this->assertSame($expected,$image->image_url);
            $this->assertSame(64,strlen($map->source_checksum));
        }
        $presidential=$this->db->table('room_type')->where('id',6)->first();
        $this->assertNull($presidential->hotel_id);
        $this->assertSame(0,$this->db->table('room_type_legacy_map')->where('old_room_type_id',6)->count());
        $runtime=dirname(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'));
        file_put_contents($runtime.'/phase2b1-reconciliation.json',json_encode(['database'=>$this->db->getDatabaseName(),'result'=>$result],JSON_PRETTY_PRINT));
    }

    public function test_backfill_uses_database_generated_ids_after_a_large_gap(): void
    {
        $this->db->statement('ALTER TABLE room_type AUTO_INCREMENT=127');
        $this->backfill();
        $this->assertSame(127,$this->db->table('room_type_legacy_map')->min('new_room_type_id'));
        $this->assertSame(105,$this->db->table('room_type_legacy_map')->distinct()->count('new_room_type_id'));
        $this->assertSame($this->source,$this->legacySnapshot());
    }

    public function test_rerun_is_idempotent_and_dry_run_never_writes(): void
    {
        $dry=$this->backfill(true);
        $this->assertSame(0,$dry['created_groups']);$this->assertSame(6,$this->db->table('room_type')->count());
        $this->backfill();
        $before=[$this->rows('room_type'),$this->rows('room_type_image'),$this->rows('room_type_legacy_map')];
        $again=$this->backfill();
        $this->assertSame(0,$again['created_groups']);$this->assertSame(105,$again['skipped_groups']);
        $this->assertSame($before,[$this->rows('room_type'),$this->rows('room_type_image'),$this->rows('room_type_legacy_map')]);
    }

    #[DataProvider('sourceDrifts')]
    public function test_source_drift_stops_before_any_new_target(string $scenario): void
    {
        if($scenario==='price')$this->db->table('room')->where('id',1)->update(['price'=>'500001.00']);
        elseif($scenario==='capacity')$this->db->table('room')->where('id',1)->update(['capacity'=>3]);
        elseif($scenario==='description')$this->db->table('room_type')->where('id',1)->update(['description'=>'Changed source']);
        elseif($scenario==='cross-hotel image')$this->db->table('room_image')->where('id',1)->update(['room_id'=>17]);
        else $this->db->table('room')->where('id',1)->update(['status'=>'maintenance']);
        $changed=$this->legacySnapshot();
        $this->assertFailure(fn()=>$this->backfill());
        $this->assertSame(6,$this->db->table('room_type')->count());$this->assertSame(0,$this->db->table('room_type_legacy_map')->count());
        $this->assertSame($changed,$this->legacySnapshot());
    }

    public static function sourceDrifts(): array { return [['price'],['capacity'],['description'],['cross-hotel image'],['status']]; }

    public function test_source_checksum_drift_on_resume_does_not_overwrite_targets(): void
    {
        $this->backfill();$before=$this->rows('room_type');
        $this->db->table('room')->where('id',1)->update(['floor'=>99]);
        $this->assertFailure(fn()=>$this->backfill());
        $this->assertSame($before,$this->rows('room_type'));
    }

    public function test_target_checksum_drift_on_resume_is_not_repaired_silently(): void
    {
        $this->backfill();$id=$this->db->table('room_type_legacy_map')->value('new_room_type_id');
        $this->db->table('room_type')->where('id',$id)->update(['base_price'=>'777777.77']);
        $before=$this->rows('room_type');$this->assertFailure(fn()=>$this->backfill());
        $this->assertSame($before,$this->rows('room_type'));
    }

    public function test_real_mid_hotel_sql_failure_rolls_back_that_hotel_and_can_resume(): void
    {
        $this->db->unprepared("CREATE TRIGGER fixture_rt_fail BEFORE INSERT ON room_type_image FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM room_type WHERE id=NEW.room_type_id AND hotel_id=3) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated mid-backfill failure'; END IF; END");
        $this->assertFailure(fn()=>$this->backfill());
        $this->assertSame(10,$this->db->table('room_type_legacy_map')->count());
        $this->assertSame(0,$this->db->table('room_type')->where('hotel_id',3)->count());
        $this->assertSame($this->source,$this->legacySnapshot());
        $this->db->unprepared('DROP TRIGGER fixture_rt_fail');
        $resume=$this->backfill();
        $this->assertSame(95,$resume['created_groups']);$this->assertSame(10,$resume['skipped_groups']);
        $this->assertSame(105,$resume['checkpoints']);$this->assertSame(105,$resume['type_images']);
        $this->assertSame($this->source,$this->legacySnapshot());
    }

    public function test_booking_history_with_assigned_room_and_quantity_keeps_ids_relations_and_money(): void
    {
        RoomTypeV2LegacyFixture::history($this->db);
        $before=$this->legacySnapshot();$this->backfill();
        $this->assertSame($before,$this->legacySnapshot());
        $booking=Booking::with(['bookingRooms.roomType','bookingRooms.room'])->findOrFail(901);
        $this->assertSame('4000667.50',$booking->total_price);
        $this->assertSame(4,(int)$booking->bookingRooms->sum('quantity'));
        $this->assertSame(1,$booking->bookingRooms->first()->room->id);
        $this->assertSame('LEG-1',$booking->bookingRooms->first()->room->room_number);
        $this->assertSame(3,$booking->bookingRooms->last()->quantity);
        $this->assertSame('500111.25',$booking->bookingRooms->last()->price_at_booking);
        $this->assertSame(2,$booking->bookingRooms->last()->room_type_id);
    }

    public function test_foreign_keys_checks_and_mapping_uniqueness_reject_invalid_data(): void
    {
        $this->assertFailure(fn()=>$this->db->table('room_type')->where('id',1)->update(['hotel_id'=>999]));
        $this->assertFailure(fn()=>$this->db->table('room_type')->where('id',1)->update(['total_rooms'=>-1]));
        $this->assertFailure(fn()=>$this->db->table('room_type')->where('id',1)->update(['status'=>'unexpected']));
        $this->assertFailure(fn()=>$this->db->table('room_type_image')->insert(['room_type_id'=>999,'image_url'=>'image/missing.jpg']));
        $this->backfill();$map=(array)$this->db->table('room_type_legacy_map')->first();unset($map['id']);
        $this->assertFailure(fn()=>$this->db->table('room_type_legacy_map')->insert($map));
        $this->assertFailure(fn()=>$this->db->table('room_type')->where('id',1)->delete());
        $this->assertSame(6,$this->db->table('room_type')->whereNull('hotel_id')->count());
    }

    public function test_different_hotels_have_independent_owned_types_and_no_cross_hotel_mapping(): void
    {
        $this->backfill();
        $a=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $b=$this->db->table('room_type_legacy_map')->where('hotel_id',2)->where('old_room_type_id',1)->value('new_room_type_id');
        $this->assertNotSame($a,$b);
        $this->db->table('room_type')->where('id',$a)->update(['base_price'=>'600000.25','capacity'=>3]);
        $other=$this->db->table('room_type')->where('id',$b)->first();
        $this->assertSame('500000.00',$other->base_price);$this->assertSame(2,$other->capacity);
        $this->assertSame($this->source,$this->legacySnapshot());
    }

    public function test_advisory_lock_refuses_a_second_backfill_before_writing(): void
    {
        $settings=RoomTypeV2TestDatabase::settings();
        $pdo=new \PDO('mysql:host='.$settings['host'].';port='.$settings['port'].';dbname='.$this->db->getDatabaseName(),$settings['username'],$settings['password']);
        $lock='aurel_rt_v2:'.$this->db->getDatabaseName();$statement=$pdo->prepare('SELECT GET_LOCK(?,0)');$statement->execute([$lock]);
        try {$this->assertFailure(fn()=>$this->backfill());$this->assertSame(6,$this->db->table('room_type')->count());}
        finally {$release=$pdo->prepare('SELECT RELEASE_LOCK(?)');$release->execute([$lock]);}
    }

    public function test_command_dry_run_backfill_and_repeat_use_the_actual_registered_command(): void
    {
        $this->assertSame(0,Artisan::call('room-types:v2-prepare',['--database'=>'mysql','--dry-run'=>true]),Artisan::output());
        $this->assertSame(6,$this->db->table('room_type')->count());
        $code=Artisan::call('room-types:v2-prepare',['--database'=>'mysql']);$output=Artisan::output();
        $this->assertSame(0,$code,$output);$this->assertSame(105,json_decode($output,true,512,JSON_THROW_ON_ERROR)['created_groups']);
        $code=Artisan::call('room-types:v2-prepare',['--database'=>'mysql']);$output=Artisan::output();
        $this->assertSame(0,$code,$output);$this->assertSame(105,json_decode($output,true,512,JSON_THROW_ON_ERROR)['skipped_groups']);
    }

    public function test_plan_checksum_and_image_target_drift_both_stop_resume(): void
    {
        $this->backfill();$map=$this->db->table('room_type_legacy_map')->first();
        $original=$map->plan_checksum;
        $this->db->table('room_type_legacy_map')->where('id',$map->id)->update(['plan_checksum'=>str_repeat('0',64)]);
        $this->assertFailure(fn()=>$this->backfill());
        $this->db->table('room_type_legacy_map')->where('id',$map->id)->update(['plan_checksum'=>$original]);
        $this->db->table('room_type_image')->where('room_type_id',$map->new_room_type_id)->update(['is_primary'=>false]);
        $before=$this->rows('room_type_image');$this->assertFailure(fn()=>$this->backfill());
        $this->assertSame($before,$this->rows('room_type_image'));
    }

    public function test_corrupt_checkpoint_is_not_completed_silently(): void
    {
        $this->backfill();
        // No source deletion: move a checkpoint to the intentionally unmapped legacy type.
        $map=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->first();
        $this->db->table('room_type_legacy_map')->where('id',$map->id)->update(['old_room_type_id'=>6]);
        $before=$this->rows('room_type');$this->assertFailure(fn()=>$this->backfill());
        $this->assertSame($before,$this->rows('room_type'));
    }

    public function test_runtime_guard_refuses_primary_config_before_db_connection_is_requested(): void
    {
        config(['database.connections.blocked'=>array_merge(config('database.connections.mysql'),['database'=>'hotel_booking'])]);
        DB::shouldReceive('connection')->never();
        $this->assertFailure(fn()=>RoomTypeV2TestDatabase::connection('blocked'));
    }

    public function test_down_refuses_to_destroy_backfilled_data(): void
    {
        $this->backfill();$before=[$this->rows('room_type'),$this->rows('room_type_image'),$this->rows('room_type_legacy_map')];
        foreach (['000004_create_room_type_legacy_map_table','000003_create_room_type_image_table','000002_expand_room_type_for_hotel_inventory'] as $suffix) {
            $migration=require base_path('database/migrations/2026_10_11_'.$suffix.'.php');
            $this->assertFailure(fn()=>$migration->down());
        }
        $this->assertSame($before,[$this->rows('room_type'),$this->rows('room_type_image'),$this->rows('room_type_legacy_map')]);
    }

    public function test_large_decimal_is_copied_exactly_without_float_rounding(): void
    {
        $runtime=dirname(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'));
        $csv=fopen(base_path('docs/hotel-roomtype-phase2a-mapping.csv'),'rb');$headers=fgetcsv($csv);$lines=[];
        while(($values=fgetcsv($csv))!==false){$row=array_combine($headers,$values);if($row['legacy_room_type_id']==='1')foreach(['price_before_room','price_before_room_type','price_after'] as $field)$row[$field]='9999999999.97';$lines[]=$row;}fclose($csv);
        $csvPath=$runtime.'/decimal-plan-'.$this->db->getDatabaseName().'.csv';$out=fopen($csvPath,'wb');fputcsv($out,$headers);foreach($lines as $row)fputcsv($out,array_map(fn($field)=>$row[$field],$headers));fclose($out);
        $types=json_decode(file_get_contents(base_path('docs/hotel-roomtype-phase2b1-source-types.json')),true);$types['legacy_types'][0]['base_price']='9999999999.97';
        $typesPath=$runtime.'/decimal-types-'.$this->db->getDatabaseName().'.json';file_put_contents($typesPath,json_encode($types));
        $this->db->table('room_type')->where('id',1)->update(['base_price'=>'9999999999.97']);$this->db->table('room')->where('room_type_id',1)->update(['price'=>'9999999999.97']);
        $before=$this->legacySnapshot();$plan=new RoomTypeV2Plan($csvPath,$typesPath);app(RoomTypeV2BackfillService::class)->run('mysql',$plan);
        $id=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $this->assertSame('9999999999.97',$this->db->table('room_type')->where('id',$id)->value('base_price'));$this->assertSame($before,$this->legacySnapshot());
    }

    public function test_endpoint_override_is_refused_before_db_manager_connection(): void
    {
        $port=(int)RoomTypeV2TestDatabase::settings()['port'];$otherPort=$port===65535?$port-1:$port+1;
        config(['database.connections.spoofed'=>array_merge(config('database.connections.mysql'),['port'=>$otherPort])]);
        DB::shouldReceive('connection')->never();
        $this->assertFailure(fn()=>RoomTypeV2TestDatabase::connection('spoofed'));
    }

    private function assertFailure(callable $action): void
    {
        try {$action();$this->fail('Expected guarded rejection/SQL failure.');}
        catch (\RuntimeException|\Illuminate\Database\QueryException $error) {$this->assertNotEmpty($error->getMessage());}
    }
}
