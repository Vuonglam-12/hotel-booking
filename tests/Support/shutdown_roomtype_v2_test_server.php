<?php

// Graceful shutdown of the owned test server, without loading Laravel or application .env.
if (count($argv) !== 2) throw new RuntimeException('Pass the owned server descriptor.');
$workspace = realpath(dirname(__DIR__, 2));
$path = realpath($argv[1]);
$prefix = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR;
if (!$path || !str_starts_with($path, $prefix) || basename($path) !== 'server.json') throw new RuntimeException('Unsafe descriptor.');
$server = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
if (!$server['initialized_here'] || $server['host'] !== '127.0.0.1' || (int) $server['port'] === 3306
    || realpath($server['runtime']) !== dirname($path)) throw new RuntimeException('Refusing unowned/application server.');
$control = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($server['runtime'] . '/control-bootstrap.json')), true, 512, JSON_THROW_ON_ERROR);
if ($control['username'] !== 'aurel_v2_control_' . $server['run_id']) throw new RuntimeException('Control account mismatch.');
$pdo = new PDO('mysql:host=127.0.0.1;port=' . (int) $server['port'], $control['username'], $control['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT @@datadir AS directory, @@port AS port')->fetch(PDO::FETCH_ASSOC);
if (realpath($identity['directory']) !== realpath($server['data']) || (int) $identity['port'] !== (int) $server['port']) throw new RuntimeException('Server identity mismatch.');
$pdo->exec('SHUTDOWN');
echo 'Graceful shutdown requested for owned test server.' . PHP_EOL;
