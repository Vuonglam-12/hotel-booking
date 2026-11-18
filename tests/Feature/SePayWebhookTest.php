<?php

namespace Tests\Feature;

use App\Services\BookingEffectServiceV2;
use App\Services\ManualBankingPaymentServiceV2;
use App\Services\PreparedV2\BookingMailSender;
use App\Support\RoomTypeV2Integration;
use App\Support\VnpayProtocol;
use Carbon\Carbon;
use Symfony\Component\Process\Process;
use Tests\Support\RoomTypeV2ReadinessFixture;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    private $db;
    private int $type;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->db = RoomTypeV2ReadinessFixture::initialize();
        (require base_path('database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php'))->up();
        (require base_path('database/migrations/2026_10_12_000008_add_booking_delivery_safety.php'))->up();
        (require base_path('database/migrations/2026_10_17_000001_create_sepay_webhook_events.php'))->up();
        $this->type = 1;
        $this->db->table('customer')->where('id', 9002)->update(['role' => 'admin']);
        config([
            'roomtype-v2.integration_complete' => false,
            'roomtype-v2.final_integration' => true,
            'roomtype-v2.delivery_safety' => true,
            'services.sepay.webhook.enabled' => true,
            'services.sepay.webhook.mode' => 'test',
            'services.sepay.webhook.auth_method' => 'api_key',
            'services.sepay.webhook.api_key' => 'synthetic-sepay-api-key',
            'services.sepay.webhook.hmac_secret' => 'synthetic-sepay-hmac-secret',
            'services.sepay.webhook.account_number' => '1234567890',
            'roomtype-v2.banking_instructions' => [
                'verified' => true,
                'bank_name' => 'Synthetic Test Bank',
                'bank_code' => 'TEST',
                'account_name' => 'AUREL TEST',
                'account_number' => '1234567890',
                'instructions' => 'Synthetic test transfer only.',
                'transfer_reference' => 'payment.transaction_id',
                'qr_payload' => 'SYNTHETIC-ONLY',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_is_registered_but_disabled_by_default(): void
    {
        config(['services.sepay.webhook.enabled' => false]);
        $booking = $this->createBooking();
        $payment = $this->payment($booking);

        $this->sendEvent($this->event($payment))->assertStatus(503)->assertJsonPath('success', false);
        $this->assertSame('pending', $this->paymentStatus($payment));
        $this->assertSame(0, $this->db->table('sepay_webhook_event')->count());
    }

    public function test_unconfigured_auth_live_mode_and_production_manifest_are_refused(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $event = $this->event($payment);
        $this->enableWebhook();

        config(['services.sepay.webhook.auth_method' => null]);
        $this->sendEvent($event)->assertStatus(503);
        config(['services.sepay.webhook.auth_method' => 'api_key', 'services.sepay.webhook.mode' => 'live']);
        $this->sendEvent($event)->assertStatus(503);
        config(['services.sepay.webhook.mode' => 'test', 'roomtype-v2.production_manifest' => 'synthetic-manifest']);
        $this->sendEvent($event)->assertStatus(503);

        $this->assertSame('pending', $this->paymentStatus($payment));
        $this->assertSame(0, $this->db->table('sepay_webhook_event')->count());
    }

    public function test_valid_incoming_payment_confirms_once_and_queues_existing_delivery_effect(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $this->enableWebhook();

        $response = $this->sendEvent($this->event($payment))->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('{"success":true}', $response->getContent());
        $this->assertSame('success', $this->db->table('sepay_webhook_event')->value('outcome'));
        $this->assertSame('confirmed', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame('success', $this->paymentStatus($payment));
        $this->assertSame(1, $this->db->table('payment_gateway_receipt')->where('provider', 'sepay')->count());
        $this->assertSame(1, $this->db->table('sepay_webhook_event')->count());
        $this->assertSame(1, $this->db->table('booking_state_audit')->where('reason', 'sepay_webhook_verified')->count());
        $this->assertSame(1, $this->db->table('booking_effect')->where('event_key', 'payment-confirmed:' . $payment->id)->count());
        $this->assertSame(0, $this->db->table('invoice')->count());
    }

    public function test_customer_checkout_to_sepay_confirmation_invoice_and_array_email(): void
    {
        RoomTypeV2Integration::backend();
        $this->actingAs(\App\Models\Customer::findOrFail(9001), 'sanctum');
        $this->db->table('room_type')->where('id', $this->type)->update([
            'hotel_id' => 1,
            'base_price' => '100000.00',
            'capacity' => 2,
            'total_rooms' => 10,
            'status' => 'active',
        ]);

        $bankingInstructions = config('roomtype-v2.banking_instructions');
        config(['roomtype-v2.banking_instructions' => null]);
        $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'payment_method' => 'banking',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, $this->db->table('booking')->count());
        config(['roomtype-v2.banking_instructions' => $bankingInstructions]);

        $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'payment_method' => 'vnpay',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, $this->db->table('booking')->count());

        $checkout = $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'payment_method' => 'banking',
        ]);
        $this->assertSame(201, $checkout->status(), $checkout->getContent());
        $booking = $checkout->json('booking');

        $this->assertSame('pending', $booking['status']);
        $this->assertSame('2026-10-12 08:15:00', $booking['expires_at']);
        $line = $this->db->table('booking_room')->where('booking_id', $booking['id'])->first();
        $this->assertNull($line->room_id);
        $this->assertSame(1, $line->quantity);
        $payment = $this->payment($booking);
        $this->assertSame($booking['total_price'], $payment->amount);
        $this->assertSame('pending', $payment->payment_status);
        config([
            'roomtype-v2.vnpay_merchant_verified' => true,
            'services.vnpay.return_url' => 'https://payments.example.com/payment/result',
            'services.vnpay.ipn_url' => 'https://payments.example.com/api/payments/vnpay-ipn',
        ]);
        $this->postJson('/api/payments/create', ['booking_id' => $booking['id']])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertSame(1, $this->db->table('payment')->where('booking_id', $booking['id'])->count());
        $this->enableWebhook();

        $this->sendEvent($this->event($payment))->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('confirmed', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame(1, $this->db->table('booking_effect')->where('payment_id', $payment->id)->count());
        $this->assertSame(0, $this->db->table('invoice')->count());

        $effectId = $this->db->table('booking_effect')->where('payment_id', $payment->id)->value('id');
        $this->assertTrue(app(BookingEffectServiceV2::class)->deliver('mysql', $effectId, app(BookingMailSender::class)));
        $this->assertFalse(app(BookingEffectServiceV2::class)->deliver('mysql', $effectId, app(BookingMailSender::class)));
        $this->assertSame(1, $this->db->table('invoice')->where('booking_id', $booking['id'])->count());
        $this->assertSame(1, $this->db->table('invoice_item')->count());
        $this->assertSame('delivered', $this->db->table('booking_effect')->where('id', $effectId)->value('state'));
    }

    public function test_vnpay_return_is_read_only_and_signed_ipn_reconciles_wrong_amount(): void
    {
        RoomTypeV2Integration::backend();
        $this->actingAs(\App\Models\Customer::findOrFail(9001), 'sanctum');
        $this->db->table('room_type')->where('id', $this->type)->update([
            'hotel_id' => 1,
            'base_price' => '100000.00',
            'capacity' => 2,
            'total_rooms' => 10,
            'status' => 'active',
        ]);
        config([
            'roomtype-v2.vnpay_merchant_verified' => true,
            'services.vnpay' => [
                'tmn_code' => 'TEST0001',
                'hash_secret' => str_repeat('synthetic-test-secret-', 3),
                'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                'return_url' => 'https://payments.example.com/payment/result',
                'ipn_url' => 'https://payments.example.com/api/payments/vnpay-ipn',
            ],
        ]);

        $booking = $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'payment_method' => 'vnpay',
        ])->assertCreated()->json('booking');
        $started = $this->postJson('/api/payments/create', ['booking_id' => $booking['id']])->assertOk()->json();
        $this->assertSame('pending', $this->db->table('booking')->where('id', $booking['id'])->value('status'));

        $protocol = new VnpayProtocol(config('services.vnpay'));
        $callback = [
            'vnp_TxnRef' => $started['txn_ref'],
            'vnp_TmnCode' => 'TEST0001',
            'vnp_Amount' => (string) (int) str_replace('.', '', $started['amount']),
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '990001',
            'vnp_PayDate' => '20261012150000',
        ];
        $callback['vnp_SecureHash'] = $protocol->sign($callback);
        $this->getJson('/api/payments/vnpay-return?' . http_build_query($callback))
            ->assertOk()->assertJsonPath('outcome', 'awaiting_ipn');
        $callback['vnp_Amount'] = (string) ((int) $callback['vnp_Amount'] + 100);
        $callback['vnp_SecureHash'] = $protocol->sign($callback);
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($callback))
            ->assertOk()->assertJsonPath('RspCode', '04')->assertJsonPath('outcome', 'amount_mismatch_reconciliation');
        $this->assertSame('pending', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame('pending', $this->db->table('payment')->where('id', $started['payment_id'])->value('payment_status'));
        $this->assertSame('amount_mismatch_reconciliation', $this->db->table('payment_gateway_receipt')->value('outcome'));
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($callback))
            ->assertOk()->assertJsonPath('RspCode', '02');

        $validBooking = $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-20',
            'check_out' => '2026-10-22',
            'payment_method' => 'vnpay',
        ])->assertCreated()->json('booking');
        $validPayment = $this->postJson('/api/payments/create', ['booking_id' => $validBooking['id']])->assertOk()->json();
        $validCallback = [
            'vnp_TxnRef' => $validPayment['txn_ref'],
            'vnp_TmnCode' => 'TEST0001',
            'vnp_Amount' => (string) (int) str_replace('.', '', $validPayment['amount']),
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '990002',
            'vnp_PayDate' => '20261012150000',
        ];
        $validCallback['vnp_SecureHash'] = $protocol->sign($validCallback);
        $this->getJson('/api/payments/vnpay-return?' . http_build_query($validCallback))
            ->assertOk()->assertJsonPath('outcome', 'awaiting_ipn');
        $this->assertSame('pending', $this->db->table('booking')->where('id', $validBooking['id'])->value('status'));
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($validCallback))
            ->assertOk()->assertJsonPath('RspCode', '00')->assertJsonPath('outcome', 'success');
        $this->assertSame('confirmed', $this->db->table('booking')->where('id', $validBooking['id'])->value('status'));
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($validCallback))
            ->assertOk()->assertJsonPath('RspCode', '02');
        $this->assertSame(1, $this->db->table('booking_effect')->where('payment_id', $validPayment['payment_id'])->count());
        $this->assertSame(2, $this->db->table('payment_gateway_receipt')->where('provider', 'vnpay')->count());
    }

    public function test_signed_vnpay_amount_mismatch_is_audited_and_never_confirms(): void
    {
        RoomTypeV2Integration::backend();
        $this->actingAs(\App\Models\Customer::findOrFail(9001), 'sanctum');
        $this->db->table('room_type')->where('id', $this->type)->update([
            'hotel_id' => 1,
            'base_price' => '100000.00',
            'capacity' => 2,
            'total_rooms' => 10,
            'status' => 'active',
        ]);
        config([
            'roomtype-v2.vnpay_merchant_verified' => true,
            'services.vnpay' => [
                'tmn_code' => 'TEST0001',
                'hash_secret' => str_repeat('synthetic-test-secret-', 3),
                'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
                'return_url' => 'https://payments.example.com/payment/result',
                'ipn_url' => 'https://payments.example.com/api/payments/vnpay-ipn',
            ],
        ]);
        $booking = $this->postJson('/api/bookings', [
            'hotel_id' => 1,
            'room_type_id' => $this->type,
            'quantity' => 1,
            'num_guests' => 2,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'payment_method' => 'vnpay',
        ])->assertCreated()->json('booking');
        $payment = $this->postJson('/api/payments/create', ['booking_id' => $booking['id']])->assertOk()->json();
        $callback = [
            'vnp_TxnRef' => $payment['txn_ref'],
            'vnp_TmnCode' => 'TEST0001',
            'vnp_Amount' => (string) ((int) str_replace('.', '', $payment['amount']) + 100),
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '990003',
            'vnp_PayDate' => '20261012150000',
        ];
        $callback['vnp_SecureHash'] = (new VnpayProtocol(config('services.vnpay')))->sign($callback);

        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($callback))
            ->assertOk()->assertJsonPath('RspCode', '04')->assertJsonPath('outcome', 'amount_mismatch_reconciliation');
        $this->assertSame('pending', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame('pending', $this->db->table('payment')->where('id', $payment['payment_id'])->value('payment_status'));
        $this->assertSame('amount_mismatch_reconciliation', $this->db->table('payment_gateway_receipt')->value('outcome'));
        $this->assertSame('200001.00', $this->db->table('payment_gateway_receipt')->value('amount'));
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($callback))
            ->assertOk()->assertJsonPath('RspCode', '02');
        $this->assertSame(1, $this->db->table('payment_gateway_receipt')->count());
        $this->assertSame(0, $this->db->table('booking_effect')->count());

        $unmatched = array_replace($callback, [
            'vnp_TxnRef' => 'UNKNOWN_TEST_REFERENCE',
            'vnp_Amount' => '10000',
            'vnp_TransactionNo' => '990004',
        ]);
        $unmatched['vnp_SecureHash'] = (new VnpayProtocol(config('services.vnpay')))->sign($unmatched);
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($unmatched))
            ->assertOk()->assertJsonPath('RspCode', '00')->assertJsonPath('outcome', 'reference_unmatched_reconciliation');
        $this->getJson('/api/payments/vnpay-ipn?' . http_build_query($unmatched))
            ->assertOk()->assertJsonPath('RspCode', '02');
        $this->assertSame(1, $this->db->table('payment_gateway_unmatched_event')->count());
        $reconciliation = app(\App\Http\Controllers\PreparedV2\ReconciliationController::class)->index()->getData(true);
        $this->assertSame('reference_unmatched_reconciliation', $reconciliation['unmatched_gateway'][0]['outcome']);
    }

    public function test_invalid_api_key_and_invalid_hmac_never_write_financial_state(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $event = $this->event($payment);
        $this->enableWebhook();
        $this->sendEvent($event, ['HTTP_AUTHORIZATION' => 'Apikey wrong'])->assertUnauthorized();
        $this->assertSame(0, $this->db->table('sepay_webhook_event')->count());

        config(['services.sepay.webhook.auth_method' => 'hmac_sha256']);
        $timestamp = (string) now()->timestamp;
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', ($timestamp - 301) . '.' . $body, 'synthetic-sepay-hmac-secret');
        $this->sendRaw($body, [
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
            'HTTP_X_SEPAY_TIMESTAMP' => (string) ($timestamp - 301),
        ])->assertUnauthorized();
        $this->assertSame('pending', $this->paymentStatus($payment));
        $this->assertSame(0, $this->db->table('sepay_webhook_event')->count());
    }

    public function test_hmac_sha256_uses_sepay_raw_body_timestamp_and_signature_contract(): void
    {
        config(['services.sepay.webhook.auth_method' => 'hmac_sha256']);
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $event = $this->event($payment);
        $this->enableWebhook();
        $timestamp = (string) now()->timestamp;
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, 'synthetic-sepay-hmac-secret');

        $this->sendRaw($body, [
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
        ])->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('success', $this->db->table('sepay_webhook_event')->value('outcome'));
        $this->assertSame('confirmed', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
    }

    public function test_wrong_amount_and_reference_are_audited_without_confirmation(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $this->enableWebhook();

        $this->sendEvent($this->event($payment, ['transferAmount' => 1]))
            ->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('amount_mismatch_reconciliation', $this->db->table('sepay_webhook_event')->where('provider_transaction_id', '92704')->value('outcome'));
        $this->assertSame('pending', $this->paymentStatus($payment));
        $this->assertSame('pending', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame(0, $this->db->table('booking_effect')->count());

        $this->sendEvent($this->event($payment, ['id' => 92708, 'accountNumber' => '9999999999']))
            ->assertOk()->assertExactJson(['success' => true]);
        $this->sendEvent($this->event($payment, ['id' => 92709, 'transferType' => 'out']))
            ->assertOk()->assertExactJson(['success' => true]);
        $this->sendEvent($this->event($payment, ['id' => 92705, 'code' => 'NOT-A-PAYMENT', 'content' => 'NOT-A-PAYMENT']))
            ->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('pending', $this->paymentStatus($payment));
        $this->assertSame(1, $this->db->table('payment_gateway_receipt')->count());
        $this->assertSame(4, $this->db->table('sepay_webhook_event')->count());
        $reconciliation = app(\App\Http\Controllers\PreparedV2\ReconciliationController::class)->index()->getData(true);
        $this->assertSame(
            ['reference_unmatched_reconciliation', 'account_mismatch_reconciliation'],
            array_column($reconciliation['sepay_events'], 'outcome')
        );
    }

    public function test_duplicate_delivery_uses_unique_provider_transaction_id(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $event = $this->event($payment);
        $this->enableWebhook();

        $this->sendEvent($event)->assertOk()->assertExactJson(['success' => true]);
        $this->sendEvent($event)->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(1, $this->db->table('sepay_webhook_event')->count());
        $this->assertSame(1, $this->db->table('payment_gateway_receipt')->count());
        $this->assertSame(1, $this->db->table('booking_effect')->count());

        $this->sendEvent($this->event($payment, ['transferAmount' => 1]))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(1, $this->db->table('booking_effect')->count());
    }

    public function test_cancelled_expired_and_past_deadline_bookings_are_never_restored(): void
    {
        $cancelled = $this->createBooking();
        $cancelledPayment = $this->payment($cancelled);
        $this->db->table('booking')->where('id', $cancelled['id'])->update(['status' => 'cancelled']);
        $expired = $this->createBooking(9002);
        $expiredPayment = $this->payment($expired);
        $this->db->table('booking')->where('id', $expired['id'])->update(['status' => 'expired']);
        $pastDeadline = $this->createBooking(9003);
        $pastPayment = $this->payment($pastDeadline);
        $this->db->table('booking')->where('id', $pastDeadline['id'])->update(['expires_at' => '2026-10-12 07:59:59']);
        $this->enableWebhook();

        $this->sendEvent($this->event($cancelledPayment))->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('late_reconciliation', $this->db->table('sepay_webhook_event')->where('provider_transaction_id', '92704')->value('outcome'));
        $this->assertSame('cancelled', $this->db->table('booking')->where('id', $cancelled['id'])->value('status'));
        $this->assertSame(0, $this->db->table('booking_effect')->count());
        $this->sendEvent($this->event($expiredPayment, ['id' => 92706]))->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('late_reconciliation', $this->db->table('sepay_webhook_event')->where('provider_transaction_id', '92706')->value('outcome'));
        $this->assertSame('expired', $this->db->table('booking')->where('id', $expired['id'])->value('status'));
        $this->assertSame(0, $this->db->table('booking_effect')->count());
        $this->sendEvent($this->event($pastPayment, ['id' => 92707]))->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame('late_reconciliation', $this->db->table('sepay_webhook_event')->where('provider_transaction_id', '92707')->value('outcome'));
        $this->assertSame('pending', $this->db->table('booking')->where('id', $pastDeadline['id'])->value('status'));
        $this->assertSame(0, $this->db->table('booking_effect')->count());
    }

    public function test_webhook_and_admin_verification_racing_share_one_payment_lock_and_effect(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $event = $this->event($payment);
        $this->enableWebhook();
        $admin = [
            'action' => 'manual',
            'booking_id' => $booking['id'],
            'payment_id' => $payment->id,
            'admin_id' => 9002,
            'input' => [
                'reference' => $event['referenceCode'],
                'amount' => $payment->amount,
                'received_at' => Carbon::parse($event['transactionDate'], 'Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:sP'),
                'funds_received' => true,
                'evidence_source' => 'bank_statement',
            ],
        ];
        $results = $this->runConcurrent([
            ['action' => 'sepay', 'event' => $event],
            $admin,
        ]);

        $this->assertCount(2, $results);
        $this->assertNotSame($results[0]['connection_id'], $results[1]['connection_id']);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success'] || $results[1]['rejected']);
        $this->assertSame('success', $this->paymentStatus($payment));
        $this->assertSame('confirmed', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame(1, $this->db->table('booking_effect')->count());
        $this->assertLessThanOrEqual(1, $this->db->table('payment_gateway_receipt')->where('provider', 'sepay')->count());
    }

    public function test_webhook_racing_expiry_worker_records_late_funds_without_resurrection(): void
    {
        $booking = $this->createBooking();
        $payment = $this->payment($booking);
        $this->db->table('booking')->where('id', $booking['id'])->update(['expires_at' => '2026-10-12 07:59:59']);
        $this->enableWebhook();

        $results = $this->runConcurrent([
            ['action' => 'sepay', 'event' => $this->event($payment)],
            ['action' => 'expire', 'booking_id' => $booking['id']],
        ]);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertSame('late_reconciliation', $this->db->table('sepay_webhook_event')->value('outcome'));
        $this->assertSame('cancelled', $this->db->table('booking')->where('id', $booking['id'])->value('status'));
        $this->assertSame('success', $this->paymentStatus($payment));
        $this->assertSame(0, $this->db->table('booking_effect')->count());
    }

    private function createBooking(int $customerId = 9001): array
    {
        $amount = '200000.00';
        $id = $this->db->table('booking')->insertGetId([
            'customer_id' => $customerId,
            'hotel_id' => 1,
            'check_in' => '2026-10-15',
            'check_out' => '2026-10-17',
            'num_guests' => 2,
            'total_price' => $amount,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);
        $this->db->table('booking_room')->insert([
            'booking_id' => $id,
            'room_type_id' => $this->type,
            'room_id' => null,
            'quantity' => 1,
            'nights' => 2,
            'price_at_booking' => '100000.00',
        ]);
        $this->db->table('payment')->insert([
            'booking_id' => $id,
            'amount' => $amount,
            'payment_method' => 'banking',
            'payment_status' => 'pending',
            'created_at' => now(),
            'transaction_id' => 'MANUAL_' . $id . '_' . bin2hex(random_bytes(12)),
        ]);
        return (array) $this->db->table('booking')->where('id', $id)->first();
    }

    private function enableWebhook(): void
    {
        config(['roomtype-v2.integration_complete' => true]);
    }

    private function payment(array $booking): object
    {
        return $this->db->table('payment')->where('booking_id', $booking['id'])->where('payment_method', 'banking')->first();
    }

    private function paymentStatus(object $payment): string
    {
        return $this->db->table('payment')->where('id', $payment->id)->value('payment_status');
    }

    private function event(object $payment, array $overrides = []): array
    {
        $localDate = Carbon::parse($payment->created_at, config('app.timezone'))
            ->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        return array_replace([
            'id' => 92704,
            'gateway' => 'SyntheticBank',
            'transactionDate' => $localDate,
            'accountNumber' => '1234567890',
            'subAccount' => '',
            'code' => $payment->transaction_id,
            'content' => $payment->transaction_id . ' chuyen tien',
            'transferType' => 'in',
            'description' => 'Synthetic test transfer',
            'transferAmount' => (int) $payment->amount,
            'accumulated' => 100000000,
            'referenceCode' => 'FTTEST-' . $payment->id,
        ], $overrides);
    }

    private function sendEvent(array $event, array $headers = [])
    {
        $body = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $this->sendRaw($body, array_merge(['HTTP_AUTHORIZATION' => 'Apikey synthetic-sepay-api-key'], $headers));
    }

    private function sendRaw(string $body, array $headers)
    {
        return $this->call('POST', '/api/webhooks/sepay', [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $headers), $body);
    }

    private function runConcurrent(array $requests): array
    {
        $settings = realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'));
        $runtime = dirname($settings);
        $paths = [];
        $processes = [];
        $this->db->beginTransaction();
        try {
            $this->db->table('hotel')->where('id', 1)->lockForUpdate()->first();
            foreach ($requests as $request) {
                $path = $runtime . DIRECTORY_SEPARATOR . 'ready-worker-' . bin2hex(random_bytes(6)) . '.json';
                file_put_contents($path, json_encode($request, JSON_THROW_ON_ERROR));
                $paths[] = $path;
                $process = new Process(
                    [PHP_BINARY, base_path('tests/Support/sepay_webhook_worker.php'), $path],
                    base_path(),
                    [
                        'AUREL_ROOMTYPE_V2_TEST_SETTINGS' => $settings,
                        'AUREL_ROOMTYPE_V2_CASE_DATABASE' => $this->db->getDatabaseName(),
                    ]
                );
                $process->setTimeout(35);
                $process->start();
                $processes[] = $process;
            }
            usleep(300000);
        } finally {
            $this->db->commit();
        }

        $results = [];
        foreach ($processes as $index => $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
            $resultPath = $paths[$index] . '.result';
            $results[] = json_decode(file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
            unlink($resultPath);
            unlink($paths[$index]);
        }
        return $results;
    }
}
