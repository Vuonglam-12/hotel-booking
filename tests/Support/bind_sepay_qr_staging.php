<?php

if (count($argv) !== 3) throw new RuntimeException('Pass staging-session.json and the verified private QR profile.');
$workspace = realpath(dirname(__DIR__, 2));
$ownedRoot = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR;
$stagePath = realpath($argv[1]);
$profilePath = realpath($argv[2]);
if (!$stagePath || !$profilePath || !str_starts_with($stagePath, $ownedRoot . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR)
    || !str_starts_with($profilePath, $ownedRoot) || basename($stagePath) !== 'staging-session.json') {
    throw new RuntimeException('Owned synthetic staging and private QR profile required.');
}
$stage = json_decode(file_get_contents($stagePath), true, 512, JSON_THROW_ON_ERROR);
$profile = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($profilePath)), true, 512, JSON_THROW_ON_ERROR);
if (($stage['synthetic_only'] ?? false) !== true || ($stage['database'] ?? null) === 'hotel_booking'
    || ($profile['version'] ?? null) !== 1 || ($profile['mode'] ?? null) !== 'sepay_test'
    || ($profile['dashboard_verified'] ?? false) !== true || ($profile['qr_matches_account'] ?? false) !== true) {
    throw new RuntimeException('QR profile is not explicitly verified for SePay Test Mode.');
}
$verifiedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $profile['verified_at'] ?? '', new DateTimeZone('UTC'));
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
if (!$verifiedAt || $verifiedAt > $now->modify('+5 minutes') || $verifiedAt < $now->modify('-24 hours')) {
    throw new RuntimeException('Dashboard verification must be repeated within 24 hours of staging binding.');
}
$settings = realpath($stage['settings_file'] ?? '');
if (!$settings || dirname($settings) !== dirname($stagePath)) throw new RuntimeException('Staging settings binding is invalid.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settings);
putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE=' . $stage['database']);
require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require $workspace . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = App\Support\RoomTypeV2TestDatabase::connection('mysql');
$identity = $db->selectOne('SELECT DATABASE() db, @@server_uuid uuid');
$banking = $profile['banking_instructions'] ?? null;
config(['roomtype-v2.banking_instructions' => $banking]);
if (!App\Support\RoomTypeV2PaymentConfiguration::banking()) throw new RuntimeException('QR profile does not satisfy the Banking contract.');
$artifactPath = dirname($stagePath) . DIRECTORY_SEPARATOR . 'qr-banking-staging.json';
$artifact = [
    'version' => 1, 'synthetic_only' => true, 'mode' => 'sepay_test',
    'database' => $identity->db, 'server_uuid' => $identity->uuid,
    'settings_file' => $settings, 'created_at' => $now->format('Y-m-d\TH:i:sP'),
    'expires_at' => $now->modify('+4 hours')->format('Y-m-d\TH:i:sP'),
    'profile_sha256' => hash_file('sha256', $profilePath),
    'banking_instructions' => $banking,
];
file_put_contents($artifactPath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$stage['qr_banking_descriptor'] = $artifactPath;
file_put_contents($stagePath, json_encode($stage, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['qr_banking_descriptor' => $artifactPath, 'database' => $identity->db,
    'server_uuid' => $identity->uuid, 'expires_at' => $artifact['expires_at'], 'production_access' => false], JSON_THROW_ON_ERROR);
