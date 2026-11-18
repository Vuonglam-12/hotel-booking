<?php

namespace Tests\Feature;

use App\Models\RoomTypeV2;
use App\Services\BookingLifecycleServiceV2;
use App\Services\BookingService;
use App\Services\BookingServiceV2;
use App\Services\RoomAvailabilityService;
use App\Services\RoomAvailabilityServiceV2;
use App\Services\RoomTypeInventoryServiceV2;
use App\Services\RoomTypeV2BackfillService;
use App\Services\RoomTypeV2RemapService;
use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\RoomTypeV2BackendFixture;
use Tests\TestCase;

class RoomTypeV2BackendTest extends TestCase
{
    private $db;
    private int $type;
    private int $otherType;

    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); Mail::fake();
        $this->db = RoomTypeV2BackendFixture::initialize();
        app(RoomTypeV2BackfillService::class)->run('mysql', RoomTypeV2Plan::prepared());
        $this->type = $this->mapped(1, 1); $this->otherType = $this->mapped(2, 1);
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }
    private function mapped(int $hotel, int $old): int { return $this->db->table('room_type_legacy_map')->where('hotel_id', $hotel)->where('old_room_type_id', $old)->value('new_room_type_id'); }
    private function data(array $changes = []): array { return array_replace(['hotel_id' => 1, 'room_type_id' => $this->type, 'quantity' => 1, 'num_guests' => 2, 'check_in' => '2026-10-15', 'check_out' => '2026-10-17', 'payment_method' => 'cash'], $changes); }
    private function create(array $changes = [], int $customer = 9001): array { return app(BookingServiceV2::class)->create('mysql', $customer, $this->data($changes)); }
    private function available(string $in = '2026-10-15', string $out = '2026-10-17'): array { return app(RoomAvailabilityServiceV2::class)->forType('mysql', 1, $this->type, $in, $out); }
    private function life(): BookingLifecycleServiceV2 { return app(BookingLifecycleServiceV2::class); }
    private function total(int $n): array { return app(RoomTypeInventoryServiceV2::class)->changeTotal('mysql', 1, $this->type, $n); }
    private function rows(string $table): array { return $this->db->table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all(); }
    private function reject(callable $callback, string $field): void { try { $callback(); $this->fail('Expected validation rejection.'); } catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); } }
    private function stop(callable $callback, string $message): void { try { $callback(); $this->fail('Expected fail-closed refusal.'); } catch (\RuntimeException $error) { $this->assertStringContainsString($message, $error->getMessage()); } }

    private function reservation(string $status, int $quantity, string $in = '2026-10-15', string $out = '2026-10-17', ?int $type = null): int
    {
        $customer = $this->db->table('customer')->insertGetId(['name' => 'Synthetic history', 'email' => bin2hex(random_bytes(6)) . '@example.test']);
        $nights = RoomTypeV2Inventory::dates($in, $out);
        $id = $this->db->table('booking')->insertGetId(['hotel_id' => 1, 'customer_id' => $customer, 'check_in' => $in, 'check_out' => $out,
            'num_guests' => 2, 'status' => $status, 'total_price' => RoomTypeV2Inventory::money(50000000 * $quantity * $nights), 'expires_at' => '2026-10-12 07:00:00']);
        $this->db->table('booking_room')->insert(['booking_id' => $id, 'room_type_id' => $type ?? $this->type, 'quantity' => $quantity, 'nights' => $nights, 'price_at_booking' => '500000.00']);
        return $id;
    }

    public function test_availability_sweeps_each_night_and_checkout_is_exclusive(): void
    {
        $this->reservation('confirmed', 2, '2026-10-15', '2026-10-17');
        $this->reservation('pending', 2, '2026-10-16', '2026-10-18');
        $a = $this->available('2026-10-15', '2026-10-18');
        $this->assertSame([3,1,3], array_column($a['segments'], 'remaining'));
        $this->assertSame(1, $a['available_rooms']);
        $this->reject(fn() => $this->create(['quantity' => 2, 'check_out' => '2026-10-18']), 'quantity');
        $this->assertSame(5, $this->available('2026-10-18', '2026-10-19')['available_rooms']);
    }

    public function test_nonoverlapping_bookings_are_not_summed_as_one_night(): void
    {
        $this->reservation('confirmed', 4, '2026-10-15', '2026-10-16');
        $this->reservation('completed', 4, '2026-10-16', '2026-10-17');
        $this->assertSame(1, $this->available()['available_rooms']);
        $this->assertSame('pending', $this->create()['status']);
    }

    public function test_all_statuses_and_expired_pending_hold_semantics(): void
    {
        foreach (['pending','confirmed','completed','cancelled','expired'] as $offset => $status) {
            $in = Carbon::parse('2026-11-01')->addDays($offset * 3)->toDateString();
            $out = Carbon::parse($in)->addDays(2)->toDateString();
            $this->reservation($status, 2, $in, $out);
            $this->assertSame(in_array($status, ['cancelled','expired']) ? 5 : 3, $this->available($in, $out)['available_rooms']);
        }
    }

    public function test_unknown_status_bad_quantity_bad_nights_and_overcommit_fail_closed(): void
    {
        $id = $this->reservation('mystery', 1);
        $this->stop(fn() => $this->available(), 'Unrecognized');
        $this->db->table('booking')->where('id', $id)->update(['status' => 'confirmed']);
        $this->db->table('booking_room')->where('booking_id', $id)->update(['quantity' => 0]);
        $this->stop(fn() => $this->available(), 'Invalid held');
        $this->db->table('booking_room')->where('booking_id', $id)->update(['quantity' => 1, 'nights' => 3]);
        $this->stop(fn() => $this->available(), 'Invalid held');
        $this->db->table('booking_room')->where('booking_id', $id)->update(['quantity' => 6, 'nights' => 2]);
        $before = $this->rows('booking'); $this->stop(fn() => $this->available(), 'exceeds total_rooms');
        $this->assertSame($before, $this->rows('booking'));
    }

    public function test_ownership_and_inactive_hotel_type_are_rejected(): void
    {
        $this->reject(fn() => $this->create(['room_type_id' => $this->otherType]), 'room_type_id');
        $this->reject(fn() => $this->create(['room_type_id' => 1]), 'room_type_id');
        $this->db->table('room_type')->where('id', $this->type)->update(['status' => 'inactive']);
        $this->reject(fn() => $this->create(), 'room_type_id');
        $this->db->table('room_type')->where('id', $this->type)->update(['status' => 'active']);
        $this->db->table('hotel')->where('id', 1)->update(['status' => 'inactive']);
        $this->reject(fn() => $this->create(), 'hotel_id');
        $this->assertSame(0, $this->db->table('booking')->count());
    }

    public function test_quantity_one_to_ten_uses_one_line_no_room_id_and_exact_money(): void
    {
        $this->total(10);
        for ($q = 1; $q <= 10; $q++) {
            $in = Carbon::parse('2026-11-01')->addDays($q * 3)->toDateString();
            $out = Carbon::parse($in)->addDays(2)->toDateString();
            $b = $this->create(['quantity' => $q, 'num_guests' => 2 * $q, 'check_in' => $in, 'check_out' => $out, 'base_price' => '0.01']);
            $line = $this->db->table('booking_room')->where('booking_id', $b['id'])->sole();
            $this->assertNull($line->room_id); $this->assertSame($q, $line->quantity); $this->assertSame(2, $line->nights);
            $this->assertSame('500000.00', $line->price_at_booking); $this->assertSame(($q * 1000000) . '.00', $b['total_price']);
            $this->assertSame('2026-10-12 08:15:00', $b['expires_at']);
        }
        $this->assertSame(10, $this->db->table('booking')->count());
        $this->assertSame(10, $this->db->table('booking_room')->count());
        $this->assertSame(10, $this->db->table('payment')->count());
        Mail::assertNothingSent(); $this->assertSame(0, $this->db->table('invoice')->count());
    }

    public function test_request_validation_capacity_dates_and_single_type_contract(): void
    {
        foreach ([[['quantity'=>0],'quantity'],[['quantity'=>11],'quantity'],[['quantity'=>1.0],'quantity'],[['quantity'=>true],'quantity'],
            [['quantity'=>'1.0'],'quantity'],[['num_guests'=>0],'num_guests'],[['num_guests'=>1.0],'num_guests'],[['num_guests'=>3],'num_guests'],
            [['check_in'=>'2026-10-11'],'check_in'],[['check_in'=>'2026-02-30'],'check_in'],[['check_out'=>'2026-10-15'],'check_out'],
            [['room_id'=>1],'room_id'],[['room_types'=>[$this->type]],'room_types'],[['payment_method'=>'unsupported_gateway'],'payment_method']] as [$input,$field]) {
            $this->reject(fn() => $this->create($input), $field);
        }
        $this->reject(fn() => $this->create([], 99999), 'customer_id');
        $this->assertSame(0, $this->db->table('booking')->count());
        $this->assertSame('pending', $this->create(['quantity'=>2,'num_guests'=>4])['status']);
    }

    public function test_decimal_boundary_snapshot_and_banking_create_semantics(): void
    {
        $this->db->table('room_type')->where('id', $this->type)->update(['base_price'=>'9999999999.97']);
        $this->reject(fn() => $this->create(), 'total_price');
        $b = $this->create(['check_out'=>'2026-10-16', 'payment_method'=>'banking']);
        $this->assertSame('9999999999.97', $b['total_price']); $this->assertSame('pending', $b['status']);
        $this->assertSame('pending', $this->db->table('payment')->where('booking_id',$b['id'])->value('payment_status'));
        $this->db->table('room_type')->where('id',$this->type)->update(['base_price'=>'0.01','capacity'=>8]);
        $this->assertSame('9999999999.97', $this->db->table('booking_room')->where('booking_id',$b['id'])->value('price_at_booking'));
        $this->assertSame('9999999999.97', $this->db->table('booking')->where('id',$b['id'])->value('total_price'));
        $this->assertSame('500000.00', $this->db->table('room_type')->where('id',$this->otherType)->value('base_price'));
        $this->assertSame(2, $this->db->table('room_type')->where('id',$this->otherType)->value('capacity'));
    }

    public function test_duplicate_rule_and_physical_stock_are_not_used_by_v2(): void
    {
        $this->db->table('room')->where('hotel_id',1)->update(['status'=>'maintenance']);
        $this->assertSame(5,$this->available()['available_rooms']);
        $this->create(); $this->reject(fn()=>$this->create(), 'booking');
        $this->assertSame(1,$this->db->table('booking')->count());
    }

    public function test_unmapped_history_is_not_silently_omitted(): void
    {
        $this->reservation('confirmed',1,'2026-10-15','2026-10-17',1);
        $this->stop(fn()=>$this->available(), 'unmapped');
        $this->stop(fn()=>$this->create(), 'unmapped');
    }

    public function test_owned_model_gallery_uses_existing_table_and_hotel(): void
    {
        $type = RoomTypeV2::on('mysql')->with('images','hotel')->findOrFail($this->type);
        $this->assertSame('room_type',$type->getTable()); $this->assertSame(1,$type->hotel->id);
        $this->assertCount(1,$type->images); $this->assertTrue($type->images->first()->is_primary);
        $this->assertSame('image/img_room/room (1).jpg',$type->images->first()->image_url);
    }

    public function test_cancel_and_expiry_repeats_release_no_counter_twice(): void
    {
        $b = $this->create(['quantity'=>2]); $before=$this->rows('booking_room');
        $this->assertSame('cancelled',$this->life()->cancel('mysql',$b['id'],9001)['status']);
        $this->life()->cancel('mysql',$b['id'],9001); $this->assertSame(5,$this->available()['available_rooms']);
        $b = $this->create([],9002); $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->assertTrue($this->life()->expire('mysql',$b['id'])); $this->assertFalse($this->life()->expire('mysql',$b['id']));
        $this->assertSame('system',$this->db->table('booking')->where('id',$b['id'])->value('cancelled_by'));
        $this->assertSame($before[0],$this->rows('booking_room')[0]); $this->assertSame(0,$this->db->table('room_availability')->count());
        $this->assertSame(5,$this->available()['available_rooms']);
    }

    public function test_expiry_rechecks_confirmed_status_and_cancel_policy(): void
    {
        $b=$this->create(); $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->life()->transition('mysql',$b['id'],'confirmed');
        $this->assertFalse($this->life()->expire('mysql',$b['id']));
        $this->reject(fn()=>$this->life()->cancel('mysql',$b['id'],9002),'customer_id');
        $near=$this->create(['check_in'=>'2026-10-13','check_out'=>'2026-10-14'],9002);
        $this->reject(fn()=>$this->life()->cancel('mysql',$near['id'],9002),'check_in');
    }

    public function test_restore_cancelled_and_expired_checks_stock_and_preserves_snapshots(): void
    {
        $this->total(2); $b=$this->create(['quantity'=>2]); $snapshot=$this->rows('booking_room');
        $this->life()->cancel('mysql',$b['id'],9001); $new=$this->create(['quantity'=>2],9002);
        $this->reject(fn()=>$this->life()->transition('mysql',$b['id'],'confirmed'),'status');
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$b['id'])->value('status'));
        $this->life()->cancel('mysql',$new['id'],9002);
        $this->db->table('booking')->where('id',$b['id'])->update(['status'=>'expired']);
        $this->assertSame('confirmed',$this->life()->transition('mysql',$b['id'],'confirmed')['status']);
        $this->assertSame($snapshot[0],$this->rows('booking_room')[0]); $this->assertSame('2000000.00',$this->db->table('booking')->where('id',$b['id'])->value('total_price'));
    }

    public function test_stop_selling_keeps_existing_bookings_but_prevents_restore(): void
    {
        $b=$this->create(); $this->db->table('room_type')->where('id',$this->type)->update(['status'=>'inactive']);
        $this->db->table('hotel')->where('id',1)->update(['status'=>'inactive']);
        $this->assertSame(4,$this->available()['available_rooms']); $this->assertSame('pending',$this->db->table('booking')->where('id',$b['id'])->value('status'));
        $this->life()->cancel('mysql',$b['id'],9001); $this->reject(fn()=>$this->life()->transition('mysql',$b['id'],'confirmed'),'status');
        $this->db->table('hotel')->where('id',1)->update(['status'=>'active']);
        $this->reject(fn()=>$this->life()->transition('mysql',$b['id'],'confirmed'),'status');
    }

    public function test_total_rooms_protects_ongoing_and_distant_future_peak(): void
    {
        $this->reservation('confirmed',3,'2035-01-01','2035-01-03');
        $this->reservation('completed',4,'2026-10-10','2026-10-14');
        $this->reject(fn()=>$this->total(3),'total_rooms');
        $this->assertSame(4,$this->total(4)['peak_reserved_quantity']);
        $this->assertSame(10,$this->total(10)['total_rooms']); $this->reject(fn()=>$this->total(-1),'total_rooms');
        $this->assertSame(2,$this->db->table('booking')->count());
    }

    public function test_current_locking_read_sees_commit_hidden_from_old_repeatable_read_snapshot(): void
    {
        config(['database.connections.stale'=>config('database.connections.mysql')]);
        $stale=RoomTypeV2Inventory::open('stale'); $stale->db->beginTransaction();
        try {
            $this->assertSame(0,$stale->db->table('booking')->count()); $this->create(['quantity'=>2]);
            $this->assertSame(0,$stale->db->table('booking')->count());
            $stale->hotel(1,true); $type=$stale->types(1,[$this->type],true)[$this->type];
            $this->assertSame(3,$stale->inspect($type,'2026-10-15','2026-10-17',null,true)['available_rooms']);
        } finally { $stale->db->rollBack(); DB::purge('stale'); }
    }

    public function test_payment_insert_failure_rolls_back_booking_and_line(): void
    {
        $this->db->unprepared("CREATE TRIGGER v2_fail_payment BEFORE INSERT ON payment FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test payment failure'");
        try { $this->create(); $this->fail('Expected SQL failure.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('Test payment failure',$e->getMessage()); }
        foreach (['booking','booking_room','payment'] as $table) $this->assertSame(0,$this->db->table($table)->count());
        $this->assertSame(5,$this->available()['available_rooms']);
    }

    public function test_manual_payment_acknowledgement_stays_pending_and_holds_inventory(): void
    {
        $b=$this->create(['quantity'=>2,'payment_method'=>'banking']);
        $this->assertSame('pending',$this->life()->manualPayment('mysql',$b['id'],9001,'banking')['status']);
        $this->assertSame(3,$this->available()['available_rooms']);
        $this->life()->manualPayment('mysql',$b['id'],9001,'banking');
        $this->assertSame(1,$this->db->table('payment')->count()); $this->assertSame('pending',$this->db->table('payment')->value('payment_status'));
        $this->assertFalse($this->life()->expire('mysql',$b['id']));
    }

    public function test_callback_checks_amount_is_idempotent_and_never_restores_released_booking(): void
    {
        $this->total(1); $b=$this->create(); $p=$this->db->table('payment')->where('booking_id',$b['id'])->first();
        $this->reject(fn()=>$this->life()->verifiedPaymentSuccess('mysql',$b['id'],$p->id,$p->transaction_id,'0.01'),'payment');
        $this->assertTrue($this->life()->verifiedPaymentSuccess('mysql',$b['id'],$p->id,$p->transaction_id,$p->amount)['changed']);
        $this->assertFalse($this->life()->verifiedPaymentSuccess('mysql',$b['id'],$p->id,$p->transaction_id,$p->amount)['changed']);
        $this->life()->cancel('mysql',$b['id'],9001); $this->create([],9002);
        $before=$this->rows('payment'); $this->reject(fn()=>$this->life()->verifiedPaymentSuccess('mysql',$b['id'],$p->id,$p->transaction_id,$p->amount),'payment');
        $this->assertSame($before,$this->rows('payment')); $this->assertSame(0,$this->available()['available_rooms']);
    }

    private function contend(array $requests): array
    {
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))); $processes=[]; $paths=[];
        $this->db->beginTransaction(); $this->db->table('hotel')->where('id',1)->lockForUpdate()->first();
        try {
            foreach ($requests as $request) {
                $path=$runtime . '/worker-' . bin2hex(random_bytes(6)) . '.json'; file_put_contents($path,json_encode($request)); $paths[]=$path;
                $p=new Process([PHP_BINARY,base_path('tests/Support/roomtype_v2_backend_worker.php'),$path],base_path(),
                    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'AUREL_ROOMTYPE_V2_CASE_DATABASE'=>$this->db->getDatabaseName()]);
                $p->setTimeout(35); $p->start(); $processes[]=$p;
            }
            $deadline=microtime(true)+15; $waiting=0;
            do {
                $waiting=(int) array_values((array) $this->db->selectOne("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock_current_waits'"))[1];
                if ($waiting>=count($requests)) break;
                foreach ($processes as $p) if (!$p->isRunning()) throw new \RuntimeException('Worker exited before locking: ' . $p->getErrorOutput() . $p->getOutput());
                usleep(20000);
            } while (microtime(true)<$deadline);
            $this->assertGreaterThanOrEqual(count($requests),$waiting,'Both real MySQL connections must be blocked on the Hotel row lock.');
        } catch (\Throwable $error) {
            foreach ($processes as $p) if ($p->isRunning()) $p->stop(1);
            throw $error;
        } finally { $this->db->commit(); }
        $results=[];
        try {
            foreach ($processes as $i=>$p) { $p->wait(); $this->assertSame(0,$p->getExitCode(),$p->getErrorOutput()); $results[]=json_decode(file_get_contents($paths[$i].'.result'),true,512,JSON_THROW_ON_ERROR); }
        } finally { foreach ($processes as $p) if ($p->isRunning()) $p->stop(1); }
        $this->assertCount(count($requests),array_unique(array_column($results,'connection_id')));
        file_put_contents($runtime.'/concurrency-' . bin2hex(random_bytes(6)) . '.json',json_encode(['database'=>$this->db->getDatabaseName(),'row_lock_waiters'=>$waiting,'requests'=>$requests,'results'=>$results],JSON_PRETTY_PRINT));
        return $results;
    }

    public function test_two_processes_compete_for_last_room_without_orphan_rows(): void
    {
        $this->total(1); $results=$this->contend([['action'=>'create','customer_id'=>9001,'data'=>$this->data()],['action'=>'create','customer_id'=>9002,'data'=>$this->data()]]);
        $this->assertSame(1,count(array_filter($results,fn($r)=>$r['success'])));
        $loser=array_values(array_filter($results,fn($r)=>!$r['success']))[0]; $this->assertArrayHasKey('quantity',$loser['errors']);
        foreach (['booking','booking_room','payment'] as $table) $this->assertSame(1,$this->db->table($table)->count());
        $this->assertSame(1,(int) $this->db->table('booking_room')->sum('quantity')); $this->assertSame(0,$this->available()['available_rooms']);
        $this->assertSame(0,$this->db->table('booking_room as br')->leftJoin('booking as b','b.id','=','br.booking_id')->whereNull('b.id')->count());
        $this->assertSame(0,$this->db->table('payment as p')->leftJoin('booking as b','b.id','=','p.booking_id')->whereNull('b.id')->count());
    }

    public function test_two_processes_overlapping_intervals_cannot_overbook(): void
    {
        $this->total(1); $results=$this->contend([['action'=>'create','customer_id'=>9001,'data'=>$this->data()],['action'=>'create','customer_id'=>9002,'data'=>$this->data(['check_in'=>'2026-10-16','check_out'=>'2026-10-18'])]]);
        $this->assertSame(1,count(array_filter($results,fn($r)=>$r['success']))); $this->assertSame(1,$this->db->table('booking')->count());
    }

    public function test_total_reduction_competes_with_booking_under_same_locks(): void
    {
        $this->total(1); $r=$this->contend([['action'=>'create','customer_id'=>9001,'data'=>$this->data()],['action'=>'total','hotel_id'=>1,'type_id'=>$this->type,'new_total'=>0]]);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['success'])));
        $a=$this->available(); $this->assertGreaterThanOrEqual($a['booked_rooms'],$a['total_rooms']);
    }

    public function test_cancel_competes_with_callback_without_resurrection(): void
    {
        $this->total(1); $b=$this->create(); $p=$this->db->table('payment')->first();
        $this->contend([['action'=>'cancel','id'=>$b['id'],'customer_id'=>9001],['action'=>'callback','id'=>$b['id'],'payment_id'=>$p->id,'transaction_id'=>$p->transaction_id,'amount'=>$p->amount]]);
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$b['id'])->value('status')); $this->assertSame(1,$this->available()['available_rooms']);
    }

    public function test_expiry_competes_with_callback_rechecks_status(): void
    {
        $b=$this->create(); $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']); $p=$this->db->table('payment')->first();
        $this->contend([['action'=>'expire','id'=>$b['id']],['action'=>'callback','id'=>$b['id'],'payment_id'=>$p->id,'transaction_id'=>$p->transaction_id,'amount'=>$p->amount]]);
        $status=$this->db->table('booking')->where('id',$b['id'])->value('status'); $this->assertContains($status,['cancelled','confirmed']);
        $this->assertSame($status==='confirmed'?4:5,$this->available()['available_rooms']);
        $this->assertSame($status==='confirmed'?'success':'pending',$this->db->table('payment')->value('payment_status'));
    }

    public function test_restore_competes_with_new_booking_for_last_room(): void
    {
        $this->total(1); $b=$this->create(); $this->life()->cancel('mysql',$b['id'],9001);
        $r=$this->contend([['action'=>'transition','id'=>$b['id'],'target'=>'confirmed'],['action'=>'create','customer_id'=>9002,'data'=>$this->data()]]);
        $this->assertSame(1,count(array_filter($r,fn($x)=>$x['success']))); $this->assertSame(0,$this->available()['available_rooms']);
        $this->assertSame(1,$this->db->table('booking')->whereIn('status',['pending','confirmed','completed'])->count());
    }

    public function test_expiry_competes_with_new_booking_without_counter_or_overbooking(): void
    {
        $this->total(1); $b=$this->create(); $this->db->table('booking')->where('id',$b['id'])->update(['expires_at'=>'2026-10-12 07:00:00']);
        $this->contend([['action'=>'expire','id'=>$b['id']],['action'=>'create','customer_id'=>9002,'data'=>$this->data()]]);
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$b['id'])->value('status'));
        $a=$this->available(); $this->assertLessThanOrEqual(1,$a['booked_rooms']); $this->assertSame(0,$this->db->table('room_availability')->count());
    }

    private function manifest(): string { return dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/remap-'.bin2hex(random_bytes(6)).'.json'; }
    private function oldHistory(): void
    {
        $this->db->table('booking')->insert(['id'=>901,'hotel_id'=>1,'customer_id'=>9001,'check_in'=>'2026-11-10','check_out'=>'2026-11-12','num_guests'=>6,'total_price'=>'4000667.50','status'=>'confirmed']);
        $this->db->table('booking_room')->insert([
            ['id'=>1901,'booking_id'=>901,'room_type_id'=>1,'room_id'=>1,'quantity'=>1,'nights'=>2,'price_at_booking'=>'500000.00'],
            ['id'=>1902,'booking_id'=>901,'room_type_id'=>2,'room_id'=>null,'quantity'=>3,'nights'=>2,'price_at_booking'=>'500111.25'],
        ]);
        $this->db->table('payment')->insert(['booking_id'=>901,'amount'=>'4000667.50','payment_method'=>'cash','payment_status'=>'pending','transaction_id'=>'SYNTHETIC_HISTORY_901']);
        $this->db->table('booking')->insert(['id'=>902,'hotel_id'=>2,'customer_id'=>9002,'check_in'=>'2026-11-10','check_out'=>'2026-11-11','num_guests'=>4,'total_price'=>'123456.78','status'=>'completed']);
        $this->db->table('booking_room')->insert(['id'=>1903,'booking_id'=>902,'room_type_id'=>1,'room_id'=>null,'quantity'=>2,'nights'=>1,'price_at_booking'=>'61728.39']);
    }

    public function test_remap_preserves_rooms_images_history_money_and_reruns(): void
    {
        $this->oldHistory(); $path=$this->manifest(); $service=app(RoomTypeV2RemapService::class);
        $before=[]; foreach (['booking','payment','room_image','hotel_image','room_type_image','room_type_legacy_map'] as $table) $before[$table]=$this->rows($table);
        $service->prepare('mysql',RoomTypeV2Plan::prepared(),$path);
        $this->assertSame(0,$service->run('mysql',$path,true)['remapped_rooms']);
        $result=$service->run('mysql',$path); $this->assertSame(336,$result['remapped_rooms']); $this->assertSame(3,$result['remapped_booking_rooms']);
        foreach ($before as $table=>$rows) $this->assertSame($rows,$this->rows($table));
        $this->assertSame($this->type,$this->db->table('room')->where('id',1)->value('room_type_id'));
        $lines=$this->rows('booking_room'); $this->assertSame(1,$lines[0]['room_id']); $this->assertNull($lines[1]['room_id']);
        $this->assertSame([1,3,2],array_column($lines,'quantity')); $this->assertSame(['500000.00','500111.25','61728.39'],array_column($lines,'price_at_booking'));
        $this->assertSame($this->otherType,$lines[2]['room_type_id']); $this->assertNotSame($lines[0]['room_type_id'],$lines[2]['room_type_id']);
        $this->assertSame(0,$service->run('mysql',$path)['remapped_rooms']); $this->assertSame(0,$service->run('mysql',$path)['remapped_booking_rooms']);
        $this->assertSame(4,$this->available('2026-11-10','2026-11-12')['available_rooms']);
        $this->assertSame(111,$this->db->table('room_type')->count()); $this->assertSame(336,$this->db->table('room')->count());
        file_put_contents(dirname($path).'/phase2b2-reconciliation.json',json_encode(['database'=>$this->db->getDatabaseName(),'result'=>$result,'types'=>111,'legacy_types'=>6,'maps'=>105,'images'=>105,'stock'=>336,'history_money'=>'4000667.50','preserved'=>array_keys($before)],JSON_PRETTY_PRINT));
    }

    public function test_remap_sql_failure_resumes_per_hotel_and_partial_rows(): void
    {
        $this->oldHistory(); $service=app(RoomTypeV2RemapService::class); $path=$this->manifest(); $service->prepare('mysql',RoomTypeV2Plan::prepared(),$path);
        $this->db->unprepared("CREATE TRIGGER fail_remap BEFORE UPDATE ON room FOR EACH ROW BEGIN IF OLD.hotel_id=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remap checkpoint test'; END IF; END");
        try { $service->run('mysql',$path); $this->fail('Expected trigger failure.'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('Remap checkpoint test',$e->getMessage()); }
        $this->assertSame(32,$this->db->table('room')->where('room_type_id','>',6)->count());
        $this->assertSame(0,$this->db->table('room')->where('hotel_id',3)->where('room_type_id','>',6)->count());
        $this->db->unprepared('DROP TRIGGER fail_remap');
        // Simulate interrupted older tooling with a subset already mapped; immutable IDs permit safe resume.
        $room=$this->db->table('room')->where('hotel_id',3)->first(); $this->db->table('room')->where('id',$room->id)->update(['room_type_id'=>$this->mapped(3,$room->room_type_id)]);
        $r=$service->run('mysql',$path); $this->assertSame(303,$r['remapped_rooms']); $this->assertSame(0,$r['remapped_booking_rooms']);
        $this->assertSame(336,$this->db->table('room')->where('room_type_id','>',6)->count());
    }

    public function test_remap_checksum_and_manifest_drift_stop_before_writes(): void
    {
        $service=app(RoomTypeV2RemapService::class); $path=$this->manifest(); $service->prepare('mysql',RoomTypeV2Plan::prepared(),$path);
        $this->db->table('room')->where('id',1)->update(['floor'=>99]);
        $this->stop(fn()=>$service->run('mysql',$path),'checksum drift'); $this->assertSame(0,$this->db->table('room')->where('room_type_id','>',6)->count());
        $this->db->table('room')->where('id',1)->update(['floor'=>1]);
        $manifest=json_decode(file_get_contents($path),true); $manifest['checksum']=str_repeat('0',64); file_put_contents($path,json_encode($manifest));
        $this->stop(fn()=>$service->run('mysql',$path),'checksum mismatch');
    }

    public function test_remap_rejects_ambiguous_assigned_history_and_nonempty_ledger(): void
    {
        $this->oldHistory(); $service=app(RoomTypeV2RemapService::class);
        $this->db->table('booking_room')->where('id',1901)->update(['quantity'=>2]);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'Ambiguous');
        $this->db->table('booking_room')->where('id',1901)->update(['quantity'=>1,'room_id'=>99999]);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'Dangling');
        $this->db->table('booking_room')->where('id',1901)->update(['room_id'=>17]);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'Ambiguous');
        $this->db->table('booking_room')->where('id',1901)->update(['room_id'=>1]);
        $this->db->table('booking_room')->where('id',1902)->update(['room_id'=>1,'room_type_id'=>1,'quantity'=>1]);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'Ambiguous');
        $this->db->table('booking_room')->where('id',1902)->update(['room_id'=>null,'room_type_id'=>2,'quantity'=>3]);
        $this->db->table('booking')->insert(['id'=>903,'hotel_id'=>1,'customer_id'=>9003,'check_in'=>'2026-11-10','check_out'=>'2026-11-12','num_guests'=>2,'total_price'=>'1000000.00','status'=>'confirmed']);
        $this->db->table('booking_room')->insert(['booking_id'=>903,'room_type_id'=>1,'room_id'=>1,'quantity'=>1,'nights'=>2,'price_at_booking'=>'500000.00']);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'Overlapping');
        $this->db->table('room_availability')->insert(['hotel_id'=>1,'room_type_id'=>1,'date'=>'2026-10-15','total_rooms'=>5,'available_count'=>5,'booked_count'=>0]);
        $this->stop(fn()=>$service->prepare('mysql',RoomTypeV2Plan::prepared(),$this->manifest()),'ledger is nonempty');
        $this->assertSame(0,$this->db->table('room')->where('room_type_id','>',6)->count());
    }

    public function test_remap_command_is_registered_and_test_only(): void
    {
        $path=$this->manifest(); $this->assertSame(0,Artisan::call('room-types:v2-remap',['--manifest'=>$path,'--prepare'=>true]));
        $this->assertSame(0,Artisan::call('room-types:v2-remap',['--manifest'=>$path,'--dry-run'=>true]));
        $this->assertSame(0,Artisan::call('room-types:v2-remap',['--manifest'=>$path]));
        $this->assertSame(0,Artisan::call('room-types:v2-remap',['--manifest'=>$path]));
        $this->assertSame(336,$this->db->table('room')->where('room_type_id','>',6)->count());
    }

    public function test_every_v2_entry_refuses_primary_config_before_db_connection(): void
    {
        config(['database.connections.mysql.database'=>'hotel_booking']); DB::shouldReceive('connection')->never();
        foreach ([fn()=>$this->create(),fn()=>$this->available(),fn()=>$this->total(2),fn()=>$this->life()->expire('mysql',1),
            fn()=>app(RoomTypeV2RemapService::class)->run('mysql',$this->manifest()),fn()=>RoomTypeV2::on('mysql')->first()] as $call) {
            $this->stop($call,'refused before');
        }
    }

    public function test_legacy_phase1_inventory_and_cash_banking_create_remain_default(): void
    {
        $this->assertInstanceOf(RoomAvailabilityService::class,app(RoomAvailabilityService::class));
        $this->assertInstanceOf(BookingService::class,app(BookingService::class));
        $cash=app(BookingService::class)->create(9001,$this->data(['room_type_id'=>1,'quantity'=>2,'num_guests'=>4]));
        $this->assertSame('pending',$cash->status); $this->assertSame(1,$cash->bookingRooms->first()->room_type_id);
        $this->assertSame(3,app(RoomAvailabilityService::class)->forType(1,1,'2026-10-15','2026-10-17')['available_rooms']);
        $this->reject(fn()=>app(BookingService::class)->create(9002,$this->data(['room_type_id'=>1,'num_guests'=>3])),'num_guests');
        $bank=app(BookingService::class)->create(9002,$this->data(['room_type_id'=>1,'payment_method'=>'banking','check_in'=>'2026-11-01','check_out'=>'2026-11-03']));
        $this->assertSame('confirmed',$bank->status); $this->assertSame('success',$bank->payment->payment_status);
        $this->assertSame(1,$this->db->table('invoice')->count()); Mail::assertSent(\App\Mail\BookingConfirmed::class);
    }

    public function test_actual_legacy_web_callback_is_closed_without_resurrecting_released_inventory(): void
    {
        $this->total(1); $old=$this->create(); $this->life()->cancel('mysql',$old['id'],9001); $this->create([],9002);
        $p=$this->db->table('payment')->where('booking_id',$old['id'])->first();
        config(['services.vnpay.hash_secret'=>'synthetic-test-only-secret']);
        $fields=['vnp_TxnRef'=>$p->transaction_id,'vnp_ResponseCode'=>'00']; ksort($fields); $encoded=[];
        foreach ($fields as $k=>$v) $encoded[]=urlencode($k).'='.urlencode($v);
        $fields['vnp_SecureHash']=hash_hmac('sha512',implode('&',$encoded),'synthetic-test-only-secret');
        // Direct controller call, fake signature + Mail::fake; no live HTTP/gateway or real credentials.
        require_once app_path('Http/Controllers/Webcontroller.php');
        $response=app(\App\Http\Controllers\WebController::class)->paymentResult(\Illuminate\Http\Request::create('/payment/result','GET',$fields));
        $this->assertSame(503,$response->getStatusCode());
        $this->assertSame('cancelled',$this->db->table('booking')->where('id',$old['id'])->value('status'));
        $this->assertSame(2,(int) $this->db->table('booking_room')->sum('quantity'));
        $this->assertSame(0,$this->available()['available_rooms']);
        $this->assertSame('pending',$this->db->table('payment')->where('id',$p->id)->value('payment_status'));
        $this->assertTrue(method_exists(\App\Http\Controllers\PaymentController::class,'vnpayReturn'));
        $this->assertTrue(method_exists(\App\Http\Controllers\PaymentController::class,'retry'));
        Mail::assertNothingSent();
    }

    public function test_legacy_checkout_is_closed_while_physical_catalog_read_remains_available(): void
    {
        $this->actingAs(\App\Models\Customer::findOrFail(9001),'sanctum')->postJson('/api/bookings',$this->data(['room_type_id'=>1]))
            ->assertStatus(503);
        $this->assertSame(0,$this->db->table('booking')->count());
        $this->assertSame(0,$this->db->table('booking_room')->count());
        $this->assertSame(0,$this->db->table('booking_room')->where('room_type_id',$this->type)->count());
        $this->assertSame(0,$this->db->table('payment')->count());
        $this->assertSame(336,$this->db->table('room')->count());
        $this->getJson('/api/hotels/1/rooms?check_in=2026-10-15&check_out=2026-10-17')->assertOk();
    }

    public function test_phase1_admin_amenities_nullable_validation_and_filtered_kpi_regression(): void
    {
        $admin=\App\Models\Customer::findOrFail(9001); $admin->role='admin';
        // Synthetic in-memory token object only; no real token copied or Authentication code changed.
        $admin->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($admin,'admin');
        $id=$this->db->table('hotel_amenity')->insertGetId(['hotel_id'=>1,'amenity'=>'room_service']);
        $this->db->table('hotel')->where('id',1)->update(['email'=>'fixture@example.test']);
        $this->putJson('/api/admin/hotels/1',['name'=>'Synthetic renamed hotel','email'=>null])->assertOk();
        $this->assertNull($this->db->table('hotel')->where('id',1)->value('email'));
        $this->assertSame($id,$this->db->table('hotel_amenity')->where('hotel_id',1)->value('id'));
        $this->putJson('/api/admin/hotels/1',['amenities'=>['airport','room_service']])->assertOk();
        $this->assertSame(['airport_shuttle','room_service'],$this->db->table('hotel_amenity')->where('hotel_id',1)->orderBy('amenity')->pluck('amenity')->all());
        $this->putJson('/api/admin/hotels/1',['name'=>str_repeat('x',201)])->assertUnprocessable();
        $this->getJson('/api/admin/hotels?per_page=1')->assertOk()->assertJsonPath('stats.total',21);
        $this->getJson('/api/admin/hotels?status=active&per_page=1')->assertOk()->assertJsonPath('stats.total',11)->assertJsonPath('stats.inactive',0);
    }

    public function test_existing_status_unique_index_collision_is_a_reported_blocker_without_lost_history(): void
    {
        $first=$this->create(); $this->life()->cancel('mysql',$first['id'],9001);
        $second=$this->create();
        try { $this->life()->cancel('mysql',$second['id'],9001); $this->fail('Legacy duplicate-status constraint should still be enforced.'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertSame(1062,$error->errorInfo[1]); }
        $this->assertSame(['cancelled','pending'],$this->db->table('booking')->orderBy('id')->pluck('status')->all());
        $this->assertSame(2,$this->db->table('booking_room')->count()); $this->assertSame(4,$this->available()['available_rooms']);
    }
}
