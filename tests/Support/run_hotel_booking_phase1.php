<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$arguments = [];
for ($index = 1; $index < count($argv); $index++) {
    if ($argv[$index] === '--filter' && isset($argv[$index + 1])) {
        $arguments[] = '--filter';
        $arguments[] = $argv[++$index];
    } elseif (in_array($argv[$index], ['--stop-on-error', '--stop-on-failure'], true)) {
        $arguments[] = $argv[$index];
    } else {
        throw new InvalidArgumentException('Only test filters and stop flags are accepted; the safe bootstrap/config cannot be overridden.');
    }
}
$source = Dotenv\Dotenv::createArrayBacked($root)->safeLoad();
if (($source['DB_CONNECTION'] ?? 'mysql') !== 'mysql'
    || !in_array($source['DB_HOST'] ?? '127.0.0.1', ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Phase 1 tests require a local MySQL server.');
}
$sourceName = $source['DB_DATABASE'] ?? '';
$testName = 'holidayviet_booking_test_' . bin2hex(random_bytes(6));
if (!$sourceName || $testName === $sourceName || !preg_match('/^holidayviet_booking_test_[a-f0-9]{12}$/D', $testName)) {
    throw new RuntimeException('Unsafe test database name.');
}
$dsn = 'mysql:host=' . ($source['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($source['DB_PORT'] ?? '3306') . ';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
$read = new PDO($dsn . ';dbname=' . $sourceName, $source['DB_USERNAME'] ?? '', $source['DB_PASSWORD'] ?? '', $options);
$read->exec('SET SESSION TRANSACTION READ ONLY');
$read->beginTransaction();
$schemas = [];
try {
    if ($read->query('SELECT DATABASE()')->fetchColumn() !== $sourceName) throw new RuntimeException('Wrong source database.');
    foreach ($read->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('Unexpected table name.');
        $schema = $read->query('SHOW CREATE TABLE ' . chr(96) . $table . chr(96))->fetch(PDO::FETCH_NUM)[1];
        if (!str_starts_with($schema, 'CREATE TABLE ' . chr(96) . $table . chr(96))
            || str_contains($schema, chr(96) . $sourceName . chr(96) . '.')) throw new RuntimeException('Schema has an external reference.');
        $schemas[] = $schema;
    }
} finally {
    $read->rollBack();
}
// This separate PDO connection performs every DDL write only against the new disposable database.
$test = new PDO($dsn, $source['DB_USERNAME'] ?? '', $source['DB_PASSWORD'] ?? '', $options);
$exists = $test->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
$exists->execute([$testName]);
if ($exists->fetchColumn()) throw new RuntimeException('Test database must not exist.');
$created = false;
try {
    $test->exec('CREATE DATABASE ' . chr(96) . $testName . chr(96) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $test->exec('USE ' . chr(96) . $testName . chr(96));
    if ($test->query('SELECT DATABASE()')->fetchColumn() !== $testName) throw new RuntimeException('Wrong write connection.');
    $test->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($schemas as $schema) $test->exec($schema);
    $test->exec('SET FOREIGN_KEY_CHECKS=1');
    echo 'Disposable schema-only database: ' . $testName . PHP_EOL;
    $process = new Process(array_merge([PHP_BINARY, $root . '/vendor/bin/phpunit', '-c', $root . '/phpunit.hotel-booking-phase1.xml', '--no-progress'], $arguments), $root, ['HOLIDAYVIET_TEST_DATABASE' => $testName]);
    $process->setTimeout(240);
    $exitCode = $process->run(function ($type, $output) { echo $output; });
} finally {
    if ($created) {
        if ($testName === $sourceName || !preg_match('/^holidayviet_booking_test_[a-f0-9]{12}$/D', $testName)
            || $test->query('SELECT DATABASE()')->fetchColumn() !== $testName) throw new RuntimeException('Unsafe test cleanup.');
        $test->exec('DROP DATABASE ' . chr(96) . $testName . chr(96));
        echo 'Disposable test database removed.' . PHP_EOL;
    }
}
exit($exitCode ?? 1);
