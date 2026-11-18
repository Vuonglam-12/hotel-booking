<?php

require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Carbon\Carbon::setTestNow('2026-10-12 08:00:00');
config([
    'roomtype-v2.integration_complete' => true,
    'roomtype-v2.final_integration' => true,
    'roomtype-v2.delivery_safety' => true,
    'services.sepay.webhook.enabled' => true,
    'services.sepay.webhook.mode' => 'test',
    'services.sepay.webhook.auth_method' => 'api_key',
    'services.sepay.webhook.api_key' => 'synthetic-sepay-api-key',
    'services.sepay.webhook.hmac_secret' => 'synthetic-sepay-hmac-secret',
    'services.sepay.webhook.account_number' => '1234567890',
]);

$path = realpath($argv[1] ?? '');
$runtime = dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
if (!$path || dirname($path) !== $runtime || !preg_match('/^ready-worker-[a-f0-9]{12}\.json$/D', basename($path))) {
    throw new RuntimeException('Unsafe SePay concurrency worker path.');
}
$request = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$db = App\Support\RoomTypeV2TestDatabase::connection('mysql');
$connectionId = (int) $db->selectOne('SELECT CONNECTION_ID() AS id')->id;

try {
    $result = match ($request['action']) {
        'sepay' => app(App\Services\SePayWebhookServiceV2::class)->receive(
            json_encode($request['event'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        ),
        'manual' => app(App\Services\ManualBankingPaymentServiceV2::class)->verify(
            'mysql',
            (int) $request['booking_id'],
            (int) $request['payment_id'],
            (int) $request['admin_id'],
            $request['input']
        ),
        'expire' => ['expired' => app(App\Services\BookingLifecycleServiceV2::class)->expire('mysql', (int) $request['booking_id'])],
        default => throw new RuntimeException('Unknown SePay concurrency action.'),
    };
    file_put_contents($path . '.result', json_encode([
        'success' => true,
        'connection_id' => $connectionId,
        'result' => $result,
    ], JSON_THROW_ON_ERROR));
} catch (Illuminate\Validation\ValidationException $e) {
    file_put_contents($path . '.result', json_encode([
        'success' => false,
        'rejected' => true,
        'connection_id' => $connectionId,
        'errors' => $e->errors(),
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    file_put_contents($path . '.result', json_encode([
        'success' => false,
        'unexpected' => get_class($e),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(2);
}
