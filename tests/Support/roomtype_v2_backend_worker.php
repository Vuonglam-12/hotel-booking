<?php

require __DIR__ . '/roomtype_v2_test_bootstrap.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Carbon\Carbon::setTestNow('2026-10-12 08:00:00');
if (count($argv) !== 2) throw new RuntimeException('Pass an owned private worker request.');
$path = realpath($argv[1]);
$owned = dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
if (!$path || dirname($path) !== $owned || !preg_match('/^worker-[a-f0-9]{12}\.json$/D', basename($path))) throw new RuntimeException('Unsafe worker path.');
$request = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$db = App\Support\RoomTypeV2TestDatabase::connection('mysql');
$connectionId = (int) $db->selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($path . '.ready', (string) $connectionId);
try {
    $booking = app(App\Services\BookingServiceV2::class);
    $lifecycle = app(App\Services\BookingLifecycleServiceV2::class);
    $result = match ($request['action']) {
        'create' => $booking->create('mysql', $request['customer_id'], $request['data']),
        'cancel' => $lifecycle->cancel('mysql', $request['id'], $request['customer_id']),
        'expire' => $lifecycle->expire('mysql', $request['id']),
        'transition' => $lifecycle->transition('mysql', $request['id'], $request['target']),
        'callback' => $lifecycle->verifiedPaymentSuccess('mysql', $request['id'], $request['payment_id'], $request['transaction_id'], $request['amount']),
        'total' => app(App\Services\RoomTypeInventoryServiceV2::class)->changeTotal('mysql', $request['hotel_id'], $request['type_id'], $request['new_total']),
        default => throw new RuntimeException('Unknown worker action.'),
    };
    file_put_contents($path . '.result', json_encode(['success' => true, 'connection_id' => $connectionId, 'result' => $result], JSON_THROW_ON_ERROR));
} catch (Illuminate\Validation\ValidationException $error) {
    file_put_contents($path . '.result', json_encode(['success' => false, 'connection_id' => $connectionId, 'errors' => $error->errors()], JSON_THROW_ON_ERROR));
} catch (Throwable $error) {
    file_put_contents($path . '.result', json_encode(['success' => false, 'connection_id' => $connectionId, 'unexpected' => get_class($error), 'message' => $error->getMessage()], JSON_THROW_ON_ERROR));
    exit(2);
}
