<?php

putenv('HOLIDAYVIET_TEST_DATABASE=' . ($argv[1] ?? ''));
require __DIR__ . '/booking_test_bootstrap.php';

use App\Http\Controllers\AdminController;
use App\Models\Customer;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$job = $argv[2] ?? '';
$directory = realpath($argv[3] ?? '');
$testingRoot = realpath(storage_path('framework/testing'));
$payload = json_decode($argv[4] ?? '', true, 512, JSON_THROW_ON_ERROR);

if (!$directory || !$testingRoot
    || !str_starts_with($directory, $testingRoot . DIRECTORY_SEPARATOR . 'booking-gate-')
    || DB::selectOne('SELECT DATABASE() AS name')->name !== getenv('HOLIDAYVIET_TEST_DATABASE')) {
    throw new RuntimeException('Unsafe concurrency test environment.');
}

Mail::fake();
file_put_contents($directory . '/' . $job . '-connected', (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);

$waitForRelease = static function (string $path): void {
    $deadline = microtime(true) + 15;
    while (!is_file($path)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency test barrier timed out.');
        }
        usleep(20000);
        clearstatcache(true, $path);
    }
};

try {
    if ($job === 'booking') {
        if ($payload['pause_booking'] ?? false) {
            $paused = false;
            DB::listen(static function ($query) use ($directory, $waitForRelease, &$paused): void {
                $sql = strtolower($query->sql);
                if (!$paused && str_contains($sql, 'from ' . chr(96) . 'hotel' . chr(96)) && str_contains($sql, 'for update')) {
                    $paused = true;
                    file_put_contents($directory . '/booking-locked', '1');
                    $waitForRelease($directory . '/release-booking');
                }
            });
        }
        $booking = $app->make(BookingService::class)->create($payload['customer_id'], $payload['data']);
        $result = ['ok' => true, 'booking_id' => $booking->id];
    } elseif ($job === 'admin') {
        DB::beginTransaction();
        $admin = Customer::findOrFail($payload['admin_id']);
        $request = Request::create('/api/admin/hotels/' . $payload['hotel_id'] . '/status', 'PUT', ['status' => 'inactive']);
        $request->setUserResolver(static fn () => $admin);
        $app->make(AdminController::class)->updateHotelStatus($request, $payload['hotel_id']);
        file_put_contents($directory . '/admin-updated', '1');
        $waitForRelease($directory . '/release-admin');
        DB::commit();
        $result = ['ok' => true];
    } else {
        throw new RuntimeException('Unknown concurrency test job.');
    }
} catch (ValidationException $exception) {
    $result = ['ok' => false, 'errors' => $exception->errors()];
} catch (Throwable $exception) {
    $result = ['ok' => false, 'exception' => get_class($exception), 'message' => $exception->getMessage()];
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

file_put_contents($directory . '/' . $job . '-result.json', json_encode($result, JSON_THROW_ON_ERROR));
