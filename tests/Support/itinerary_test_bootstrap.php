<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

$settings = Tests\Support\ItineraryFixture::settings();
$runtime = $settings['runtime'].'/itinerary-phpunit';
if (! is_dir($runtime)) {
    mkdir($runtime, 0777, true);
}
$values = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://localhost', 'APP_KEY' => $settings['app_key'],
    'APP_CONFIG_CACHE' => $runtime.'/absent-config.php', 'APP_SERVICES_CACHE' => $runtime.'/services.php',
    'APP_PACKAGES_CACHE' => $runtime.'/packages.php', 'APP_ROUTES_CACHE' => $runtime.'/absent-routes.php',
    'APP_EVENTS_CACHE' => $runtime.'/absent-events.php', 'DB_CONNECTION' => 'mysql', 'DATABASE_URL' => '', 'DB_URL' => '',
    'SESSION_DRIVER' => 'array', 'CACHE_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'stderr',
];
foreach ($values as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
