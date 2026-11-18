<?php

if (count($argv) !== 2) {
    throw new RuntimeException('Pass the private SePay staging descriptor.');
}

$workspace = realpath(dirname(__DIR__, 2));
$descriptorPath = realpath($argv[1]);
$ownedRoot = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app'
    . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR;

if (!$descriptorPath || !str_starts_with($descriptorPath, $ownedRoot)
    || basename($descriptorPath) !== 'sepay-staging.json') {
    throw new RuntimeException('Owned SePay staging descriptor required.');
}

$descriptor = json_decode(file_get_contents($descriptorPath), true, 512, JSON_THROW_ON_ERROR);
$settingsPath = realpath($descriptor['settings_file'] ?? '');
if (($descriptor['synthetic_only'] ?? false) !== true || !$settingsPath
    || dirname($settingsPath) !== dirname($descriptorPath)
    || ($descriptor['database'] ?? null) === 'hotel_booking') {
    throw new RuntimeException('Descriptor is not bound to an owned synthetic database.');
}

putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settingsPath);
require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require $workspace . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = Illuminate\Support\Facades\DB::connection('mysql');
$identity = $db->selectOne('SELECT DATABASE() db, @@port port, @@server_uuid uuid');
$settings = App\Support\RoomTypeV2TestDatabase::settings();
if ($identity->db !== $descriptor['database'] || $identity->db !== $settings['database']
    || (int) $identity->port !== (int) $settings['port'] || $identity->uuid !== $settings['server_uuid']) {
    throw new RuntimeException('Connected MySQL identity does not match the SePay artifact.');
}

$event = $db->table('sepay_webhook_event')->orderByDesc('id')->first();
$receipt = $db->table('payment_gateway_receipt')->where('provider', 'sepay')->orderByDesc('id')->first();
$booking = $db->table('booking')->where('id', $descriptor['booking_id'])->first();
$payment = $db->table('payment')->where('id', $descriptor['payment_id'])->first();
$payload = $event ? json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR) : [];

echo json_encode([
    'identity_match' => true,
    'database' => $identity->db,
    'server_uuid' => $identity->uuid,
    'sepay_events' => $db->table('sepay_webhook_event')->count(),
    'event_outcomes' => $db->table('sepay_webhook_event')->orderBy('id')->pluck('outcome')->all(),
    'latest_event_outcome' => $event?->outcome,
    'latest_payload_keys' => array_values(array_keys($payload)),
    'latest_payload_gateway' => $payload['gateway'] ?? null,
    'latest_payload_account_matches' => isset($payload['accountNumber'])
        && hash_equals((string) $descriptor['account_number'], (string) $payload['accountNumber']),
    'sepay_receipts' => $db->table('payment_gateway_receipt')->where('provider', 'sepay')->count(),
    'latest_receipt_outcome' => $receipt?->outcome,
    'booking_status' => $booking?->status,
    'payment_status' => $payment?->payment_status,
    'invoice_count' => $db->table('invoice')->where('booking_id', $descriptor['booking_id'])->count(),
    'effect_states' => $db->table('booking_effect')->where('booking_id', $descriptor['booking_id'])->pluck('state')->all(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
