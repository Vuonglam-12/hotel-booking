<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Explicit local demo deployment; never enables a real payment gateway or the production V2 cutover. */
final class LocalBookingDemo
{
    public static function enabled(): bool
    {
        if (config('booking-demo.local_enabled') !== true || !app()->environment(['local', 'testing'])) return false;
        if (app()->environment('testing')) return true;
        $file = config('booking-demo.local_manifest');
        $root = realpath(storage_path('app/private/booking-demo-live'));
        if (!$root || !is_string($file) || !is_file($file) || dirname(realpath($file)) !== $root) return false;
        $hash = config('booking-demo.local_manifest_sha256');
        if (!is_string($hash) || !hash_equals($hash, hash_file('sha256', $file))) return false;
        $m = json_decode(file_get_contents($file), true);
        return ($m['approved'] ?? false) === true && ($m['mode'] ?? '') === 'LOCAL_DEMO_NO_REAL_PAYMENTS'
            && ($m['schema_verified'] ?? false) === true && preg_match('/^[a-f0-9]{64}$/D', $m['restore_backup_sha256'] ?? '') === 1;
    }

    public static function connection(): Connection
    {
        abort_unless(self::enabled(), 404);
        if (app()->environment('testing')) return RoomTypeV2TestDatabase::connection('mysql');
        $m = json_decode(file_get_contents(config('booking-demo.local_manifest')), true, 512, JSON_THROW_ON_ERROR);
        $c = config('database.connections.mysql');
        if (config('database.default') !== 'mysql' || ($c['driver'] ?? '') !== 'mysql'
            || !in_array($c['host'] ?? '', ['127.0.0.1', 'localhost'], true)
            || (int)($c['port'] ?? 0) !== 3306 || ($c['database'] ?? '') !== 'hotel_booking'
            || !empty($c['url']) || !empty($c['unix_socket']) || !empty($c['read']) || !empty($c['write'])) {
            throw new \RuntimeException('Local demo database target rejected.');
        }
        $db = DB::connection('mysql');
        $actual = $db->selectOne('SELECT DATABASE() db, @@port port, @@server_uuid uuid');
        if ($actual->db !== 'hotel_booking' || (int)$actual->port !== 3306 || $actual->uuid !== ($m['server_uuid'] ?? null)) {
            throw new \RuntimeException('Local demo server identity differs from the approved target.');
        }
        if (!$db->getSchemaBuilder()->hasTable('booking_demo') || !$db->getSchemaBuilder()->hasTable('booking_demo_event')
            || !$db->getSchemaBuilder()->hasColumn('booking', 'active_duplicate_key')) {
            throw new \RuntimeException('Local demo migrations are not ready.');
        }
        return $db;
    }

    public static function assertRequest(\Illuminate\Http\Request $r): void
    {
        abort_unless(in_array(strtolower($r->getHost()), ['localhost','127.0.0.1','::1'],true)
            && in_array($r->ip(),['127.0.0.1','::1'],true),404);
    }

    public static function isDemo(int $booking): bool
    {
        return self::enabled() && self::connection()->table('booking_demo')->where('booking_id', $booking)->exists();
    }
}
