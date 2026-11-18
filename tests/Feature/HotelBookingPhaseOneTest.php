<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelAmenity;
use App\Models\HotelImage;
use App\Models\Location;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Services\RoomAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HotelBookingPhaseOneTest extends TestCase
{
    private Customer $admin;
    private Customer $customer;
    private Hotel $hotel;
    private RoomType $type;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $database = DB::selectOne('SELECT DATABASE() AS name')->name;
        if ($database !== getenv('HOLIDAYVIET_TEST_DATABASE')
            || !preg_match('/^holidayviet_booking_test_[a-f0-9]{12}$/D', $database)
            || $database === getenv('HOLIDAYVIET_SOURCE_DATABASE')) throw new \RuntimeException('Unsafe write test connection.');
        Mail::fake();
        $this->prefix = 'Phase1-' . bin2hex(random_bytes(5));
        $this->admin = Customer::create(['name' => 'Fixture admin', 'email' => $this->prefix . '-admin@example.test', 'role' => 'admin', 'password_hash' => 'disabled-test-credential']);
        $this->admin->withAccessToken($this->admin->createToken('admin_token', ['admin'])->accessToken);
        $this->customer = $this->anotherCustomer();
        $location = Location::create(['name' => $this->prefix, 'country' => 'Test']);
        $this->hotel = Hotel::create(['name' => $this->prefix, 'location_id' => $location->id,
            'address' => 'Test address', 'latitude' => 10, 'longitude' => 106, 'status' => 'active', 'avg_rating' => 4.2]);
        $this->type = RoomType::create(['name' => 'Standard', 'capacity' => 2, 'base_price' => 500000]);
        foreach (['101', '102', '103'] as $number) Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->type->id,
            'room_number' => $number, 'price' => 500000, 'capacity' => 2, 'status' => 'available']);
    }

    private function anotherCustomer(): Customer
    {
        return Customer::create(['name' => 'Fixture customer', 'email' => bin2hex(random_bytes(6)) . '@example.test', 'role' => 'user', 'password_hash' => 'disabled-test-credential']);
    }

    private function data(array $changes = []): array
    {
        return array_merge(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->type->id, 'quantity' => 1,
            'num_guests' => 2, 'check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString(),
            'payment_method' => 'cash'], $changes);
    }

    private function seedBooking(string $status, int $quantity, array $changes = []): Booking
    {
        $data = $this->data($changes);
        $booking = Booking::create(['customer_id' => $this->anotherCustomer()->id, 'hotel_id' => $this->hotel->id,
            'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'num_guests' => 2,
            'total_price' => 1000000 * $quantity, 'status' => $status, 'expires_at' => $changes['expires_at'] ?? now()->addMinutes(15)]);
        BookingRoom::create(['booking_id' => $booking->id, 'room_type_id' => $this->type->id, 'quantity' => $quantity,
            'price_at_booking' => 500000, 'nights' => 2]);
        return $booking;
    }

    public function test_hotel_partial_update_preserves_amenities_and_ids(): void
    {
        foreach (['airport_shuttle', 'room_service', 'legacy_facility'] as $key) HotelAmenity::create(['hotel_id' => $this->hotel->id, 'amenity' => $key]);
        $before = $this->hotel->amenities()->pluck('id', 'amenity')->all();
        $this->actingAs($this->admin, 'admin')->putJson('/api/admin/hotels/' . $this->hotel->id, ['name' => 'Renamed'])
            ->assertOk()->assertJsonCount(3, 'hotel.amenities');
        $this->assertSame($before, $this->hotel->amenities()->pluck('id', 'amenity')->all());
    }

    public function test_explicit_amenity_edit_normalizes_alias_preserves_retained_rows_and_can_clear(): void
    {
        $service = HotelAmenity::create(['hotel_id' => $this->hotel->id, 'amenity' => 'room_service']);
        HotelAmenity::create(['hotel_id' => $this->hotel->id, 'amenity' => 'wifi']);
        $this->actingAs($this->admin, 'admin')->putJson('/api/admin/hotels/' . $this->hotel->id,
            ['amenities' => ['airport', 'airport_shuttle', 'room_service']])->assertOk()->assertJsonCount(2, 'hotel.amenities');
        $this->assertSame($service->id, $this->hotel->amenities()->where('amenity', 'room_service')->value('id'));
        $this->assertSame(['airport_shuttle', 'room_service'], $this->hotel->amenities()->orderBy('amenity')->pluck('amenity')->all());
        $this->putJson('/api/admin/hotels/' . $this->hotel->id, ['amenities' => []])->assertOk()->assertJsonCount(0, 'hotel.amenities');
    }

    #[DataProvider('invalidHotelInput')]
    public function test_invalid_hotel_input_is_rejected_before_writes(array $input, string $field): void
    {
        HotelAmenity::create(['hotel_id' => $this->hotel->id, 'amenity' => 'wifi']);
        $before = $this->hotel->fresh()->getAttributes();
        $this->actingAs($this->admin, 'admin')->putJson('/api/admin/hotels/' . $this->hotel->id, $input)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $this->hotel->fresh()->getAttributes());
        $this->assertSame(['wifi'], $this->hotel->amenities()->pluck('amenity')->all());
    }

    public static function invalidHotelInput(): array
    {
        return [
            'name' => [['name' => str_repeat('a', 201)], 'name'],
            'address' => [['address' => str_repeat('a', 301)], 'address'],
            'phone' => [['phone' => str_repeat('1', 21)], 'phone'],
            'email' => [['email' => str_repeat('a', 90) . '@example.test'], 'email'],
            'time' => [['check_in_time' => '25:99'], 'check_in_time'],
            'latitude' => [['latitude' => 91], 'latitude'],
            'amenity-null' => [['amenities' => null], 'amenities'],
            'amenity-string' => [['amenities' => 'wifi'], 'amenities'],
            'unsupported' => [['amenities' => ['invented_facility']], 'amenities.0'],
            'text-bytes' => [['description' => str_repeat('đ', 32768)], 'description'],
            'description-array' => [['description' => ['invalid']], 'description'],
        ];
    }

    public function test_create_and_update_hotel_fields_match_schema_and_clear_nullable_fields(): void
    {
        $input = ['name' => str_repeat('a', 200), 'address' => str_repeat('b', 300), 'location_id' => $this->hotel->location_id,
            'star_rating' => 3, 'phone' => '0123456789', 'description' => 'Test description', 'email' => 'fixture@example.test',
            'check_in_time' => '15:30', 'check_out_time' => '11:45:00', 'amenities' => ['room_service', 'airport']];
        $response = $this->actingAs($this->admin, 'admin')->postJson('/api/admin/hotels', $input)->assertCreated();
        $id = $response->json('hotel.id');
        $this->assertDatabaseHas('hotel', ['id' => $id, 'latitude' => 0, 'longitude' => 0, 'check_in_time' => '15:30:00']);
        $this->putJson('/api/admin/hotels/' . $id, ['phone' => null, 'email' => null, 'description' => null])->assertOk();
        $this->assertDatabaseHas('hotel', ['id' => $id, 'phone' => null, 'email' => null, 'description' => null]);
        $this->assertSame(2, HotelAmenity::where('hotel_id', $id)->count());
    }

    public function test_hotel_status_and_kpis_use_full_filtered_scope_across_pages(): void
    {
        foreach (['inactive' => 3.2, 'closed' => 5] as $status => $rating) Hotel::create(['name' => $this->prefix . '-' . $status,
            'location_id' => $this->hotel->location_id, 'address' => 'Test', 'latitude' => 10, 'longitude' => 106, 'status' => $status, 'avg_rating' => $rating]);
        $url = '/api/admin/hotels?search=' . $this->prefix . '&per_page=1';
        $a = $this->actingAs($this->admin, 'admin')->getJson($url)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('stats.total', 3)->assertJsonPath('stats.active', 1)->assertJsonPath('stats.inactive', 1)->assertJsonPath('stats.closed', 1);
        $b = $this->getJson($url . '&page=2')->assertOk();
        $this->assertSame($a->json('stats'), $b->json('stats'));
        $this->getJson($url . '&status=closed')->assertJsonPath('stats.total', 1)->assertJsonPath('stats.closed', 1)->assertJsonPath('stats.inactive', 0);
        $this->getJson($url . '&search=missing-unique-name')->assertJsonPath('stats.total', 0)->assertJsonPath('stats.average_rating', null);
        $this->putJson('/api/admin/hotels/' . $this->hotel->id . '/status', ['status' => 'inactive'])->assertOk();
        $this->getJson($url)->assertJsonPath('stats.active', 0)->assertJsonPath('stats.inactive', 2);
    }

    public function test_existing_physical_numbers_do_not_break_room_type_creation(): void
    {
        $this->actingAs($this->admin, 'admin')->postJson('/api/admin/hotels/' . $this->hotel->id . '/room-types',
            ['name' => 'Deluxe', 'base_price' => 800000, 'capacity' => 3, 'floor_start' => 1, 'room_count' => 3])
            ->assertCreated()->assertJsonPath('rooms_created', 3);
        $this->assertSame(6, $this->hotel->rooms()->count());
        $this->assertSame(6, $this->hotel->rooms()->distinct()->count('room_number'));
    }

    public function test_room_type_creation_rolls_back_all_rows_when_one_room_fails(): void
    {
        $types = RoomType::count();$rooms = Room::count();
        $trigger = 'phase1_fail_' . bin2hex(random_bytes(4));
        DB::unprepared('CREATE TRIGGER ' . $trigger . " BEFORE INSERT ON room FOR EACH ROW BEGIN IF NEW.room_number = '105' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'isolated fixture failure'; END IF; END");
        try {
            $this->withoutExceptionHandling()->actingAs($this->admin, 'admin')->postJson('/api/admin/hotels/' . $this->hotel->id . '/room-types',
                ['name' => 'Atomic', 'base_price' => 800000, 'capacity' => 2, 'room_count' => 3]);
            $this->fail('Expected isolated DB failure');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('isolated fixture failure', $e->getMessage());
            $this->assertSame($types, RoomType::count());$this->assertSame($rooms, Room::count());
        } finally { DB::unprepared('DROP TRIGGER ' . $trigger); }
    }

    #[DataProvider('quantities')]
    public function test_quantity_capacity_and_history_snapshot(int $quantity): void
    {
        HotelImage::create(['hotel_id' => $this->hotel->id, 'image_url' => 'image/logothuonghieu.png', 'is_primary' => 1]);
        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings', $this->data(['quantity' => $quantity, 'num_guests' => 2 * $quantity]))->assertCreated();
        $id = $response->json('booking.id');
        $this->type->update(['base_price' => 900000]);
        $history = $this->getJson('/api/bookings/' . $id)->assertOk()->assertJsonPath('booking_rooms.0.quantity', $quantity);
        $this->assertEquals(500000, $history->json('booking_rooms.0.price_at_booking'));
        $this->assertEquals(1000000 * $quantity, $history->json('total_price'));
        $this->assertStringEndsWith('/image/logothuonghieu.png', $history->json('hotel.cover_image_url'));
        $this->getJson('/api/bookings/my')->assertOk()->assertJsonPath('data.0.booking_rooms.0.quantity', $quantity);
    }

    public static function quantities(): array { return [[1], [2], [3]]; }

    public function test_capacity_cannot_be_bypassed_at_booking_or_availability(): void
    {
        $before = Booking::count();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings', $this->data(['num_guests' => 99]))
            ->assertUnprocessable()->assertJsonValidationErrors('num_guests');
        try { app(BookingService::class)->create($this->customer->id, $this->data(['num_guests' => 99])); $this->fail('Service bypass'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('num_guests', $e->errors()); }
        $query = http_build_query($this->data(['num_guests' => 99]));
        $this->actingAs($this->admin, 'admin')->getJson('/api/admin/rooms/availability?' . $query)->assertUnprocessable()->assertJsonValidationErrors('num_guests');
        $this->getJson('/api/hotels/' . $this->hotel->id . '/rooms?' . $query)->assertUnprocessable()->assertJsonValidationErrors('num_guests');
        $this->getJson('/api/hotels/' . $this->hotel->id . '/rooms?' . http_build_query($this->data(['quantity' => 2, 'num_guests' => 4])))
            ->assertOk()->assertJsonCount(3);
        $this->assertSame($before, Booking::count());
    }

    public function test_specific_room_uses_its_actual_capacity(): void
    {
        $room = $this->hotel->rooms()->first();$room->update(['capacity' => 1]);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings', $this->data(['room_id' => $room->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('num_guests');
    }

    public function test_wishlist_photo_uses_existing_non_primary_image_and_fixed_placeholder(): void
    {
        HotelImage::create(['hotel_id' => $this->hotel->id, 'image_url' => 'image/logothuonghieu.png', 'is_primary' => 0]);
        \App\Models\Wishlist::create(['hotel_id' => $this->hotel->id, 'customer_id' => $this->customer->id]);
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/wishlist')->assertOk();
        $this->assertStringEndsWith('/image/logothuonghieu.png', $response->json('0.hotel.cover_image_url'));
        HotelImage::where('hotel_id', $this->hotel->id)->delete();
        $this->getJson('/api/wishlist')->assertOk()->assertJsonPath('0.hotel.cover_image_url', \App\Support\AccommodationImage::placeholder());
    }

    public function test_admin_booking_details_include_type_and_quantity_for_unassigned_booking(): void
    {
        $booking = $this->seedBooking('confirmed', 3);
        $response = $this->actingAs($this->admin, 'admin')->getJson('/api/admin/bookings?hotel_id=' . $this->hotel->id)->assertOk();
        $response->assertJsonPath('data.0.id', $booking->id)->assertJsonPath('data.0.room_quantity', 3)
            ->assertJsonPath('data.0.room_type_summary', 'Standard × 3 phòng')->assertJsonPath('data.0.booking_rooms.0.quantity', 3);
        $this->assertEquals(3000000, $response->json('data.0.total_price'));
    }

    public function test_booking_total_exceeding_decimal_column_is_rejected_before_insert(): void
    {
        $this->type->update(['base_price' => 2000000000]);
        $before = Booking::count();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings', $this->data(['quantity' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors('total_price');
        $this->assertSame($before, Booking::count());
    }

    public function test_availability_requires_guest_quantity_and_rejects_stock_shortfall(): void
    {
        $this->actingAs($this->admin, 'admin')->getJson('/api/admin/rooms/availability?' . http_build_query(array_diff_key($this->data(), array_flip(['quantity', 'num_guests']))))
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'num_guests']);
        $this->getJson('/api/admin/rooms/availability?' . http_build_query($this->data(['quantity' => 4])))
            ->assertOk()->assertJsonPath('available', false)->assertJsonPath('available_rooms', 3);
    }

    public function test_pending_confirmed_cancelled_and_checkout_boundary(): void
    {
        $a = $this->seedBooking('pending', 1);$b = $this->seedBooking('confirmed', 1);
        $service = app(RoomAvailabilityService::class);$d = $this->data();
        $this->assertSame(1, $service->forType($this->hotel->id, $this->type->id, $d['check_in'], $d['check_out'])['available_rooms']);
        $a->update(['status' => 'cancelled']);
        $this->assertSame(2, $service->forType($this->hotel->id, $this->type->id, $d['check_in'], $d['check_out'])['available_rooms']);
        $this->assertSame(3, $service->forType($this->hotel->id, $this->type->id, $d['check_out'], now()->addDays(9)->toDateString())['available_rooms']);
    }

    public function test_direct_legacy_inventory_reduction_exposes_shortfall_and_preserves_booking(): void
    {
        $booking = $this->seedBooking('confirmed', 3);
        $this->hotel->rooms()->orderBy('id')->limit(2)->delete();
        $d = $this->data();$stock = app(RoomAvailabilityService::class)->forType($this->hotel->id, $this->type->id, $d['check_in'], $d['check_out']);
        $this->assertSame(1, $stock['total_rooms']);$this->assertSame(3, $stock['booked_rooms']);$this->assertSame(0, $stock['available_rooms']);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(3, $booking->bookingRooms->first()->quantity);
    }

    public function test_restoring_cancelled_booking_cannot_exceed_current_inventory(): void
    {
        $old = $this->seedBooking('cancelled', 2);$this->seedBooking('confirmed', 2);
        $this->actingAs($this->admin, 'admin')->putJson('/api/admin/bookings/' . $old->id . '/status', ['status' => 'confirmed'])->assertUnprocessable();
        $this->assertSame('cancelled', $old->fresh()->status);
    }

    public function test_restoring_booking_when_inventory_fits_keeps_snapshot(): void
    {
        $old = $this->seedBooking('cancelled', 2);$this->type->update(['base_price' => 900000]);
        $this->actingAs($this->admin, 'admin')->putJson('/api/admin/bookings/' . $old->id . '/status', ['status' => 'confirmed'])->assertOk();
        $this->assertEquals(2000000, $old->fresh()->total_price);$this->assertEquals(500000, $old->bookingRooms->first()->price_at_booking);
    }

    #[DataProvider('overlapScenarios')]
    public function test_concurrent_booking_requests_do_not_oversell(bool $partialOverlap): void
    {
        $this->hotel->rooms()->orderBy('id')->limit(2)->delete();
        $directory = $this->barrierDirectory();$workers = [];
        try {
            $a = $workers[] = $this->worker('booking_a', $directory, ['pause_booking' => true]);
            $this->waitFor('booking_a-locked', $directory, $a);
            $data = $partialOverlap ? $this->data(['check_in' => now()->addDays(6)->toDateString(), 'check_out' => now()->addDays(8)->toDateString()]) : $this->data();
            $b = $workers[] = $this->worker('booking_b', $directory, ['customer_id' => $this->anotherCustomer()->id, 'data' => $data]);
            $this->waitFor('booking_b-connected', $directory, $b);
            $this->waitUntil(fn()=>DB::selectOne('SELECT COUNT(*) AS waiting FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID=?', [(int)file_get_contents($directory.'/booking_b-connected')])->waiting > 0, $b);
            file_put_contents($directory . '/release-booking_a', '1');$a->wait();$b->wait();
            $this->assertTrue($this->workerResult('booking_a', $directory)['ok']);$this->assertFalse($this->workerResult('booking_b', $directory)['ok']);
            $this->assertSame(1, Booking::where('hotel_id', $this->hotel->id)->whereIn('status', ['pending', 'confirmed'])->count());
        } finally { $this->cleanup($workers, $directory); }
    }

    public static function overlapScenarios(): array { return ['same stay' => [false], 'partial overlap' => [true]]; }

    public function test_expiry_cannot_cancel_a_booking_confirmed_after_the_candidate_scan(): void
    {
        $old = $this->seedBooking('pending', 1, ['expires_at' => now()->subMinute()]);
        $directory=$this->barrierDirectory();$workers=[];
        try {
            $worker=$workers[]=$this->worker('expire', $directory, ['pause_scan'=>true]);$this->waitFor('expire-scanned', $directory, $worker);
            $this->actingAs($this->admin, 'admin')->putJson('/api/admin/bookings/'.$old->id.'/status',['status'=>'confirmed'])->assertOk();
            file_put_contents($directory.'/release-expire','1');$worker->wait();
            $this->assertTrue($this->workerResult('expire',$directory)['ok']);$this->assertSame('confirmed',$old->fresh()->status);
        } finally {$this->cleanup($workers,$directory);}
    }

    public function test_create_competing_with_expiry_preserves_inventory(): void
    {
        $this->hotel->rooms()->orderBy('id')->limit(2)->delete();$old=$this->seedBooking('pending',1,['expires_at'=>now()->subMinute()]);
        $directory=$this->barrierDirectory();$workers=[];
        try {
            $a=$workers[]=$this->worker('booking_a',$directory,['pause_booking'=>true]);$this->waitFor('booking_a-locked',$directory,$a);
            $e=$workers[]=$this->worker('expire',$directory,[]);$this->waitFor('expire-connected',$directory,$e);
            file_put_contents($directory.'/release-booking_a','1');$a->wait();$e->wait();
            $this->assertTrue($this->workerResult('expire',$directory)['ok']);$this->assertSame('cancelled',$old->fresh()->status);
            if(!$this->workerResult('booking_a',$directory)['ok'])app(BookingService::class)->create($this->customer->id,$this->data());
            $this->assertSame(1,Booking::where('hotel_id',$this->hotel->id)->whereIn('status',['pending','confirmed'])->count());
        } finally {$this->cleanup($workers,$directory);}
    }

    public function test_expiry_does_not_credit_a_ledger_that_booking_creation_does_not_debit(): void
    {
        $old = $this->seedBooking('pending', 1, ['expires_at' => now()->subMinute()]);
        DB::table('room_availability')->insert(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->type->id,
            'date' => $this->data()['check_in'], 'total_rooms' => 3, 'available_count' => 3, 'booked_count' => 0]);
        $this->artisan('bookings:cancel-expired')->assertSuccessful();
        $this->assertSame('cancelled', $old->fresh()->status);
        $ledger = DB::table('room_availability')->where('hotel_id', $this->hotel->id)->first();
        $this->assertSame(3, $ledger->available_count);$this->assertSame(0, $ledger->booked_count);
    }

    private function barrierDirectory(): string { $path=storage_path('framework/testing/phase1-gate-'.bin2hex(random_bytes(6)));mkdir($path,0700,true);return $path; }
    private function worker(string $job,string $directory,array $changes): Process
    {
        $payload=array_merge(['customer_id'=>$this->customer->id,'data'=>$this->data()],$changes);
        $process=new Process([PHP_BINARY,base_path('tests/Support/hotel_booking_phase1_worker.php'),getenv('HOLIDAYVIET_TEST_DATABASE'),$job,$directory,json_encode($payload)],base_path());
        $process->setTimeout(25);$process->start();return $process;
    }
    private function waitFor(string $name,string $directory,Process $worker): void { $this->waitUntil(fn()=>is_file($directory.'/'.$name),$worker); }
    private function waitUntil(callable $condition,Process $worker): void
    {
        $end=microtime(true)+10;do{clearstatcache();if($condition())return;if(!$worker->isRunning())$this->fail('Worker stopped: '.$worker->getErrorOutput());usleep(20000);}while(microtime(true)<$end);
        $this->fail('Worker barrier timed out: '.$worker->getErrorOutput());
    }
    private function workerResult(string $job,string $directory): array {return json_decode(file_get_contents($directory.'/'.$job.'-result.json'),true,512,JSON_THROW_ON_ERROR);}
    private function cleanup(array $workers,string $directory): void
    {
        foreach($workers as $worker)if($worker->isRunning())$worker->stop(0.2);
        $resolved=realpath($directory);$root=realpath(storage_path('framework/testing'));
        if(!$resolved||!$root||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR.'phase1-gate-'))throw new \RuntimeException('Unsafe fixture cleanup.');
        foreach(glob($resolved.'/*') as $file)if(is_file($file))unlink($file);rmdir($resolved);
    }
}
