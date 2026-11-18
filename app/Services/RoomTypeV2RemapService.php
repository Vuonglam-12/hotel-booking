<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Database\Connection;
use RuntimeException;

/** Offline test preparation only. Immutable manifest + per-Hotel DML transactions; no schema changes. */
final class RoomTypeV2RemapService
{
    public function prepare(string $connection, RoomTypeV2Plan $plan, string $path): array
    {
        $db = RoomTypeV2TestDatabase::connection($connection);
        $path = $this->path($path);
        if (file_exists($path)) throw new RuntimeException('Never overwrite a remap manifest; resume from the original.');
        return $this->exclusive($db, function () use ($db, $plan, $path) {
            app(RoomTypeV2BackfillService::class)->run($db->getName(), $plan, true);
            if ($db->table('room_type_legacy_map')->count() !== 105) throw new RuntimeException('Complete all 105 verified backfill groups before remap.');
            $snapshot = $this->snapshot($db);
            $maps = [];
            foreach ($snapshot['room_type_legacy_map'] as $map) $maps[$map['hotel_id'] . ':' . $map['old_room_type_id']] = $map;
            $rooms = array_column($snapshot['room'], null, 'id');
            $bookings = array_column($snapshot['booking'], null, 'id');
            $lines = [];
            $assigned = [];
            foreach ($snapshot['booking_room'] as $line) {
                $booking = $bookings[$line['booking_id']] ?? throw new RuntimeException('Dangling BookingRoom history.');
                $key = $booking['hotel_id'] . ':' . $line['room_type_id'];
                if (!isset($maps[$key]) || $line['quantity'] < 1
                    || $line['nights'] !== RoomTypeV2Inventory::dates($booking['check_in'], $booking['check_out'])) throw new RuntimeException('Unverifiable legacy BookingRoom mapping/quantity/nights.');
                RoomTypeV2Inventory::holding($booking['status']);
                RoomTypeV2Inventory::cents($line['price_at_booking']);
                if ($line['room_id'] !== null) {
                    $room = $rooms[$line['room_id']] ?? throw new RuntimeException('Dangling historical room_id.');
                    if ($line['quantity'] !== 1 || $room['hotel_id'] !== $booking['hotel_id'] || $room['room_type_id'] !== $line['room_type_id']
                        || isset($assigned[$booking['id'] . ':' . $room['id']])) throw new RuntimeException('Ambiguous assigned-room history; manual decision required.');
                    $assigned[$booking['id'] . ':' . $room['id']] = true;
                    if (RoomTypeV2Inventory::holding($booking['status'])) {
                        foreach ($lines as $other) {
                            if ($other['room_id'] === $line['room_id']) {
                                $b = $bookings[$other['booking_id']];
                                if (RoomTypeV2Inventory::holding($b['status']) && $booking['check_in'] < $b['check_out'] && $booking['check_out'] > $b['check_in']) {
                                    throw new RuntimeException('Overlapping assigned-room history; manual decision required.');
                                }
                            }
                        }
                    }
                }
                $lines[] = $line;
            }
            // Verify every legacy quantity peak, including unassigned history, before writing a manifest.
            foreach ($maps as $map) {
                $type = $db->table('room_type')->where('id', $map['new_room_type_id'])->first();
                $events = [];
                foreach ($lines as $line) {
                    $b = $bookings[$line['booking_id']];
                    if ($b['hotel_id'] !== $map['hotel_id'] || $line['room_type_id'] !== $map['old_room_type_id'] || !RoomTypeV2Inventory::holding($b['status'])) continue;
                    $events[$b['check_in']] = ($events[$b['check_in']] ?? 0) + $line['quantity'];
                    $events[$b['check_out']] = ($events[$b['check_out']] ?? 0) - $line['quantity'];
                }
                ksort($events); $used = 0;
                foreach ($events as $delta) { $used += $delta; if ($used > $type->total_rooms) throw new RuntimeException('Legacy history already exceeds V2 inventory.'); }
            }
            $manifest = ['version' => 1, 'database' => $db->getDatabaseName(), 'server_uuid' => RoomTypeV2TestDatabase::settings()['server_uuid'],
                'plan_checksum' => $plan->checksum, 'snapshot' => $snapshot];
            $manifest['checksum'] = RoomTypeV2Plan::hash($manifest);
            $handle = fopen($path, 'xb');
            if (!$handle) throw new RuntimeException('Cannot create immutable remap manifest.');
            try { $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); if (fwrite($handle, $json) !== strlen($json)) throw new RuntimeException('Incomplete manifest write; do not remap.'); }
            finally { fclose($handle); }
            return ['manifest' => $path, 'rooms' => count($snapshot['room']), 'booking_rooms' => count($lines), 'checksum' => $manifest['checksum']];
        });
    }

    public function run(string $connection, string $path, bool $dryRun = false): array
    {
        $db = RoomTypeV2TestDatabase::connection($connection);
        $manifest = json_decode(file_get_contents($this->path($path)), true, 512, JSON_THROW_ON_ERROR);
        $hash = $manifest['checksum'] ?? ''; unset($manifest['checksum']);
        if ($hash !== RoomTypeV2Plan::hash($manifest) || ($manifest['version'] ?? null) !== 1 || $manifest['database'] !== $db->getDatabaseName()
            || $manifest['server_uuid'] !== RoomTypeV2TestDatabase::settings()['server_uuid'] || $manifest['plan_checksum'] !== RoomTypeV2Plan::prepared()->checksum) throw new RuntimeException('Manifest identity/plan/checksum mismatch.');
        return $this->exclusive($db, function () use ($db, $manifest, $dryRun) {
            $source = $manifest['snapshot'];
            $maps = [];
            foreach ($source['room_type_legacy_map'] as $map) $maps[$map['hotel_id'] . ':' . $map['old_room_type_id']] = $map;
            $this->verify($db, $source, $maps);
            $changedRooms = $changedLines = 0;
            $bookings = array_column($source['booking'], null, 'id');
            if (!$dryRun) {
                foreach ($source['hotel'] as $hotel) {
                    [$rooms, $lines] = $db->transaction(function () use ($db, $source, $maps, $bookings, $hotel) {
                        $db->table('hotel')->where('id', $hotel['id'])->lockForUpdate()->first();
                        $ids = [];
                        foreach ($maps as $map) if ($map['hotel_id'] === $hotel['id']) array_push($ids, $map['old_room_type_id'], $map['new_room_type_id']);
                        $db->table('room_type')->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
                        $db->table('booking')->where('hotel_id', $hotel['id'])->orderBy('id')->lockForUpdate()->get();
                        // Tool requires an offline window. Verify all preserved rows before each checkpoint.
                        $this->verify($db, $source, $maps);
                        $rooms = $lines = 0;
                        foreach ($source['room'] as $row) {
                            if ($row['hotel_id'] !== $hotel['id']) continue;
                            $map = $maps[$hotel['id'] . ':' . $row['room_type_id']];
                            $rooms += $db->table('room')->where('id', $row['id'])->where('room_type_id', $map['old_room_type_id'])->update(['room_type_id' => $map['new_room_type_id']]);
                        }
                        foreach ($source['booking_room'] as $row) {
                            if ($bookings[$row['booking_id']]['hotel_id'] !== $hotel['id']) continue;
                            $map = $maps[$hotel['id'] . ':' . $row['room_type_id']];
                            $lines += $db->table('booking_room')->where('id', $row['id'])->where('room_type_id', $map['old_room_type_id'])->update(['room_type_id' => $map['new_room_type_id']]);
                        }
                        return [$rooms, $lines];
                    });
                    $changedRooms += $rooms; $changedLines += $lines;
                }
            }
            $this->verify($db, $source, $maps);
            return ['dry_run' => $dryRun, 'remapped_rooms' => $changedRooms, 'remapped_booking_rooms' => $changedLines,
                'source_rooms' => count($source['room']), 'source_booking_rooms' => count($source['booking_room']), 'preserved_checksum' => RoomTypeV2Plan::hash($source)];
        });
    }

    private function verify(Connection $db, array $source, array $maps): void
    {
        $current = $this->snapshot($db);
        $rooms = array_column($source['room'], null, 'id');
        $lines = array_column($source['booking_room'], null, 'id');
        $bookings = array_column($source['booking'], null, 'id');
        foreach ($current['room'] as &$row) {
            $old = $rooms[$row['id']] ?? throw new RuntimeException('Room added after manifest.');
            $map = $maps[$old['hotel_id'] . ':' . $old['room_type_id']] ?? throw new RuntimeException('Missing source map.');
            if (!in_array($row['room_type_id'], [$map['old_room_type_id'], $map['new_room_type_id']], true)) throw new RuntimeException('Unexpected remapped Room type.');
            $row['room_type_id'] = $old['room_type_id'];
        } unset($row);
        foreach ($current['booking_room'] as &$row) {
            $old = $lines[$row['id']] ?? throw new RuntimeException('BookingRoom added after manifest.');
            $b = $bookings[$old['booking_id']];
            $map = $maps[$b['hotel_id'] . ':' . $old['room_type_id']];
            if (!in_array($row['room_type_id'], [$map['old_room_type_id'], $map['new_room_type_id']], true)) throw new RuntimeException('Unexpected remapped BookingRoom type.');
            $row['room_type_id'] = $old['room_type_id'];
        } unset($row);
        if (RoomTypeV2Plan::hash($current) !== RoomTypeV2Plan::hash($source)) throw new RuntimeException('Source/target/history checksum drift; no further remap permitted.');
    }

    private function snapshot(Connection $db): array
    {
        $result = [];
        foreach (['hotel', 'room_type', 'room', 'room_image', 'booking', 'booking_room', 'room_type_image', 'room_type_legacy_map', 'hotel_image', 'payment', 'invoice', 'invoice_item', 'review', 'room_availability'] as $table) {
            if ($db->getSchemaBuilder()->hasTable($table)) $result[$table] = $db->table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all();
        }
        if (!empty($result['room_availability'])) throw new RuntimeException('Legacy availability ledger is nonempty; a separate audit is required.');
        return $result;
    }

    private function path(string $path): string
    {
        $settingsPath = realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'));
        $dir = realpath(dirname($path));
        if (!$settingsPath || $dir !== dirname($settingsPath) || !preg_match('/^remap-[a-f0-9]{12}\.json$/D', basename($path))) throw new RuntimeException('Manifest must live in this owned private test runtime.');
        return $dir . DIRECTORY_SEPARATOR . basename($path);
    }

    private function exclusive(Connection $db, callable $callback): mixed
    {
        $key = 'aurel_rt_v2:' . $db->getDatabaseName();
        if ((int) $db->selectOne('SELECT GET_LOCK(?,0) AS acquired', [$key])->acquired !== 1) throw new RuntimeException('Another preparation/remap is running.');
        try { return $callback(); }
        finally { $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$key]); }
    }
}
