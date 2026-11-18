<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Enable the isolated CLI pdo_sqlite extension for guide tests.');
$guideRuntime = sys_get_temp_dir().'/aurel-guide-tests-'.bin2hex(random_bytes(6));
mkdir($guideRuntime, 0777, true);
$guideEnvironment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('g', 32)),
    'APP_CONFIG_CACHE' => $guideRuntime.'/absent-config.php', 'APP_SERVICES_CACHE' => $guideRuntime.'/services.php',
    'APP_PACKAGES_CACHE' => $guideRuntime.'/packages.php', 'APP_ROUTES_CACHE' => $guideRuntime.'/absent-routes.php',
    'APP_EVENTS_CACHE' => $guideRuntime.'/absent-events.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'DATABASE_URL' => '', 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stderr', 'AUREL_GUIDE_TEST_RUNTIME' => $guideRuntime,
];
foreach ($guideEnvironment as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value; }
