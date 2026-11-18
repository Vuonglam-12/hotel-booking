<?php

if (count($argv) !== 2) {
    throw new RuntimeException('Pass the owned staging-session.json path.');
}

$workspace = realpath(dirname(__DIR__, 2));
$ownedRoot = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app'
    . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR;
$stagePath = realpath($argv[1]);
if (!$stagePath || !str_starts_with($stagePath, $ownedRoot) || basename($stagePath) !== 'staging-session.json') {
    throw new RuntimeException('Owned synthetic staging descriptor required.');
}

$stage = json_decode(file_get_contents($stagePath), true, 512, JSON_THROW_ON_ERROR);
$settings = realpath($stage['settings_file'] ?? '');
if (($stage['synthetic_only'] ?? false) !== true || ($stage['database'] ?? null) === 'hotel_booking'
    || !$settings || dirname($settings) !== dirname($stagePath)) {
    throw new RuntimeException('Primary database access refused.');
}

putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settings);
putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE=' . $stage['database']);
require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require $workspace . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = App\Support\RoomTypeV2TestDatabase::connection('mysql');
if ($db->getDatabaseName() !== $stage['database']) {
    throw new RuntimeException('Synthetic database identity mismatch.');
}

$created = false;
if (!$db->getSchemaBuilder()->hasTable('sepay_webhook_event')) {
    (require $workspace . '/database/migrations/2026_10_17_000001_create_sepay_webhook_events.php')->up();
    $created = true;
}

echo json_encode(['synthetic_only' => true, 'production_access' => false,
    'sepay_event_schema_ready' => true, 'created' => $created], JSON_THROW_ON_ERROR);
