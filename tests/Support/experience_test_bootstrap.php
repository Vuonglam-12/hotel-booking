<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Run the experience checks with the isolated CLI pdo_sqlite extension enabled.');
$experienceRuntime = sys_get_temp_dir().'/aurel-experience-tests-'.bin2hex(random_bytes(6));
mkdir($experienceRuntime, 0777, true);
$experienceEnvironment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('e', 32)),
    'APP_CONFIG_CACHE' => $experienceRuntime.'/absent-config.php',
    'APP_SERVICES_CACHE' => $experienceRuntime.'/services.php', 'APP_PACKAGES_CACHE' => $experienceRuntime.'/packages.php',
    'APP_ROUTES_CACHE' => $experienceRuntime.'/absent-routes.php', 'APP_EVENTS_CACHE' => $experienceRuntime.'/absent-events.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DATABASE_URL' => '', 'DB_URL' => '',
    'SESSION_DRIVER' => 'array', 'CACHE_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stderr', 'AUREL_EXPERIENCE_TEST_RUNTIME' => $experienceRuntime,
];
foreach ($experienceEnvironment as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value; }
