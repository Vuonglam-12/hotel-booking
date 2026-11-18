<?php

// Standalone provisioning on a freshly initialized, owned server. Never reads .env.
$root = realpath(dirname(__DIR__, 2));
$descriptor = isset($argv[1]) ? realpath($argv[1]) : false;
$allowed = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'roomtype-v2-tests'.DIRECTORY_SEPARATOR;
if (! $descriptor || ! str_starts_with($descriptor, $allowed) || basename($descriptor) !== 'server.json') {
    throw new RuntimeException('Pass an owned isolated server descriptor.');
}
$server = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($descriptor)), true, 512, JSON_THROW_ON_ERROR);
if (($server['host'] ?? '') !== '127.0.0.1' || ($server['port'] ?? 3306) === 3306 || ! ($server['initialized_here'] ?? false)
    || ! preg_match('/^[a-f0-9]{12}$/D', $server['run_id'] ?? '') || realpath($server['runtime']) !== dirname($descriptor)
    || realpath($server['data']) !== realpath($server['runtime'].'/data')) {
    throw new RuntimeException('Not an owned isolated server.');
}
$control = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($server['runtime'].'/control-bootstrap.json')), true, 512, JSON_THROW_ON_ERROR);
$dsn = 'mysql:host=127.0.0.1;port='.(int) $server['port'].';charset=utf8mb4';
$pdo = new PDO($dsn, $control['username'], $control['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
$identity = $pdo->query('SELECT @@datadir AS directory, @@port AS port, @@server_uuid AS uuid')->fetch(PDO::FETCH_ASSOC);
if (realpath($identity['directory']) !== realpath($server['data']) || (int) $identity['port'] !== (int) $server['port']) {
    throw new RuntimeException('Server identity mismatch.');
}
$database = 'aurel_itinerary_'.$server['run_id'];
$user = 'aurel_itin_'.$server['run_id'];
$password = bin2hex(random_bytes(24));
$owner = bin2hex(random_bytes(32));
$pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('CREATE USER '.$pdo->quote($user).'@'.$pdo->quote('127.0.0.1').' IDENTIFIED BY '.$pdo->quote($password));
$pdo->exec('GRANT ALL PRIVILEGES ON `'.$database.'`.* TO '.$pdo->quote($user).'@'.$pdo->quote('127.0.0.1'));
$test = new PDO($dsn.';dbname='.$database, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$test->exec('CREATE TABLE _itinerary_test_owner (owner_token CHAR(64) NOT NULL PRIMARY KEY)');
$statement = $test->prepare('INSERT INTO _itinerary_test_owner VALUES (?)');
$statement->execute([$owner]);
$settings = [...$server, 'database' => $database, 'username' => $user, 'password' => $password, 'server_uuid' => $identity['uuid'],
    'owner_token' => $owner, 'app_key' => 'base64:'.base64_encode(random_bytes(32))];
$path = $server['runtime'].'/itinerary-test-environment.json';
file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo $path.PHP_EOL;
