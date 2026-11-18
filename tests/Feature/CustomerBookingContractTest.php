<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\BookingService;
use App\Services\BookingServiceV2;
use App\Support\RoomTypeV2Integration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RoomTypeV2AcceptanceFixture;
use Tests\Support\RoomTypeV2BackendFixture;
use Tests\TestCase;

class CustomerBookingContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-12 08:00:00');
        Http::preventStrayRequests();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedHistory($db): int
    {
        $id = $db->table('booking')->insertGetId(['customer_id' => 9001, 'hotel_id' => 1,
            'check_in' => '2026-10-15', 'check_out' => '2026-10-17', 'num_guests' => 4,
            'total_price' => '2000000.00', 'status' => 'confirmed']);
        $db->table('booking_room')->insert(['booking_id' => $id, 'room_type_id' => 1, 'room_id' => 1,
            'quantity' => 2, 'nights' => 2, 'price_at_booking' => '500000.00']);
        $db->table('payment')->insert(['booking_id' => $id, 'amount' => '2000000.00', 'payment_method' => null, 'payment_status' => 'failed']);
        return $id;
    }

    public function test_legacy_reads_select_successful_retry_and_actual_invoice_without_side_effects(): void
    {
        $db = RoomTypeV2BackendFixture::initialize();
        $id = $this->seedHistory($db);
        $paid = $db->table('payment')->insertGetId(['booking_id' => $id, 'amount' => '2000000.00', 'payment_method' => 'vnpay', 'payment_status' => 'success']);
        $db->table('payment')->insert(['booking_id' => $id, 'amount' => '2000000.00', 'payment_method' => 'banking', 'payment_status' => 'failed']);
        $invoiceId = $db->table('invoice')->insertGetId(['booking_id' => $id, 'customer_id' => 9001,
            'payment_id' => $paid, 'invoice_no' => 'SYNTHETIC-CONTRACT-1', 'subtotal' => '2000000.00', 'total' => '2000000.00']);
        $service = app(BookingService::class);
        $before = $db->table('invoice')->where('id', $invoiceId)->first();
        $detail = $service->getDetail($id, 9001)->toArray();
        $this->assertSame($paid, $detail['payment']['id']);
        $this->assertSame('success', $detail['payment']['payment_status']);
        $this->assertSame('vnpay', $detail['payment_method']);
        $this->assertSame($invoiceId, $detail['invoice']['id']);
        $this->assertSame('2000000.00', $detail['total_price']);
        $list = $service->myBookings(9001, ['per_page' => 1000000]);
        $this->assertSame(100, $list->perPage());
        $this->assertSame($paid, $list->first()->payment->id);
        $this->assertSame(0, $service->myBookings(9002, [])->total());
        $this->actingAs(Customer::findOrFail(9001), 'sanctum');
        $pdf = $this->get('/api/invoices/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertEquals($before, $db->table('invoice')->where('id', $invoiceId)->first());
        $db->table('booking')->where('id', $id)->update(['status' => 'cancelled']);
        $cancelled = $service->getDetail($id, 9001);
        $this->assertSame('reconciliation', $cancelled->payment->display_status);
        $this->assertSame($invoiceId, $cancelled->invoice->id);
    }

    public function test_legacy_http_ownership_unknown_payment_and_pdf_read_are_truthful(): void
    {
        $db = RoomTypeV2BackendFixture::initialize();
        $id = $this->seedHistory($db);
        $this->actingAs(Customer::findOrFail(9001), 'sanctum');
        $this->getJson('/api/bookings/'.$id)->assertOk()->assertJsonPath('payment.payment_status', 'failed')
            ->assertJsonPath('payment_method', null)->assertJsonPath('invoice', null);
        $this->getJson('/api/invoices/'.$id.'/pdf')->assertNotFound();
        $this->actingAs(Customer::findOrFail(9002), 'sanctum');
        $this->getJson('/api/bookings/'.$id)->assertNotFound();
        $this->getJson('/api/invoices/'.$id)->assertNotFound();
        $this->getJson('/api/invoices/'.$id.'/pdf')->assertNotFound();
        $this->assertSame(0, $db->table('invoice')->count());
    }

    public function test_v2_history_envelope_ownership_and_no_invoice_until_issued(): void
    {
        $db = RoomTypeV2AcceptanceFixture::initialize();
        RoomTypeV2Integration::acceptance();
        $type = $db->table('room_type_legacy_map')->where('hotel_id', 1)->where('old_room_type_id', 1)->value('new_room_type_id');
        $b = app(BookingServiceV2::class)->create('mysql', 9001, ['hotel_id' => 1, 'room_type_id' => $type,
            'quantity' => 2, 'num_guests' => 4, 'check_in' => '2026-10-15', 'check_out' => '2026-10-17', 'payment_method' => 'banking']);
        $this->actingAs(Customer::findOrFail(9001), 'sanctum');
        $this->getJson('/api/bookings/'.$b['id'])->assertOk()->assertJsonPath('booking.id', $b['id'])
            ->assertJsonPath('booking.invoice', null)->assertJsonPath('booking.room_quantity', 2);
        $this->getJson('/api/bookings/my')->assertOk()->assertJsonPath('data.0.id', $b['id']);
        $this->getJson('/api/bookings/my?customer_id=9002')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/bookings/my?per_page=1000000')->assertOk()->assertJsonPath('per_page', 100);
        $this->actingAs(Customer::findOrFail(9002), 'sanctum');
        $this->getJson('/api/bookings/'.$b['id'])->assertNotFound();
        $this->getJson('/api/bookings/my')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/invoices/'.$b['id'].'/pdf')->assertNotFound();
        $this->assertSame(0, $db->table('invoice')->count());
    }
}
