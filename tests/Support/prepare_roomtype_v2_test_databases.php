<?php

// Standalone control plane for the newly initialized server; never loads .env or Laravel.
if (count($argv) !== 2) throw new RuntimeException('Pass the owned server.json path.');
$workspace = realpath(dirname(__DIR__, 2));
$descriptor = realpath($argv[1]);
$allowed = $workspace . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'roomtype-v2-tests' . DIRECTORY_SEPARATOR;
if (!$descriptor || !str_starts_with($descriptor, $allowed)) throw new RuntimeException('Unsafe descriptor.');
$server = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($descriptor)), true, 512, JSON_THROW_ON_ERROR);
if (($server['host'] ?? '') !== '127.0.0.1' || (int) $server['port'] === 3306
    || !($server['initialized_here'] ?? false) || !preg_match('/^[a-f0-9]{12}$/D', $server['run_id'] ?? '')
    || realpath($server['runtime']) !== dirname($descriptor)) throw new RuntimeException('Not an owned isolated server.');
$dsn = 'mysql:host=127.0.0.1;port=' . (int) $server['port'] . ';charset=utf8mb4';
$control = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($server['runtime'] . '/control-bootstrap.json')), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO($dsn, $control['username'], $control['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
$identity = $pdo->query('SELECT @@datadir AS directory, @@port AS port, @@server_uuid AS uuid')->fetch(PDO::FETCH_ASSOC);
if (realpath($identity['directory']) !== realpath($server['data']) || (int) $identity['port'] !== (int) $server['port']) throw new RuntimeException('Server identity mismatch.');
$user = 'aurel_v2_' . $server['run_id'];
$password = bin2hex(random_bytes(24));
$rootPassword = bin2hex(random_bytes(24));
$schemas = [];
$pdo->exec('ALTER USER ' . $pdo->quote('root') . '@' . $pdo->quote('localhost') . ' IDENTIFIED BY ' . $pdo->quote($rootPassword));
$pdo->exec('CREATE USER ' . $pdo->quote($user) . '@' . $pdo->quote('127.0.0.1') . ' IDENTIFIED BY ' . $pdo->quote($password));
for ($index = 0; $index < 48; $index++) {
    $schema = 'aurel_roomtype_v2_test_' . bin2hex(random_bytes(6));
    $pdo->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('GRANT ALL PRIVILEGES ON `' . $schema . '`.* TO ' . $pdo->quote($user) . '@' . $pdo->quote('127.0.0.1'));
    $schemas[] = $schema;
}
$test = new PDO($dsn . ';dbname=' . $schemas[0], $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$grants = $test->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
foreach ($grants as $grant) {
    if (stripos($grant, 'hotel_booking') !== false || (str_contains($grant, ' ON *.* ') && !str_starts_with($grant, 'GRANT USAGE '))) {
        throw new RuntimeException('Test account must have no application/global write privileges.');
    }
}
$settings = ['run_id' => $server['run_id'], 'host' => '127.0.0.1', 'port' => (int) $server['port'],
    'database' => $schemas[0], 'schemas' => $schemas, 'username' => $user, 'password' => $password,
    'server_uuid' => $identity['uuid'], 'app_key' => base64_encode(random_bytes(32))];
file_put_contents($server['runtime'] . '/test-environment.json', json_encode($settings, JSON_PRETTY_PRINT));
file_put_contents($server['runtime'] . '/control-private.json', json_encode(['root_password' => $rootPassword]));
echo json_encode(['isolated_port' => $server['port'], 'schema_count' => count($schemas),
    'test_global_write_privileges' => false, 'test_primary_database_privileges' => false,
    'settings_file' => $server['runtime'] . '/test-environment.json']);
