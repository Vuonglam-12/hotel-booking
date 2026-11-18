<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Phase 2B.1 is deliberately unavailable outside the isolated test environment. */
final class RoomTypeV2TestDatabase
{
    public static function validate(array $config, bool $testing): void
    {
        if (!$testing || ($config['driver'] ?? '') !== 'mysql'
            || ($config['host'] ?? '') !== '127.0.0.1'
            || !(is_int($config['port'] ?? null) || (is_string($config['port'] ?? null) && ctype_digit($config['port'])))
            || (int) $config['port'] < 1024 || (int) $config['port'] > 65535 || (int) $config['port'] === 3306
            || !preg_match('/^aurel_roomtype_v2_test_[a-f0-9]{12}$/D', $config['database'] ?? '')
            || !preg_match('/^aurel_v2_[a-f0-9]{12}$/D', $config['username'] ?? '')
            || strlen($config['password'] ?? '') < 32
            || !preg_match('/^[a-f0-9-]{36}$/D', $config['server_uuid'] ?? '')
            || !empty($config['url']) || !empty($config['unix_socket']) || !empty($config['read']) || !empty($config['write'])) {
            throw new RuntimeException('RoomType V2 preparation requires the isolated test server; refused before opening a connection.');
        }
        if (!in_array($config['database'], $config['schemas'] ?? [], true)) {
            throw new RuntimeException('Database is not in the owned test-schema allowlist.');
        }
    }

    public static function settings(): array
    {
        $path = getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS');
        $resolved = is_string($path) ? realpath($path) : false;
        $allowed = realpath(dirname(__DIR__, 2) . '/storage/app/private/roomtype-v2-tests');
        if (!$resolved || !$allowed || !str_starts_with($resolved, $allowed . DIRECTORY_SEPARATOR)
            || basename($resolved) !== 'test-environment.json') {
            throw new RuntimeException('Missing owned isolated test settings.');
        }
        return json_decode(file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function connection(string $name): Connection
    {
        // Config validation happens before DB::connection(), including failures in PHPUnit/CLI.
        $settings = self::settings();
        $config = config('database.connections.' . $name, []);
        self::validate(array_merge($settings, $config), app()->environment('testing'));
        if (($config['host'] ?? '') !== $settings['host'] || (int) ($config['port'] ?? 0) !== (int) $settings['port']
            || ($config['username'] ?? '') !== $settings['username'] || ($config['password'] ?? '') !== $settings['password']) {
            throw new RuntimeException('Connection config differs from owned test settings; refused before connection.');
        }
        $connection = DB::connection($name);
        $identity = $connection->selectOne('SELECT DATABASE() AS db, @@port AS port, @@server_uuid AS uuid');
        if ($identity->db !== $config['database'] || (int) $identity->port !== (int) $settings['port'] || $identity->uuid !== $settings['server_uuid']) {
            throw new RuntimeException('Connected test-server identity does not match the owned server.');
        }
        foreach ($connection->select('SHOW GRANTS') as $row) {
            $grant = array_values((array) $row)[0];
            if (stripos($grant, 'hotel_booking') !== false || (str_contains($grant, ' ON *.* ') && !str_starts_with($grant, 'GRANT USAGE '))) {
                throw new RuntimeException('Test account has forbidden database/global privileges.');
            }
        }
        return $connection;
    }
}
