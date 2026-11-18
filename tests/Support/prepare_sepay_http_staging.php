<?php

if (count($argv) !== 3) {
    throw new RuntimeException('Pass the owned test-environment.json path and Test mode account number.');
}

$settingsPath = realpath($argv[1]);
$accountNumber = $argv[2];
if (!$settingsPath || !preg_match('/^[0-9]{4,30}$/D', $accountNumber)) {
    throw new RuntimeException('Owned test settings and a numeric Test mode account number are required.');
}

putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settingsPath);
require __DIR__ . '/roomtype_v2_test_bootstrap.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = Tests\Support\RoomTypeV2ReadinessFixture::initialize();
(require base_path('database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php'))->up();
(require base_path('database/migrations/2026_10_12_000008_add_booking_delivery_safety.php'))->up();
(require base_path('database/migrations/2026_10_17_000001_create_sepay_webhook_events.php'))->up();

$now = Illuminate\Support\Carbon::now();
$bookingId = $db->table('booking')->insertGetId([
    'customer_id' => 9001,
    'hotel_id' => 1,
    'check_in' => $now->copy()->addDays(32)->toDateString(),
    'check_out' => $now->copy()->addDays(34)->toDateString(),
    'num_guests' => 2,
    'total_price' => '200000.00',
    'status' => 'pending',
    'expires_at' => $now->copy()->addDays(30),
    'created_at' => $now,
]);

$db->table('booking_room')->insert([
    'booking_id' => $bookingId,
    'room_type_id' => 1,
    'room_id' => null,
    'quantity' => 1,
    'nights' => 2,
    'price_at_booking' => '100000.00',
]);

$reference = 'AURELSEPAY' . strtoupper(App\Support\RoomTypeV2TestDatabase::settings()['run_id']);
$paymentId = $db->table('payment')->insertGetId([
    'booking_id' => $bookingId,
    'amount' => '200000.00',
    'payment_method' => 'banking',
    'payment_status' => 'pending',
    'created_at' => $now,
    'transaction_id' => $reference,
]);

$runtime = dirname($settingsPath);
$descriptorPath = $runtime . DIRECTORY_SEPARATOR . 'sepay-staging.json';
$descriptor = [
    'version' => 1,
    'synthetic_only' => true,
    'runtime' => $runtime,
    'settings_file' => $settingsPath,
    'mysql_descriptor' => $runtime . DIRECTORY_SEPARATOR . 'server.json',
    'database' => $db->getDatabaseName(),
    'booking_id' => $bookingId,
    'payment_id' => $paymentId,
    'payment_reference' => $reference,
    'amount_vnd' => 200000,
    'account_number' => $accountNumber,
    'auth_method' => 'hmac_sha256',
];

file_put_contents($descriptorPath, json_encode($descriptor, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode([
    'staging_descriptor' => $descriptorPath,
    'database' => $db->getDatabaseName(),
    'booking_id' => $bookingId,
    'payment_id' => $paymentId,
    'payment_reference' => $reference,
    'amount_vnd' => 200000,
    'test_account_number' => $accountNumber,
    'synthetic_only' => true,
    'production_access' => false,
], JSON_THROW_ON_ERROR);
