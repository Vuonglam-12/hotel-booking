<?php

declare(strict_types=1);

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($method !== 'POST' || $path !== '/api/webhooks/sepay') {
    respond(404, ['success' => false, 'message' => 'Not found.']);
}

$workspace = realpath(dirname(__DIR__, 2));
$descriptorPath = getenv('AUREL_SEPAY_STAGING_DESCRIPTOR');
$resolvedDescriptor = is_string($descriptorPath) ? realpath($descriptorPath) : false;
$runtimeRoot = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app'
    . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR;
if (!$resolvedDescriptor || !str_starts_with($resolvedDescriptor, $runtimeRoot)
    || basename($resolvedDescriptor) !== 'sepay-staging.json') {
    respond(503, ['success' => false, 'message' => 'Isolated staging is unavailable.']);
}

try {
    $stage = json_decode(file_get_contents($resolvedDescriptor), true, 512, JSON_THROW_ON_ERROR);
    $settingsFile = realpath($stage['settings_file'] ?? '');
    $mysqlDescriptor = realpath($stage['mysql_descriptor'] ?? '');
    if (($stage['synthetic_only'] ?? false) !== true
        || ($stage['auth_method'] ?? null) !== 'hmac_sha256'
        || realpath($stage['runtime'] ?? '') !== dirname($resolvedDescriptor)
        || !$settingsFile || dirname($settingsFile) !== dirname($resolvedDescriptor)
        || basename($settingsFile) !== 'test-environment.json'
        || !$mysqlDescriptor || dirname($mysqlDescriptor) !== dirname($resolvedDescriptor)
        || basename($mysqlDescriptor) !== 'server.json'
        || ($stage['database'] ?? null) !== preg_replace('/[^a-z0-9_]/', '', $stage['database'] ?? '')) {
        throw new RuntimeException('Invalid synthetic staging descriptor.');
    }

    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $settingsFile);
    putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE=' . $stage['database']);
    require_once __DIR__ . '/roomtype_v2_test_bootstrap.php';

    $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config([
        'app.debug' => false,
        'roomtype-v2.integration_complete' => true,
        'roomtype-v2.final_integration' => true,
        'roomtype-v2.delivery_safety' => true,
        'roomtype-v2.prepared_routes' => false,
        'roomtype-v2.staging_checkout' => false,
        'roomtype-v2.production_manifest' => null,
        'services.sepay.webhook.enabled' => true,
        'services.sepay.webhook.mode' => 'test',
        'services.sepay.webhook.auth_method' => 'hmac_sha256',
        'services.sepay.webhook.account_number' => $stage['account_number'],
        'services.sepay.webhook.hmac_secret' => getenv('SEPAY_WEBHOOK_HMAC_SECRET'),
    ]);

    App\Support\RoomTypeV2TestDatabase::connection('mysql');
    app(App\Services\SePayWebhookServiceV2::class)->assertOperational();

    $request = Illuminate\Http\Request::capture();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    exit;
} catch (Throwable $exception) {
    error_log('SePay isolated HTTP staging request failed: ' . $exception->getMessage());
    respond(503, ['success' => false, 'message' => 'Isolated staging guard failed.']);
}
