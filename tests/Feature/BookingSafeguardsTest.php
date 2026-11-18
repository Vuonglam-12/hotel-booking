<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Process\Process;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

/** Runs only through the owned isolated-MySQL bootstrap; never refreshes application data. */
class BookingSafeguardsTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = HotelRoomTypeFixture::initialize();
        HotelRoomTypeFixture::migrate();
        foreach (['2026_10_12_000005_replace_booking_status_unique.php', '2026_10_26_000001_create_local_booking_demo_audit.php'] as $file) {
            (require base_path('database/migrations/' . $file))->up();
        }
        foreach (glob(base_path('database/migrations/2026_10_31_000004*.php')) as $file) (require $file)->up();
        foreach (glob(base_path('database/migrations/2026_10_31_000005*.php')) as $file) (require $file)->up();
        $this->db->table('customer')->insert(['id' => 3, 'name' => 'Other customer', 'email' => 'other@example.test', 'role' => 'user']);
        config(['booking-demo.local_enabled' => true, 'booking-demo.hold_minutes' => 10,
            'booking-demo.max_pending_bookings' => 3, 'booking-demo.max_pending_rooms' => 10,
            'booking-demo.confirmation_mail_enabled' => false, 'app.url' => 'http://localhost',
            'app.debug' => false, 'logging.channels.stderr.level' => 'critical']);
        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
        $this->actor();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function actor(int $id = 2, bool $admin = false): void
    {
        $customer = Customer::findOrFail($id);
        $customer->withAccessToken(new PersonalAccessToken(['name' => $admin ? 'admin_token' : 'customer_token', 'abilities' => $admin ? ['admin'] : ['*']]));
        $this->actingAs($customer, $admin ? 'admin' : 'sanctum');
    }

    private function data(int $slot = 0, array $extra = []): array
    {
        return array_replace(['hotel_id' => 1, 'room_type_id' => 1, 'quantity' => 1, 'num_guests' => 2,
            'check_in' => now()->addDays(20 + $slot * 3)->toDateString(),
            'check_out' => now()->addDays(22 + $slot * 3)->toDateString(),
            'request_key' => bin2hex(random_bytes(16)), 'payment_method' => 'demo'], $extra);
    }

    private function make(int $slot = 0, array $extra = []): int
    {
        return $this->postJson('http://localhost/api/bookings', $this->data($slot, $extra))->assertCreated()->json('booking_id');
    }

    private function act(int $id, string $action, array $extra = [])
    {
        $this->actor(1, true);
        try {
            return $this->postJson('http://localhost/api/admin/demo/bookings/' . $id . '/simulate', array_replace([
                'action' => $action, 'payment_id' => $this->db->table('payment')->where('booking_id', $id)->orderByDesc('id')->value('id'),
            ], $extra));
        } finally {
            auth('admin')->forgetUser();
            $this->actor();
        }
    }

    private function denied($response): void
    {
        $this->assertContains($response->status(), [409, 422, 429], 'Request must be rejected by a business rule.');
    }

    private function pendingRooms(): int
    {
        return (int) $this->db->table('booking_room as r')->join('booking as b', 'b.id', '=', 'r.booking_id')
            ->where('b.customer_id', 2)->where('b.status', 'pending')->sum('r.quantity');
    }

    private function concurrentCreates(array $requests): array
    {
        $workers = [];
        foreach ($requests as $data) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/local_booking_demo_worker.php'),
                $this->db->getDatabaseName(), 'create', '2', json_encode($data, JSON_THROW_ON_ERROR)], base_path(),
                ['AUREL_ROOMTYPE_V2_TEST_SETTINGS' => getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')]);
            $worker->setTimeout(30);
            $worker->start();
            $workers[] = $worker;
        }
        $codes = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertContains($worker->getExitCode(), [0, 3], $worker->getErrorOutput());
            $codes[] = $worker->getExitCode();
        }
        sort($codes);
        return $codes;
    }

    public function test_demo_holds_exactly_ten_minutes_and_command_releases_without_a_web_visit(): void
    {
        $id = $this->make(0, ['quantity' => 5, 'num_guests' => 10]);
        $meta = $this->db->table('booking_demo')->where('booking_id', $id)->first();
        $this->assertSame(600, (int) $meta->expires_at_epoch - (int) $meta->created_at_epoch);
        $this->db->table('booking_demo')->where('booking_id', $id)->update(['expires_at_epoch' => time() - 1]);
        $this->artisan('bookings:cancel-expired')->assertSuccessful();
        $booking = $this->db->table('booking')->find($id);
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('system', $booking->cancelled_by);
        $this->assertSame('expired', $this->db->table('payment')->where('booking_id', $id)->value('payment_status'));
        $data = $this->data();
        $this->assertSame(5, app(RoomAvailabilityService::class)->forType(1, 1, $data['check_in'], $data['check_out'])['available_rooms']);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_fourth_pending_order_is_rejected_and_repeated_request_is_not_counted_twice(): void
    {
        $data = $this->data();
        $id = $this->postJson('http://localhost/api/bookings', $data)->assertCreated()->json('booking_id');
        $this->make(1);
        $this->make(2);
        $this->postJson('http://localhost/api/bookings', $data)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('booking_id', $id);
        $this->denied($this->postJson('http://localhost/api/bookings', $this->data(3)));
        $this->assertSame(3, $this->db->table('booking')->where('status', 'pending')->count());
    }

    public function test_total_pending_rooms_cannot_exceed_ten_even_on_different_stays_and_hotels(): void
    {
        $this->make(0, ['quantity' => 5, 'num_guests' => 10]);
        $this->make(1, ['hotel_id' => 2, 'quantity' => 5, 'num_guests' => 10]);
        $this->denied($this->postJson('http://localhost/api/bookings', $this->data(2)));
        $this->assertSame(10, $this->pendingRooms());
        $this->denied($this->postJson('http://localhost/api/bookings', $this->data(3, ['quantity' => 11])));
    }

    public function test_cancelled_and_elapsed_orders_do_not_consume_pending_quota(): void
    {
        $cancelled = $this->make();
        $this->act($cancelled, 'cancelled')->assertOk();
        $elapsed = $this->make(1);
        $this->db->table('booking_demo')->where('booking_id', $elapsed)->update(['expires_at_epoch' => time() - 1]);
        $this->make(2);
        $this->make(3);
        $this->make(4);
        $this->assertSame(3, $this->db->table('booking')->where('status', 'pending')->count());
        $this->assertSame(3, $this->pendingRooms());
    }

    public function test_pending_quota_is_per_account(): void
    {
        foreach ([0, 1, 2] as $slot) $this->make($slot);
        $this->actor(3);
        $this->make(3);
        $this->assertSame(1, $this->db->table('booking')->where('customer_id', 3)->count());
    }

    public function test_parallel_requests_at_different_hotels_cannot_pass_the_order_limit(): void
    {
        $this->make();
        $this->make(1);
        $this->assertSame([0, 3], $this->concurrentCreates([$this->data(2), $this->data(3, ['hotel_id' => 2])]));
        $this->assertSame(3, $this->db->table('booking')->where('customer_id', 2)->where('status', 'pending')->count());
    }

    public function test_parallel_requests_cannot_pass_the_total_room_limit(): void
    {
        $this->make(0, ['quantity' => 5, 'num_guests' => 10]);
        $this->assertSame([0, 3], $this->concurrentCreates([
            $this->data(1, ['hotel_id' => 2, 'quantity' => 3, 'num_guests' => 6]),
            $this->data(2, ['quantity' => 3, 'num_guests' => 6]),
        ]));
        $this->assertSame(8, $this->pendingRooms());
    }

    public function test_released_order_cannot_be_restored_by_admin_status_override(): void
    {
        $id = $this->make();
        $this->act($id, 'cancelled')->assertOk();
        $this->actor(1, true);
        foreach (['pending', 'confirmed', 'completed'] as $target) {
            $this->denied($this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => $target]));
            $this->assertSame('cancelled', $this->db->table('booking')->find($id)->status);
        }
        $this->assertSame(0, $this->db->table('invoice')->count());
    }

    public function test_unpaid_or_before_checkout_order_cannot_be_completed(): void
    {
        $id = $this->make();
        $this->actor(1, true);
        $this->denied($this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => 'completed']));
        $this->actor();
        $this->act($id, 'success')->assertOk();
        $this->actor(1, true);
        $this->denied($this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => 'completed']));
        $this->assertSame('confirmed', $this->db->table('booking')->find($id)->status);
    }

    public function test_paid_order_can_complete_only_after_checkout_and_repeated_completion_is_safe(): void
    {
        $id = $this->make();
        $this->act($id, 'success')->assertOk();
        $checkout = $this->db->table('booking')->find($id)->check_out;
        Carbon::setTestNow(Carbon::parse($checkout . ' 11:59:59', 'Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow(Carbon::getTestNow());
        $this->actor(1, true);
        $this->denied($this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => 'completed']));
        Carbon::setTestNow(Carbon::parse($checkout . ' 12:00:00', 'Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow(Carbon::getTestNow());
        $this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => 'completed'])->assertOk();
        $this->putJson('http://localhost/api/admin/bookings/' . $id . '/status', ['status' => 'completed'])->assertOk();
        $this->assertSame('completed', $this->db->table('booking')->find($id)->status);
        $this->assertSame(1, $this->db->table('invoice')->count());
    }

    public function test_amount_mismatch_is_recorded_without_confirming_or_creating_invoice(): void
    {
        $id = $this->make();
        $this->act($id, 'success', ['received_amount' => '1.00', 'event_key' => 'wrong-amount-test-event'])
            ->assertOk()->assertJsonPath('outcome', 'amount_mismatch_reconciliation');
        $this->assertSame('pending', $this->db->table('booking')->find($id)->status);
        $this->assertSame('pending', $this->db->table('payment')->where('booking_id', $id)->value('payment_status'));
        $this->assertSame(0, $this->db->table('invoice')->count());
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
    }

    public function test_late_success_is_recorded_once_without_resurrecting_cancelled_order(): void
    {
        $id = $this->make();
        $this->act($id, 'cancelled')->assertOk();
        $this->act($id, 'success', ['event_key' => 'late-payment-test-event'])->assertOk()->assertJsonPath('outcome', 'late_reconciliation');
        $this->act($id, 'success', ['event_key' => 'late-payment-test-event'])->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame('cancelled', $this->db->table('booking')->find($id)->status);
        $this->assertSame('success', $this->db->table('payment')->where('booking_id', $id)->value('payment_status'));
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame(0, $this->db->table('invoice')->count());
        $this->assertSame(0, $this->db->table('customer_notifications')->count());
    }

    public function test_late_success_after_deadline_releases_stock_and_retains_money_for_review(): void
    {
        $id = $this->make(0, ['quantity' => 5, 'num_guests' => 10]);
        $this->db->table('booking_demo')->where('booking_id', $id)->update(['expires_at_epoch' => time() - 1]);
        $this->act($id, 'success')->assertOk()->assertJsonPath('outcome', 'late_reconciliation');
        $this->assertSame('cancelled', $this->db->table('booking')->find($id)->status);
        $this->assertSame(0, $this->db->table('invoice')->count());
        $data = $this->data();
        $this->assertSame(5, app(RoomAvailabilityService::class)->forType(1, 1, $data['check_in'], $data['check_out'])['available_rooms']);
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
    }

    public function test_duplicate_success_has_one_financial_effect_and_customer_cannot_assert_payment(): void
    {
        $id = $this->make();
        $payment = $this->db->table('payment')->where('booking_id', $id)->value('id');
        $this->postJson('http://localhost/api/demo/bookings/' . $id . '/simulate', ['action' => 'success', 'payment_id' => $payment])->assertForbidden();
        $this->act($id, 'success')->assertOk();
        $this->act($id, 'success')->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, $this->db->table('invoice')->count());
        $this->assertSame(1, $this->db->table('booking_demo_event')->where('outcome', 'success')->count());
        $this->assertSame(1, $this->db->table('customer_notifications')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_website_quote_booking_payment_and_invoice_keep_the_same_nightly_snapshot(): void
    {
        $pricing = app(\App\Services\RoomPricingService::class);
        $data = $this->data(0, ['quantity' => 2, 'num_guests' => 4]);
        $secondNight = Carbon::parse($data['check_in'])->addDay()->toDateString();
        $pricing->saveRate(1, 1, ['kind' => 'season', 'start_date' => $data['check_in'], 'end_date' => $data['check_out'], 'price' => '1000000.10']);
        $day = $pricing->saveRate(1, 1, ['kind' => 'day', 'start_date' => $secondNight, 'end_date' => $secondNight, 'price' => '1200000.15']);
        $quote = $this->getJson('http://localhost/api/hotels/1/room-types/1/quote?' . http_build_query([
            'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'quantity' => 2,
        ]))->assertOk()->assertJsonPath('total_price', '4400000.50')->json();
        $data['quote_hash'] = $quote['quote_hash'];
        $id = $this->postJson('http://localhost/api/bookings', $data)->assertCreated()->json('booking_id');
        $this->assertSame('4400000.50', $this->db->table('booking')->find($id)->total_price);
        $this->assertSame('4400000.50', $this->db->table('payment')->where('booking_id', $id)->value('amount'));
        $line = $this->db->table('booking_room')->where('booking_id', $id)->sole();
        $snapshot = $pricing->snapshot($line->id);
        $this->assertSame(['1000000.10', '1200000.15'], array_column($snapshot, 'unit_price'));
        $pricing->deleteRate(1, 1, $day['id']);
        $this->db->table('hotel_room_type')->where('hotel_id', 1)->update(['base_price' => '999999.99']);
        $this->assertSame($snapshot, $pricing->snapshot($line->id));
        $this->getJson('http://localhost/api/demo/bookings/' . $id)->assertOk()->assertJsonPath('booking.total_price', '4400000.50');
        $this->act($id, 'success')->assertOk();
        $this->assertSame('4400000.50', $this->db->table('invoice')->where('booking_id', $id)->value('total'));
        $this->assertSame($snapshot, $pricing->snapshot($line->id));
    }

    public function test_stale_website_quote_is_rejected_until_customer_refreshes_prices(): void
    {
        $pricing = app(\App\Services\RoomPricingService::class);
        $data = $this->data();
        $quote = $pricing->quote(1, 1, $data['check_in'], $data['check_out']);
        $pricing->saveRate(1, 1, ['kind' => 'day', 'start_date' => $data['check_in'], 'end_date' => $data['check_in'], 'price' => '1500000.00']);
        $this->postJson('http://localhost/api/bookings', $data + ['quote_hash' => $quote['quote_hash']])->assertConflict();
        $this->assertSame(0, $this->db->table('booking')->count());
        $fresh = $pricing->quote(1, 1, $data['check_in'], $data['check_out']);
        $id = $this->postJson('http://localhost/api/bookings', $data + ['quote_hash' => $fresh['quote_hash']])->assertCreated()->json('booking_id');
        $this->assertSame($fresh['total_price'], $this->db->table('booking')->find($id)->total_price);
    }

    public function test_invoice_items_and_subtotal_preserve_cents_when_average_nightly_price_rounds(): void
    {
        $pricing = app(\App\Services\RoomPricingService::class);
        $data = $this->data(0, ['quantity' => 3, 'num_guests' => 6]);
        $data['check_out'] = Carbon::parse($data['check_in'])->addDays(3)->toDateString();
        foreach (['100.01', '100.01', '100.02'] as $offset => $price) {
            $date = Carbon::parse($data['check_in'])->addDays($offset)->toDateString();
            $pricing->saveRate(1, 1, ['kind' => 'day', 'start_date' => $date, 'end_date' => $date, 'price' => $price]);
        }
        $id = $this->postJson('http://localhost/api/bookings', $data)->assertCreated()->json('booking_id');
        $this->act($id, 'success')->assertOk();
        $invoice = $this->db->table('invoice')->where('booking_id', $id)->sole();
        $this->assertSame('900.12', $invoice->total);
        $this->assertSame('900.12', $invoice->subtotal);
        $this->assertSame('900.12', (string) $this->db->table('invoice_item')->where('invoice_id', $invoice->id)->sum('amount'));
        $this->assertSame(['300.03', '300.03', '300.06'], $this->db->table('invoice_item')->where('invoice_id', $invoice->id)->orderBy('id')->pluck('amount')->all());
    }

    public function test_reconciliation_notes_keep_audit_and_refund_requires_uploaded_evidence(): void
    {
        Storage::fake('local');
        $id = $this->make();
        $this->act($id, 'cancelled')->assertOk();
        $entry = $this->act($id, 'success')->assertOk()->json('reconciliation_id');
        $payment = $this->db->table('payment')->where('booking_id', $id)->sole();
        $this->actor(1, true);
        $url = 'http://localhost/api/admin/demo/reconciliation/' . $entry . '/review';
        $this->postJson($url, ['state' => 'refund_verified', 'note' => 'No evidence yet.', 'evidence_reference' => 'SYNTHETIC-REF-001'])->assertUnprocessable();
        $this->assertSame('open', $this->db->table('booking_demo_reconciliation')->find($entry)->state);
        $this->postJson($url, ['state' => 'reviewed', 'note' => 'Confirmed late transfer, awaiting resolution.'])->assertOk();
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation_review')->where('reconciliation_id', $entry)->count());
        $this->post($url, ['state' => 'refund_verified', 'note' => 'Synthetic refund evidence reviewed by admin.',
            'evidence_reference' => 'SYNTHETIC-REF-001',
            'evidence' => UploadedFile::fake()->createWithContent('synthetic-refund.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n")], ['Accept' => 'application/json'])->assertOk();
        $record = $this->db->table('booking_demo_reconciliation')->find($entry);
        $this->assertSame('refund_verified', $record->state);
        $this->assertSame('SYNTHETIC-REF-001', $record->evidence_reference);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $record->evidence_sha256);
        Storage::disk('local')->assertExists($record->evidence_path);
        $this->assertSame(2, $this->db->table('booking_demo_reconciliation_review')->where('reconciliation_id', $entry)->count());
        $this->assertEquals($payment, $this->db->table('payment')->where('booking_id', $id)->sole());
        $this->assertSame('cancelled', $this->db->table('booking')->find($id)->status);
        $this->getJson('http://localhost/api/admin/demo/reconciliation')->assertOk()->assertJsonPath('data.0.state', 'refund_verified')->assertJsonCount(2, 'data.0.reviews');
        Http::assertNothingSent();
    }

    public function test_reconciliation_records_are_restricted_to_real_admin_role(): void
    {
        $id = $this->make();
        $entry = $this->act($id, 'success', ['received_amount' => '1.00'])->assertOk()->json('reconciliation_id');
        $this->getJson('http://localhost/api/admin/demo/reconciliation')->assertUnauthorized();
        $this->actor(3, true);
        $this->getJson('http://localhost/api/admin/demo/reconciliation')->assertForbidden();
        $this->postJson('http://localhost/api/admin/demo/reconciliation/' . $entry . '/review', ['state' => 'reviewed', 'note' => 'Unauthorized note.'])->assertForbidden();
        $this->assertSame(0, $this->db->table('booking_demo_reconciliation_review')->count());
        $this->assertSame('open', $this->db->table('booking_demo_reconciliation')->find($entry)->state);
    }

    public function test_notification_identity_rejects_conflicting_reconciliation_payloads(): void
    {
        $id = $this->make();
        $identity = 'same-notification-with-conflicting-money';
        $this->act($id, 'success', ['received_amount' => '1.00', 'event_key' => $identity])->assertOk();
        $this->act($id, 'success', ['received_amount' => '1.00', 'event_key' => $identity])->assertOk()->assertJsonPath('duplicate', true);
        $this->act($id, 'success', ['received_amount' => '2.00', 'event_key' => $identity])->assertConflict();
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame('1.00', $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->value('received_amount'));
        $this->assertSame('pending', $this->db->table('booking')->find($id)->status);
        $this->assertSame(0, $this->db->table('invoice')->count());
    }

    public function test_distinct_late_notification_identities_are_retained_for_admin_review(): void
    {
        $id = $this->make();
        $this->act($id, 'cancelled')->assertOk();
        $first = $this->act($id, 'success', ['event_key' => 'late-transfer-distinct-001'])->assertOk()->assertJsonPath('duplicate', false)->json('reconciliation_id');
        $second = $this->act($id, 'success', ['event_key' => 'late-transfer-distinct-002'])->assertOk()->assertJsonPath('duplicate', false)->json('reconciliation_id');
        $this->assertNotSame($first, $second);
        $this->act($id, 'success', ['event_key' => 'late-transfer-distinct-002'])->assertOk()->assertJsonPath('duplicate', true);
        $this->act($id, 'success', ['event_key' => 'late-transfer-distinct-002', 'received_amount' => '1.00'])->assertConflict();
        $this->assertSame(2, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame(1, $this->db->table('booking_demo_event')->where('booking_id', $id)->where('outcome', 'late_success')->count());
        $this->assertSame('cancelled', $this->db->table('booking')->find($id)->status);
        $this->assertSame(0, $this->db->table('invoice')->count());
    }

    public function test_nightly_metadata_and_labels_are_saved_exactly_in_booking_history(): void
    {
        $pricing = app(\App\Services\RoomPricingService::class);
        $data = $this->data();
        $secondNight = Carbon::parse($data['check_in'])->addDay()->toDateString();
        $season = $pricing->saveRate(1, 1, ['kind' => 'season', 'start_date' => $data['check_in'], 'end_date' => $data['check_out'], 'price' => '1000000.13', 'label' => 'Original season label']);
        $day = $pricing->saveRate(1, 1, ['kind' => 'day', 'start_date' => $secondNight, 'end_date' => $secondNight, 'price' => '1200000.27', 'label' => 'Original special day']);
        $quote = $pricing->quote(1, 1, $data['check_in'], $data['check_out']);
        $id = $this->postJson('http://localhost/api/bookings', $data + ['quote_hash' => $quote['quote_hash']])->assertCreated()->json('booking_id');
        $line = $this->db->table('booking_room')->where('booking_id', $id)->sole();
        $this->assertSame($quote['nightly_rates'], $pricing->snapshot($line->id));
        $pricing->saveRate(1, 1, ['kind' => 'season', 'start_date' => $data['check_in'], 'end_date' => $data['check_out'], 'price' => '2000000.00', 'label' => 'Edited season label'], $season['id']);
        $pricing->deleteRate(1, 1, $day['id']);
        $this->assertSame($quote['nightly_rates'], $pricing->snapshot($line->id));
        $this->getJson('http://localhost/api/demo/bookings/' . $id)->assertOk()->assertJsonPath('booking.nightly_rates', $quote['nightly_rates']);
    }

    public function test_legacy_admin_status_edit_cannot_restore_released_or_confirm_unpaid_orders(): void
    {
        $ids = [];
        foreach (['cancelled', 'expired', 'pending'] as $offset => $status) {
            $data = $this->data($offset);
            $id = $this->db->table('booking')->insertGetId(['customer_id' => 2, 'hotel_id' => 1, 'check_in' => $data['check_in'],
                'check_out' => $data['check_out'], 'num_guests' => 2, 'total_price' => '1600000.50', 'status' => $status]);
            $this->db->table('booking_room')->insert(['booking_id' => $id, 'room_type_id' => 1, 'room_id' => null, 'quantity' => 1, 'nights' => 2, 'price_at_booking' => '800000.25']);
            $ids[$status] = $id;
        }
        $this->actor(1, true);
        foreach (['cancelled', 'expired'] as $status) {
            foreach (['pending', 'confirmed', 'completed'] as $target) {
                $this->putJson('http://localhost/api/admin/bookings/' . $ids[$status] . '/status', ['status' => $target])->assertUnprocessable();
                $this->assertSame($status, $this->db->table('booking')->find($ids[$status])->status);
            }
        }
        foreach (['confirmed', 'completed'] as $target) {
            $this->putJson('http://localhost/api/admin/bookings/' . $ids['pending'] . '/status', ['status' => $target])->assertUnprocessable();
        }
        $this->assertSame('pending', $this->db->table('booking')->find($ids['pending'])->status);
        $this->assertSame(0, $this->db->table('invoice')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_strict_notice_identity_cannot_change_from_mismatched_to_successful_money(): void
    {
        $id = $this->make();
        $amount = $this->db->table('payment')->where('booking_id', $id)->value('amount');
        $identity = 'strict-notice-mismatch-then-success';
        $this->act($id, 'success', ['received_amount' => '1.00', 'event_key' => $identity])->assertOk()->assertJsonPath('outcome', 'amount_mismatch_reconciliation');
        $this->act($id, 'success', ['received_amount' => $amount, 'event_key' => $identity])->assertConflict();
        $this->assertSame('pending', $this->db->table('booking')->find($id)->status);
        $this->assertSame('pending', $this->db->table('payment')->where('booking_id', $id)->value('payment_status'));
        $this->assertSame(0, $this->db->table('invoice')->count());
        $this->assertSame(1, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame(1, $this->db->table('booking_demo_payment_notice')->whereIn('payment_id', $this->db->table('payment')->where('booking_id', $id)->select('id'))->count());
    }

    public function test_strict_notice_identity_repeats_success_once_and_refuses_changed_payload(): void
    {
        $id = $this->make();
        $identity = 'strict-notice-success-and-replay';
        $this->act($id, 'success', ['event_key' => $identity])->assertOk()->assertJsonPath('outcome', 'success');
        $this->act($id, 'success', ['event_key' => $identity])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('outcome', 'success');
        $this->act($id, 'success', ['received_amount' => '1.00', 'event_key' => $identity])->assertConflict();
        $this->assertSame('confirmed', $this->db->table('booking')->find($id)->status);
        $this->assertSame('success', $this->db->table('payment')->where('booking_id', $id)->value('payment_status'));
        $this->assertSame(1, $this->db->table('invoice')->count());
        $this->assertSame(1, $this->db->table('customer_notifications')->count());
        $this->assertSame(0, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame(1, $this->db->table('booking_demo_payment_notice')->whereIn('payment_id', $this->db->table('payment')->where('booking_id', $id)->select('id'))->count());
        Http::assertNothingSent();
    }

    public function test_payment_detail_exposes_each_reconciliation_transaction_and_released_funds_status(): void
    {
        $id = $this->make();
        $this->act($id, 'success', ['received_amount' => '10.00', 'event_key' => 'ledger-mismatch-receipt'])->assertOk();
        $this->act($id, 'cancelled')->assertOk();
        $this->act($id, 'success', ['event_key' => 'ledger-late-receipt-001'])->assertOk();
        $this->act($id, 'success', ['event_key' => 'ledger-late-receipt-002'])->assertOk();
        $detail = $this->getJson('http://localhost/api/demo/bookings/' . $id)->assertOk()
            ->assertJsonPath('booking.status', 'cancelled')->assertJsonPath('payment.payment_status', 'success')
            ->assertJsonPath('payment.display_status', 'reconciliation')->assertJsonPath('invoice', null)->json();
        $this->assertSame(['cancelled', 'amount_mismatch_reconciliation', 'late_reconciliation', 'late_reconciliation'], array_column($detail['transactions'], 'outcome'));
        $this->assertSame('10.00', $detail['transactions'][1]['amount']);
        $this->assertSame('open', $detail['transactions'][1]['state']);
        $this->assertSame('1600000.50', $detail['transactions'][2]['amount']);
        $this->assertSame('1600000.50', $detail['transactions'][3]['amount']);
        $this->assertSame(3, $this->db->table('booking_demo_reconciliation')->where('booking_id', $id)->count());
        $this->assertSame(0, $this->db->table('customer_notifications')->count());
    }
}
