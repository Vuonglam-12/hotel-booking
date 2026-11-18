<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2);
$sourceEnvironment = Dotenv\Dotenv::createArrayBacked($projectRoot)->safeLoad();
$testDatabase = getenv('HOLIDAYVIET_TEST_DATABASE');

// Refuse to boot the write tests against the application's database.
if (!is_string($testDatabase)
    || !preg_match('/^holidayviet_booking_test_[a-f0-9]{12}$/D', $testDatabase)
    || $testDatabase === ($sourceEnvironment['DB_DATABASE'] ?? null)
    || !in_array($sourceEnvironment['DB_HOST'] ?? '127.0.0.1', ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Set HOLIDAYVIET_TEST_DATABASE to a separate, schema-only local MySQL test database.');
}

$testEnvironment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $projectRoot . '/storage/framework/testing/booking-gate-config.php',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => $sourceEnvironment['DB_HOST'] ?? '127.0.0.1',
    'DB_PORT' => $sourceEnvironment['DB_PORT'] ?? '3306',
    'DB_DATABASE' => $testDatabase,
    'DB_USERNAME' => $sourceEnvironment['DB_USERNAME'] ?? '',
    'DB_PASSWORD' => $sourceEnvironment['DB_PASSWORD'] ?? '',
    'DATABASE_URL' => '',
    'HOLIDAYVIET_SOURCE_DATABASE' => $sourceEnvironment['DB_DATABASE'] ?? '',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'stderr',
];

if (is_file($testEnvironment['APP_CONFIG_CACHE'])) {
    throw new RuntimeException('The isolated test config-cache path must be absent.');
}

foreach ($testEnvironment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
