<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2);
$source = Dotenv\Dotenv::createArrayBacked($projectRoot)->safeLoad();
if (($source['DB_CONNECTION'] ?? 'mysql') !== 'mysql'
    || !in_array($source['DB_HOST'] ?? '127.0.0.1', ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Image checks require the configured local MySQL database in READ ONLY mode.');
}
$environment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $projectRoot . '/storage/framework/testing/image-check-config.php',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $source['DB_HOST'] ?? '127.0.0.1',
    'DB_PORT' => $source['DB_PORT'] ?? '3306', 'DB_DATABASE' => $source['DB_DATABASE'] ?? '',
    'DB_USERNAME' => $source['DB_USERNAME'] ?? '', 'DB_PASSWORD' => $source['DB_PASSWORD'] ?? '',
    'DATABASE_URL' => '', 'HOLIDAYVIET_IMAGE_DATABASE' => $source['DB_DATABASE'] ?? '',
    'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr',
];
if (is_file($environment['APP_CONFIG_CACHE'])) {
    throw new RuntimeException('The image-check config-cache path must be absent.');
}
foreach ($environment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
