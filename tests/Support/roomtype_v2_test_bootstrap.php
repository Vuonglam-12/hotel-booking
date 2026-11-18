<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$settings = App\Support\RoomTypeV2TestDatabase::settings();
$config = array_merge($settings, ['driver' => 'mysql']);
if (getenv('AUREL_ROOMTYPE_V2_CASE_DATABASE') !== false) $config['database'] = getenv('AUREL_ROOMTYPE_V2_CASE_DATABASE');
// Validate before Laravel bootstrap or any PDO call; no application .env/schema is read here.
App\Support\RoomTypeV2TestDatabase::validate($config, true);
$cachePath = $root . '/storage/framework/testing/roomtype-v2-' . $settings['run_id'] . '-config.php';
if (is_file($cachePath)) throw new RuntimeException('Isolated config cache must be absent.');
$environment = [
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:' . $settings['app_key'], 'APP_CONFIG_CACHE' => $cachePath,
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $settings['host'], 'DB_PORT' => (string) $settings['port'],
    'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $settings['username'], 'DB_PASSWORD' => $settings['password'],
    'APP_URL' => 'http://roomtype-v2-test.invalid',
    'DATABASE_URL' => '', 'DB_URL' => '', 'DB_SOCKET' => '', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr',
];
foreach ($environment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
