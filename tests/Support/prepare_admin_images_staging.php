<?php

if (!isset($argv[1]) || !realpath($argv[1])) throw new RuntimeException('Owned test settings required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . realpath($argv[1]));
require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$db, $types] = Tests\Support\AdminImagesFixture::initialize();
$runtime = dirname(realpath($argv[1]));
$token = App\Models\Customer::findOrFail(9004)->createToken('admin_token', ['admin'])->plainTextToken;
$stage = ['database' => $db->getDatabaseName(), 'runtime' => $runtime, 'settings_file' => realpath($argv[1]),
    'synthetic_only' => true, 'image_only' => true, 'types' => $types, 'admin_token' => $token];
file_put_contents($runtime . '/admin-images-session.json', json_encode($stage, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['descriptor' => $runtime . '/admin-images-session.json', 'database' => $db->getDatabaseName(), 'checkout_enabled' => false]);
