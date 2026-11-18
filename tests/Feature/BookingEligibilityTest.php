<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceController;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingEligibilityTest extends TestCase
{
    private Customer $customer;
    private Customer $admin;
    private Hotel $activeHotel;
    private Hotel $inactiveHotel;
    private Hotel $otherHotel;
    private RoomType $roomType;
    private RoomType $otherType;
    private Room $activeRoom;
    private Room $inactiveRoom;
    private Room $otherRoom;
    private Room $wrongTypeRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::selectOne('SELECT DATABASE() AS name')->name;
        if ($database !== getenv('HOLIDAYVIET_TEST_DATABASE')
            || !preg_match('/^holidayviet_booking_test_[a-f0-9]{12}$/D', $database)
            || $database === getenv('HOLIDAYVIET_SOURCE_DATABASE')) {
            throw new \RuntimeException('Write tests require a separate MySQL database.');
        }

        Mail::fake();
        $suffix = bin2hex(random_bytes(5));
        $this->customer = Customer::create([
            'name' => 'Booking test customer',
            'email' => 'customer-' . $suffix . '@example.test',
            'password_hash' => Hash::make('test-only-password'),
            'role' => 'user',
        ]);
        $this->admin = Customer::create([
            'name' => 'Booking test admin',
            'email' => 'admin-' . $suffix . '@example.test',
            'password_hash' => Hash::make('test-only-password'),
            'role' => 'admin',
        ]);
        $this->admin->withAccessToken($this->admin->createToken('admin_token', ['admin'])->accessToken);
        $location = Location::create(['name' => 'Test ' . $suffix, 'region' => 'Test', 'country' => 'Test']);
        $this->activeHotel = $this->makeHotel($location, 'Active ' . $suffix, 'active');
        $this->inactiveHotel = $this->makeHotel($location, 'Inactive ' . $suffix, 'inactive');
        $this->otherHotel = $this->makeHotel($location, 'Other ' . $suffix, 'active');
        $this->roomType = RoomType::create(['name' => 'Standard ' . $suffix, 'capacity' => 2, 'base_price' => 500000]);
        $this->otherType = RoomType::create(['name' => 'Deluxe ' . $suffix, 'capacity' => 2, 'base_price' => 800000]);
        $this->activeRoom = $this->makeRoom($this->activeHotel, $this->roomType, '101');
        $this->makeRoom($this->activeHotel, $this->roomType, '102');
        $this->makeRoom($this->activeHotel, $this->roomType, '103');
        $this->inactiveRoom = $this->makeRoom($this->inactiveHotel, $this->roomType, '101');
        $this->otherRoom = $this->makeRoom($this->otherHotel, $this->roomType, '101');
        $this->wrongTypeRoom = $this->makeRoom($this->activeHotel, $this->otherType, '201');
    }

    public function test_active_hotel_accepts_room_type_booking_with_existing_cash_behavior(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', $this->data(['quantity' => 2]));

        $response->assertCreated()->assertJsonPath('booking.status', 'pending');
        $booking = Booking::findOrFail($response->json('booking.id'));
        $this->assertEquals(2000000, $booking->total_price);
        $this->assertEquals(2, $booking->bookingRooms->first()->quantity);
        $this->assertSame('pending', $booking->payment->payment_status);
        $this->assertSame('cash', $booking->payment->payment_method);
        $this->assertDatabaseHas('customer_notifications', ['customer_id' => $this->customer->id, 'type' => 'booking_pending']);
        Mail::assertNothingSent();
    }

    public function test_active_hotel_accepts_a_specific_room(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', $this->data(['room_id' => $this->activeRoom->id]))
            ->assertCreated()->assertJsonPath('booking.booking_rooms.0.room_id', $this->activeRoom->id);
    }

    public function test_active_banking_behavior_is_unchanged(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', $this->data(['payment_method' => 'banking']));
        $response->assertCreated()->assertJsonPath('booking.status', 'confirmed');
        $booking = Booking::findOrFail($response->json('booking.id'));
        $this->assertSame('success', $booking->payment->payment_status);
        $this->assertDatabaseHas('invoice', ['booking_id' => $booking->id]);
        Mail::assertSent(BookingConfirmed::class);
    }

    public function test_inactive_hotel_is_rejected_without_side_effects(): void
    {
        $this->assertRejected($this->data(['hotel_id' => $this->inactiveHotel->id, 'payment_method' => 'banking']), 'hotel_id');
    }

    public function test_inactive_hotel_specific_room_is_rejected(): void
    {
        $this->assertRejected($this->data(['hotel_id' => $this->inactiveHotel->id, 'room_id' => $this->inactiveRoom->id]), 'hotel_id');
    }

    public function test_closed_hotel_is_also_rejected(): void
    {
        $this->activeHotel->update(['status' => 'closed']);
        $this->assertRejected($this->data(), 'hotel_id');
    }

    public function test_active_hotel_cannot_borrow_a_room_from_an_inactive_hotel(): void
    {
        $this->assertRejected($this->data(['room_id' => $this->inactiveRoom->id]), 'room_id');
    }

    public function test_active_hotel_cannot_borrow_a_room_from_another_active_hotel(): void
    {
        $this->assertRejected($this->data(['room_id' => $this->otherRoom->id]), 'room_id');
    }

    public function test_room_must_match_the_requested_room_type(): void
    {
        $this->assertRejected($this->data(['room_id' => $this->wrongTypeRoom->id]), 'room_id');
    }

    public function test_maintenance_room_is_rejected(): void
    {
        $this->activeRoom->update(['status' => 'maintenance']);
        $this->assertRejected($this->data(['room_id' => $this->activeRoom->id]), 'room_id');
    }

    public function test_specific_room_cannot_represent_multiple_rooms(): void
    {
        $this->assertRejected($this->data(['room_id' => $this->activeRoom->id, 'quantity' => 2]), 'quantity');
    }

    public function test_service_rechecks_inactive_status_without_controller_validation(): void
    {
        $this->assertServiceRejected($this->data(['hotel_id' => $this->inactiveHotel->id, 'payment_method' => 'banking']), 'hotel_id');
    }

    public function test_service_rechecks_room_ownership_without_controller_validation(): void
    {
        $this->assertServiceRejected($this->data(['room_id' => $this->inactiveRoom->id]), 'room_id');
        $this->assertServiceRejected($this->data(['room_id' => $this->wrongTypeRoom->id]), 'room_id');
        $this->assertServiceRejected($this->data(['room_id' => $this->activeRoom->id, 'quantity' => 2]), 'quantity');
    }

    public function test_room_type_must_exist_at_the_selected_hotel(): void
    {
        $this->assertRejected($this->data(['hotel_id' => $this->otherHotel->id, 'room_type_id' => $this->otherType->id]), 'room_type_id');
    }

    #[DataProvider('invalidServiceInputs')]
    public function test_service_rejects_invalid_inputs_before_side_effects(array $overrides, string $field): void
    {
        $this->assertServiceRejected($this->data(array_merge(['payment_method' => 'banking'], $overrides)), $field);
    }

    public static function invalidServiceInputs(): array
    {
        return [
            'zero quantity' => [['quantity' => 0], 'quantity'],
            'negative quantity' => [['quantity' => -1], 'quantity'],
            'fractional quantity' => [['quantity' => 1.5], 'quantity'],
            'fractional string quantity' => [['quantity' => '1.5'], 'quantity'],
            'boolean quantity' => [['quantity' => true], 'quantity'],
            'missing quantity' => [['quantity' => null], 'quantity'],
            'checkout equals checkin' => [['check_in' => '2030-01-03', 'check_out' => '2030-01-03'], 'check_out'],
            'checkout before checkin' => [['check_in' => '2030-01-03', 'check_out' => '2030-01-01'], 'check_out'],
            'invalid checkin' => [['check_in' => '2030-02-30', 'check_out' => '2030-03-03'], 'check_in'],
            'invalid checkout' => [['check_in' => '2030-02-28', 'check_out' => '2030-02-30'], 'check_out'],
            'missing checkin' => [['check_in' => null], 'check_in'],
            'missing checkout' => [['check_out' => null], 'check_out'],
            'past checkin' => [['check_in' => '2000-01-01', 'check_out' => '2000-01-03'], 'check_in'],
        ];
    }

    public function test_service_accepts_positive_integer_string_without_controller_validation(): void
    {
        $booking = $this->app->make(BookingService::class)->create($this->customer->id, $this->data(['quantity' => '2']));
        $this->assertEquals(2, $booking->bookingRooms->first()->quantity);
        $this->assertEquals(2000000, $booking->total_price);
    }

    public function test_availability_requires_hotel_id(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->getJson('/api/admin/rooms/availability?' . http_build_query([
                'room_type_id' => $this->roomType->id, 'check_in' => $this->data()['check_in'], 'check_out' => $this->data()['check_out'],
            ]))->assertUnprocessable()->assertJsonValidationErrors('hotel_id');
    }

    public function test_availability_is_scoped_to_one_hotel(): void
    {
        $this->availability()->assertOk()->assertJsonPath('total_rooms', 3)
            ->assertJsonPath('available_rooms', 3)->assertJsonPath('booked_rooms', 0)->assertJsonPath('available', true);
    }

    public function test_inactive_hotel_has_no_bookable_availability_without_changing_rooms(): void
    {
        $roomBefore = $this->inactiveRoom->fresh()->getAttributes();
        $this->availability($this->inactiveHotel)->assertOk()->assertJsonPath('available', false)->assertJsonPath('available_rooms', 0);
        $this->assertSame($roomBefore, $this->inactiveRoom->fresh()->getAttributes());
    }

    public function test_availability_includes_unassigned_quantity_bookings_and_respects_checkout_boundary(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 2);
        $this->seedBooking($this->otherHotel, 'confirmed', 1);
        $this->seedBooking($this->activeHotel, 'cancelled', 1);
        $this->seedBooking($this->activeHotel, 'expired', 1);
        $this->seedBooking($this->activeHotel, 'completed', 1, [
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => $this->data()['check_in'],
        ]);
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 1)
            ->assertJsonPath('booked_rooms', 2)->assertJsonPath('available', true);
    }

    public function test_specific_room_cannot_bypass_room_type_quantity_reservations(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 3);
        $before = $this->sideEffectCounts();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/bookings', $this->data(['room_id' => $this->activeRoom->id]))
            ->assertUnprocessable();
        $this->assertSame($before, $this->sideEffectCounts());
    }

    public function test_non_overlapping_bookings_do_not_accumulate_across_the_requested_stay(): void
    {
        $start = now()->addDays(5)->toDateString();
        $middle = now()->addDays(6)->toDateString();
        $end = now()->addDays(7)->toDateString();
        $this->seedBooking($this->activeHotel, 'confirmed', 2, ['check_in' => $start, 'check_out' => $middle]);
        $this->seedBooking($this->activeHotel, 'confirmed', 2, ['check_in' => $middle, 'check_out' => $end]);

        $this->availability()->assertOk()->assertJsonPath('available_rooms', 1)->assertJsonPath('booked_rooms', 2);
        // A different customer can reserve the remaining room for the entire interval.
        $booking = $this->app->make(BookingService::class)->create($this->admin->id, $this->data());
        $this->assertEquals(1, $booking->bookingRooms->first()->quantity);
        $this->availability()->assertJsonPath('available_rooms', 0);
    }

    public function test_overlapping_bookings_use_peak_occupancy_during_any_night(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 2);
        $this->seedBooking($this->activeHotel, 'pending', 1, [
            'check_in' => now()->addDays(6)->toDateString(), 'check_out' => now()->addDays(8)->toDateString(),
        ]);
        $dates = ['check_out' => now()->addDays(8)->toDateString()];
        $this->availability(null, $dates)->assertOk()->assertJsonPath('available_rooms', 0)->assertJsonPath('booked_rooms', 3);
        $rooms = $this->roomList($dates)->assertOk()->assertJsonCount(3)->json();
        foreach ($rooms as $room) {
            $this->assertFalse($room['bookable']);
            $this->assertSame(0, $room['available_rooms']);
        }
    }

    public function test_duplicate_rows_do_not_count_a_physical_room_twice(): void
    {
        $booking = $this->seedBooking($this->activeHotel, 'confirmed', 1, ['room_id' => $this->activeRoom->id]);
        $line = $booking->bookingRooms()->first()->getAttributes();
        unset($line['id']);
        BookingRoom::create($line);
        $secondRoom = $this->activeHotel->rooms()->where('room_type_id', $this->roomType->id)
            ->where('id', '!=', $this->activeRoom->id)->firstOrFail();
        BookingRoom::create(array_merge($line, ['room_id' => $secondRoom->id]));

        $this->availability()->assertOk()->assertJsonPath('available_rooms', 1)->assertJsonPath('booked_rooms', 2);
        $this->roomList()->assertJsonPath('0.bookable', false)->assertJsonPath('0.occupancy_status', 'booked');
    }

    public function test_overlapping_legacy_bookings_for_one_physical_room_count_once(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 1, ['room_id' => $this->activeRoom->id]);
        $this->seedBooking($this->activeHotel, 'pending', 1, ['room_id' => $this->activeRoom->id]);
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 2)->assertJsonPath('booked_rooms', 1);
        $this->roomList()->assertJsonPath('0.occupancy_status', 'booked')->assertJsonPath('0.bookable', false);
    }

    public function test_assigned_and_unassigned_lines_of_one_booking_consume_each_unit_once(): void
    {
        $booking = $this->seedBooking($this->activeHotel, 'confirmed', 1, ['room_id' => $this->activeRoom->id]);
        BookingRoom::create(['booking_id' => $booking->id, 'room_type_id' => $this->roomType->id,
            'room_id' => null, 'price_at_booking' => 500000, 'nights' => 2, 'quantity' => 1]);
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 1)->assertJsonPath('booked_rooms', 2);
        $this->roomList()->assertJsonPath('0.bookable', false)->assertJsonPath('1.available_rooms', 1);
    }

    public function test_a_room_outside_available_stock_is_not_subtracted_again(): void
    {
        $this->activeRoom->update(['status' => 'locked']);
        $this->seedBooking($this->activeHotel, 'confirmed', 1, ['room_id' => $this->activeRoom->id]);
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 2)->assertJsonPath('booked_rooms', 1);
        $this->roomList()->assertJsonPath('0.physical_status', 'locked')->assertJsonPath('0.bookable', false);
    }

    public function test_sold_out_unassigned_bookings_do_not_invent_physical_assignments(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 3);
        $before = $this->sideEffectCounts();
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 0);
        $rooms = $this->roomList()->assertOk()->assertJsonCount(3)->json();
        foreach ($rooms as $room) {
            $this->assertSame('available', $room['physical_status']);
            $this->assertSame('available', $room['occupancy_status']);
            $this->assertSame('unavailable', $room['status']);
            $this->assertFalse($room['bookable']);
            $this->assertSame(0, $room['available_rooms']);
            $this->assertSame(3, $room['booked_rooms']);
        }
        $this->assertSame($before, $this->sideEffectCounts());
        $this->assertSame(3, $this->activeHotel->rooms()->where('room_type_id', $this->roomType->id)
            ->where('status', 'available')->count());
    }

    public function test_partial_unassigned_capacity_is_shared_without_arbitrarily_locking_rooms(): void
    {
        $this->seedBooking($this->activeHotel, 'confirmed', 2);
        $this->availability()->assertOk()->assertJsonPath('available_rooms', 1);
        $rooms = $this->roomList()->assertOk()->assertJsonCount(3)->json();
        foreach ($rooms as $room) {
            $this->assertSame('available', $room['physical_status']);
            $this->assertSame('available', $room['occupancy_status']);
            $this->assertSame('available', $room['status']);
            $this->assertTrue($room['bookable']);
            $this->assertSame(1, $room['available_rooms']);
        }
    }

    public function test_room_list_without_dates_preserves_locked_physical_status_and_json_array(): void
    {
        $this->activeRoom->update(['status' => 'locked']);
        $this->getJson('/api/hotels/' . $this->activeHotel->id . '/rooms?room_type_id=' . $this->roomType->id)
            ->assertOk()->assertJsonCount(3)->assertJsonPath('0.status', 'locked')
            ->assertJsonPath('0.physical_status', 'locked')->assertJsonPath('0.bookable', false)
            ->assertJsonPath('1.available_rooms', 2);
    }

    public function test_room_list_rejects_invalid_or_incomplete_date_ranges(): void
    {
        $this->roomList(['check_out' => null])->assertUnprocessable()->assertJsonValidationErrors('check_out');
        $this->roomList(['check_out' => $this->data()['check_in']])->assertUnprocessable()->assertJsonValidationErrors('check_out');
        $this->roomList(['check_in' => 'not-a-date'])->assertUnprocessable()->assertJsonValidationErrors('check_in');
    }

    public function test_history_list_loads_hotel_images_for_an_inactive_hotel(): void
    {
        $booking = $this->seedBooking($this->inactiveHotel, 'confirmed', 1);
        HotelImage::create(['hotel_id' => $this->inactiveHotel->id, 'image_url' => 'storage/test-placeholder.jpg', 'is_primary' => true]);
        $before = $this->sideEffectCounts();
        $list = $this->app->make(BookingService::class)->myBookings($this->customer->id, []);
        $this->assertSame($booking->id, $list->items()[0]->id);
        $this->assertTrue($list->items()[0]->hotel->relationLoaded('images'));
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/bookings/my')
            ->assertOk()->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.hotel.status', 'inactive')->assertJsonCount(1, 'data.0.hotel.images');
        $this->assertSame($before, $this->sideEffectCounts());
    }

    public function test_history_detail_loads_hotel_images_and_keeps_customer_ownership(): void
    {
        $booking = $this->seedBooking($this->inactiveHotel, 'confirmed', 1);
        HotelImage::create(['hotel_id' => $this->inactiveHotel->id, 'image_url' => 'storage/test-placeholder.jpg', 'is_primary' => true]);
        $before = $this->sideEffectCounts();
        $detail = $this->app->make(BookingService::class)->getDetail($booking->id, $this->customer->id);
        $this->assertTrue($detail->hotel->relationLoaded('images'));
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/bookings/' . $booking->id)
            ->assertOk()->assertJsonPath('id', $booking->id)
            ->assertJsonPath('hotel.status', 'inactive')->assertJsonCount(1, 'hotel.images');
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/bookings/' . $booking->id)->assertNotFound();
        $this->assertSame($before, $this->sideEffectCounts());
    }

    public function test_inactive_hotel_direct_booking_and_room_urls_are_blocked(): void
    {
        $id = $this->inactiveHotel->id;
        $this->getJson('/api/hotels/' . $id)->assertNotFound();
        $this->getJson('/api/hotels/' . $id . '/rooms')->assertNotFound();
        $this->get('/hotels/' . $id)->assertNotFound();
        $this->get('/booking/' . $id)->assertNotFound();
        $this->getJson('/api/hotels/' . $this->activeHotel->id . '/rooms?room_type_id=' . $this->roomType->id)
            ->assertOk()->assertJsonCount(3);
    }

    public function test_existing_inactive_hotel_booking_payment_and_invoice_remain_readable(): void
    {
        $booking = $this->seedBooking($this->inactiveHotel, 'confirmed', 1);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => $booking->total_price, 'payment_method' => 'cash', 'payment_status' => 'success']);
        InvoiceController::createFromPayment($booking, $payment);
        $before = $this->sideEffectCounts();
        $this->assertSame('inactive', $booking->load('hotel')->hotel->status);
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/payments/' . $booking->id)
            ->assertOk()->assertJsonPath('booking.id', $booking->id);
        $this->getJson('/api/invoices/' . $booking->id)->assertOk()->assertJsonPath('invoice.booking_id', $booking->id);
        $this->assertSame($before, $this->sideEffectCounts());
    }

    public function test_existing_pending_booking_at_inactive_hotel_keeps_manual_payment_behavior(): void
    {
        $booking = $this->seedBooking($this->inactiveHotel, 'pending', 1);
        Payment::create(['booking_id' => $booking->id, 'amount' => $booking->total_price, 'payment_method' => 'cash', 'payment_status' => 'pending']);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/payments/manual', ['booking_id' => $booking->id, 'payment_method' => 'cash'])
            ->assertOk();
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame('pending', $booking->payment->payment_status);
        $this->assertDatabaseHas('invoice', ['booking_id' => $booking->id]);
        Mail::assertSent(BookingConfirmed::class);
    }

    public function test_existing_booking_at_inactive_hotel_can_still_be_cancelled(): void
    {
        $booking = $this->seedBooking($this->inactiveHotel, 'confirmed', 1);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings/' . $booking->id . '/cancel')
            ->assertOk()->assertJsonPath('booking.status', 'cancelled');
    }

    public function test_deactivation_committing_first_blocks_waiting_booking_without_side_effects(): void
    {
        $directory = $this->makeBarrierDirectory();
        $processes = [];
        $before = $this->sideEffectCounts();
        try {
            $admin = $processes[] = $this->startWorker('admin', $directory);
            $this->waitForFile($directory . '/admin-updated', $admin);
            $booking = $processes[] = $this->startWorker('booking', $directory);
            $this->waitForFile($directory . '/booking-connected', $booking);
            $this->waitForLockWait((int) file_get_contents($directory . '/booking-connected'), $booking);
            $this->assertFileDoesNotExist($directory . '/booking-result.json');
            file_put_contents($directory . '/release-admin', '1');
            $admin->wait();
            $booking->wait();
            $this->assertTrue($this->workerResult($directory, 'admin')['ok']);
            $result = $this->workerResult($directory, 'booking');
            $this->assertFalse($result['ok']);
            $this->assertArrayHasKey('hotel_id', $result['errors']);
            $this->assertSame('inactive', $this->activeHotel->fresh()->status);
            $this->assertSame($before, $this->sideEffectCounts());
        } finally {
            $this->closeWorkers($processes, $directory);
        }
    }

    public function test_booking_committing_first_makes_deactivation_wait_and_preserves_the_booking(): void
    {
        $directory = $this->makeBarrierDirectory();
        $processes = [];
        try {
            $booking = $processes[] = $this->startWorker('booking', $directory, ['pause_booking' => true]);
            $this->waitForFile($directory . '/booking-locked', $booking);
            $admin = $processes[] = $this->startWorker('admin', $directory);
            $this->waitForFile($directory . '/admin-connected', $admin);
            $this->waitForLockWait((int) file_get_contents($directory . '/admin-connected'), $admin);
            $this->assertFileDoesNotExist($directory . '/admin-updated');
            file_put_contents($directory . '/release-admin', '1');
            file_put_contents($directory . '/release-booking', '1');
            $booking->wait();
            $admin->wait();
            $result = $this->workerResult($directory, 'booking');
            $this->assertTrue($result['ok'], json_encode($result));
            $this->assertTrue($this->workerResult($directory, 'admin')['ok']);
            $saved = Booking::findOrFail($result['booking_id']);
            $this->assertSame('inactive', $saved->hotel->status);
            $this->assertSame('pending', $saved->status);
            $this->assertNotNull($saved->payment);
            $this->assertServiceRejected($this->data(), 'hotel_id');
        } finally {
            $this->closeWorkers($processes, $directory);
        }
    }

    private function makeHotel(Location $location, string $name, string $status): Hotel
    {
        return Hotel::create(['location_id' => $location->id, 'name' => $name, 'address' => 'Test address', 'latitude' => 10, 'longitude' => 106, 'status' => $status]);
    }

    private function makeRoom(Hotel $hotel, RoomType $type, string $number): Room
    {
        return Room::create(['hotel_id' => $hotel->id, 'room_type_id' => $type->id, 'room_number' => $number, 'price' => $type->base_price, 'capacity' => 2, 'status' => 'available']);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['hotel_id' => $this->activeHotel->id, 'room_type_id' => $this->roomType->id, 'quantity' => 1,
            'check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString(), 'num_guests' => 2, 'payment_method' => 'cash'], $overrides);
    }

    private function sideEffectCounts(): array
    {
        $counts = [];
        foreach (['booking', 'booking_room', 'payment', 'invoice', 'invoice_item', 'customer_notifications'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        return $counts;
    }

    private function assertRejected(array $data, string $field): void
    {
        $before = $this->sideEffectCounts();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/bookings', $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $this->sideEffectCounts());
        Mail::assertNothingSent();
    }

    private function assertServiceRejected(array $data, string $field): void
    {
        $before = $this->sideEffectCounts();
        try {
            $this->app->make(BookingService::class)->create($this->customer->id, $data);
            $this->fail('The service accepted an invalid booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame($before, $this->sideEffectCounts());
        Mail::assertNothingSent();
    }

    private function availability(?Hotel $hotel = null, array $overrides = [])
    {
        return $this->actingAs($this->admin, 'admin')->getJson('/api/admin/rooms/availability?' . http_build_query(array_merge([
            'hotel_id' => ($hotel ?? $this->activeHotel)->id, 'room_type_id' => $this->roomType->id,
            'num_guests' => 2, 'quantity' => 1,
            'check_in' => $this->data()['check_in'], 'check_out' => $this->data()['check_out'],
        ], $overrides)));
    }

    private function roomList(array $overrides = [])
    {
        return $this->getJson('/api/hotels/' . $this->activeHotel->id . '/rooms?' . http_build_query(array_merge([
            'room_type_id' => $this->roomType->id, 'check_in' => $this->data()['check_in'], 'check_out' => $this->data()['check_out'],
        ], $overrides)));
    }

    private function seedBooking(Hotel $hotel, string $status, int $quantity, array $overrides = []): Booking
    {
        $data = $this->data(array_merge(['hotel_id' => $hotel->id, 'quantity' => $quantity], $overrides));
        $booking = Booking::create(['customer_id' => $this->customer->id, 'hotel_id' => $hotel->id, 'check_in' => $data['check_in'],
            'check_out' => $data['check_out'], 'num_guests' => 2, 'total_price' => 1000000 * $quantity, 'status' => $status, 'expires_at' => now()->addMinutes(15)]);
        BookingRoom::create(['booking_id' => $booking->id, 'room_type_id' => $this->roomType->id,
            'room_id' => $data['room_id'] ?? null, 'price_at_booking' => 500000, 'nights' => 2, 'quantity' => $quantity]);
        return $booking;
    }

    private function makeBarrierDirectory(): string
    {
        $directory = storage_path('framework/testing/booking-gate-' . bin2hex(random_bytes(6)));
        mkdir($directory, 0700, true);
        return $directory;
    }

    private function startWorker(string $job, string $directory, array $overrides = []): Process
    {
        $payload = array_merge(['customer_id' => $this->customer->id, 'admin_id' => $this->admin->id,
            'hotel_id' => $this->activeHotel->id, 'data' => $this->data()], $overrides);
        $process = new Process([PHP_BINARY, base_path('tests/Support/booking_concurrency_worker.php'),
            getenv('HOLIDAYVIET_TEST_DATABASE'), $job, $directory, json_encode($payload, JSON_THROW_ON_ERROR)], base_path());
        $process->setTimeout(25);
        $process->start();
        return $process;
    }

    private function waitForFile(string $path, Process $process): void
    {
        $this->waitUntil(static fn () => is_file($path), $process, 'Missing worker signal: ' . basename($path));
    }

    private function waitForLockWait(int $connectionId, Process $process): void
    {
        $this->waitUntil(static fn () => DB::selectOne(
            'SELECT COUNT(*) AS waiting FROM performance_schema.data_lock_waits w '
                . 'JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID '
                . 'WHERE t.PROCESSLIST_ID = ?',
            [$connectionId]
        )->waiting > 0, $process, 'The concurrent request did not wait for the hotel row lock.');
    }

    private function waitUntil(callable $condition, Process $process, string $message): void
    {
        $deadline = microtime(true) + 10;
        do {
            clearstatcache();
            if ($condition()) {
                return;
            }
            if (!$process->isRunning()) {
                $this->fail($message . "\n" . $process->getErrorOutput() . $process->getOutput());
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail($message . "\n" . $process->getErrorOutput());
    }

    private function workerResult(string $directory, string $job): array
    {
        return json_decode(file_get_contents($directory . '/' . $job . '-result.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function closeWorkers(array $processes, string $directory): void
    {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0.2);
            }
        }
        foreach (glob($directory . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}
