<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\RoomTypeV2AcceptanceFixture;
use App\Support\RoomTypeV2Integration;
use App\Services\HotelCatalogServiceV2;
use App\Services\BookingReadServiceV2;
use Carbon\Carbon;

class RoomTypeV2PerformanceTest extends TestCase
{
    public function test_measure_catalog_and_history_on_identical_synthetic_fixture(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00');
        try {
            $reuse=getenv('AUREL_ROOMTYPE_V2_REUSE_BENCHMARK')==='true';
            config(['database.connections.mysql.timezone'=>'+00:00']);
            $db=$reuse?\App\Support\RoomTypeV2TestDatabase::connection('mysql'):RoomTypeV2AcceptanceFixture::initialize();
            $type=$db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
            for ($i=0;!$reuse && $i<20;$i++) {
                $id=$db->table('booking')->insertGetId(['customer_id'=>9001,'hotel_id'=>1,'check_in'=>'2026-10-15','check_out'=>'2026-10-17',
                    'num_guests'=>2,'total_price'=>'1000000.00','status'=>'cancelled','created_at'=>now(),'expires_at'=>now()->addMinutes(15)]);
                $db->table('booking_room')->insert(['booking_id'=>$id,'room_type_id'=>$type,'room_id'=>null,'quantity'=>1,'nights'=>2,'price_at_booking'=>'500000.00']);
                $db->table('payment')->insert(['booking_id'=>$id,'amount'=>'1000000.00','payment_method'=>'banking','payment_status'=>'pending','transaction_id'=>'PERF_'.$i,'created_at'=>now()]);
            }
            $this->assertSame(21,$db->table('hotel')->count()); $this->assertSame(105,$db->table('room_type_legacy_map')->count()); $this->assertSame(20,$db->table('booking')->count());
            RoomTypeV2Integration::acceptance();
            $label=getenv('AUREL_ROOMTYPE_V2_BENCHMARK')?:'after';
            if (!in_array($label,['baseline','after'],true)) throw new \RuntimeException('Benchmark label must be baseline/after.');
            $queries=[]; $recording=false;
            $db->listen(function ($q) use (&$queries,&$recording) { if ($recording) $queries[]=['sql'=>$q->sql,'ms'=>$q->time]; });
            $probes=[
                'catalog_21'=>fn()=>app(HotelCatalogServiceV2::class)->getList(['per_page'=>21,'sort'=>'name','dir'=>'asc'])->items(),
                'history_20'=>fn()=>app(BookingReadServiceV2::class)->listing(['per_page'=>20],9001)->items(),
            ]; $report=['label'=>$label,'synthetic_only'=>true,'database'=>$db->getDatabaseName(),'port'=>config('database.connections.mysql.port'),
                'fixture'=>['hotels'=>21,'active_hotels'=>11,'owned_types'=>105,'history_bookings'=>20,'payments_per_booking'=>1],'measurement'=>'warm in-process service reads; not network/load benchmark','probes'=>[]];
            foreach ($probes as $name=>$probe) {
                $probe(); $times=[]; $counts=[]; $sqlTimes=[]; $digest=null;
                for ($n=0;$n<20;$n++) {
                    $queries=[]; $recording=true; $start=hrtime(true); $rows=$probe(); $times[]=(hrtime(true)-$start)/1e6; $recording=false;
                    $counts[]=count($queries); $sqlTimes[]=array_sum(array_column($queries,'ms'));
                    $digest=hash('sha256',json_encode(array_map(fn($r)=>$r->toArray(),$rows),JSON_THROW_ON_ERROR));
                }
                sort($times); $report['probes'][$name]=['samples'=>20,'queries_min'=>min($counts),'queries_max'=>max($counts),
                    'p50_ms'=>$times[9],'p95_ms'=>$times[18],'sql_ms_mean'=>array_sum($sqlTimes)/20,'peak_process_memory_bytes'=>memory_get_peak_usage(true),'response_sha256'=>$digest,
                    'explain'=>$db->select('EXPLAIN SELECT * FROM payment WHERE booking_id=?',[1])];
                $this->assertCount($name==='catalog_21'?11:20,$rows); // Plan fixture has 21 Hotels, 11 active.
            }
            $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
            if ($reuse) {
                $baseline=json_decode(file_get_contents($runtime.'/phase2d-performance-baseline.json'),true,512,JSON_THROW_ON_ERROR);
                $this->assertSame($baseline['database'],$report['database']);
                foreach ($report['probes'] as $name=>$probe) $this->assertSame($baseline['probes'][$name]['response_sha256'],$probe['response_sha256'],'Full response must match on the identical fixture.');
            }
            file_put_contents($runtime.'/phase2d-performance-'.$label.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            $this->assertSame('+00:00',$db->selectOne('SELECT @@session.time_zone tz')->tz);
        } finally { Carbon::setTestNow(); }
    }
}
