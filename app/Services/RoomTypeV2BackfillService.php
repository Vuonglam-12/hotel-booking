<?php

namespace App\Services;

use App\Support\AccommodationImage;
use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Database\Connection;
use RuntimeException;

/** Preparation only: never remaps or updates Room, RoomImage, BookingRoom or legacy types. */
final class RoomTypeV2BackfillService
{
    public function run(string $connectionName, RoomTypeV2Plan $plan, bool $dryRun = false): array
    {
        $db = RoomTypeV2TestDatabase::connection($connectionName);
        $lock = 'aurel_rt_v2:' . $db->getDatabaseName();
        if ((int) $db->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('Another V2 preparation is running; no data was written.');
        }
        try {
            $this->checkSourceShape($db, $plan);
            $snapshots = [];
            foreach ($plan->groups as $key => $group) $snapshots[$key] = $this->snapshot($db, $plan, $group);
            $this->checkCheckpoints($db, $plan, $snapshots);
            $before = $this->protectedChecksums($db);
            $created = $skipped = 0;
            if (!$dryRun) {
                $hotels = collect($plan->groups)->groupBy('hotel_id', true);
                foreach ($hotels as $hotelId => $groups) {
                    [$newCount, $skipCount] = $db->transaction(function () use ($db, $plan, $snapshots, $hotelId, $groups) {
                        $db->table('hotel')->where('id', $hotelId)->lockForUpdate()->first();
                        $newCount = $skipCount = 0;
                        foreach ($groups as $key => $group) {
                            $source = $this->snapshot($db, $plan, $group, true);
                            if (RoomTypeV2Plan::hash($source) !== RoomTypeV2Plan::hash($snapshots[$key])) throw new RuntimeException('Source changed after preflight: ' . $key);
                            $map = $db->table('room_type_legacy_map')->where('hotel_id', $hotelId)->where('old_room_type_id', $group['legacy_room_type_id'])->first();
                            if ($map) {
                                $this->checkMap($db, $plan, $group, $source, $map);
                                $skipCount++;
                                continue;
                            }
                            $type = $source['type'];
                            $id = $db->table('room_type')->insertGetId([
                                'name' => $type['name'], 'capacity' => $type['capacity'], 'base_price' => $type['base_price'],
                                'bed_type' => $type['bed_type'], 'description' => $type['description'], 'created_at' => $type['created_at'],
                                'hotel_id' => (int) $hotelId, 'total_rooms' => count($source['rooms']), 'status' => 'active',
                            ]);
                            // This plan contains one existing distinct URL per pair. No file copy or cross-Hotel lookup.
                            $image = $source['images'][0];
                            $db->table('room_type_image')->insert(['room_type_id' => $id, 'image_url' => $image['image_url'],
                                'is_primary' => true, 'created_at' => $image['created_at']]);
                            $db->table('room_type_legacy_map')->insert([
                                'hotel_id' => (int) $hotelId, 'old_room_type_id' => (int) $group['legacy_room_type_id'], 'new_room_type_id' => $id,
                                'plan_checksum' => $plan->checksum, 'source_checksum' => RoomTypeV2Plan::hash($source),
                                'target_checksum' => RoomTypeV2Plan::hash($this->target($db, $id)),
                            ]);
                            $newCount++;
                        }
                        return [$newCount, $skipCount];
                    });
                    $created += $newCount;
                    $skipped += $skipCount;
                }
            }
            $after = $this->protectedChecksums($db);
            if ($before !== $after) throw new RuntimeException('Protected legacy/history data changed; stop and inspect.');
            $this->checkCheckpoints($db, $plan, $snapshots);
            return ['dry_run' => $dryRun, 'planned_groups' => 105, 'created_groups' => $created, 'skipped_groups' => $skipped,
                'checkpoints' => $db->table('room_type_legacy_map')->count(), 'legacy_types' => $db->table('room_type')->whereNull('hotel_id')->count(),
                'owned_types' => $db->table('room_type')->whereNotNull('hotel_id')->count(), 'total_rooms_v2' => (int) $db->table('room_type')->whereNotNull('hotel_id')->sum('total_rooms'),
                'type_images' => $db->table('room_type_image')->count(), 'protected_data_unchanged' => true, 'protected_checksums' => $after,
                'plan_checksum' => $plan->checksum];
        } finally { $db->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]); }
    }

    private function checkSourceShape(Connection $db, RoomTypeV2Plan $plan): void
    {
        foreach (['hotel', 'room_type', 'room', 'room_image', 'booking', 'booking_room', 'room_type_image', 'room_type_legacy_map'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) throw new RuntimeException('Required table missing: ' . $table);
        }
        if ($db->table('hotel')->count() !== 21 || $db->table('room')->count() !== 336 || $db->table('room_image')->count() !== 336) {
            throw new RuntimeException('Source counts differ from Phase 2A plan.');
        }
        $types = $db->table('room_type')->whereNull('hotel_id')->orderBy('id')->get()->all();
        if (array_map(fn($type) => $type->id, $types) !== [1, 2, 3, 4, 5, 6]) throw new RuntimeException('Legacy type IDs/ownership changed.');
        foreach ($types as $type) {
            foreach ($plan->types[$type->id] as $field => $expected) {
                if ($type->$field !== $expected) throw new RuntimeException('Legacy business field differs from prepared plan: Type ' . $type->id . '/' . $field);
            }
            if ($type->total_rooms !== null || $type->status !== null) throw new RuntimeException('Legacy V2 fields must remain null.');
        }
    }

    private function snapshot(Connection $db, RoomTypeV2Plan $plan, array $group, bool $lock = false): array
    {
        $hotel = $db->table('hotel')->where('id', $group['hotel_id'])->first();
        $typeQuery = $db->table('room_type')->where('id', $group['legacy_room_type_id']);
        $roomQuery = $db->table('room')->where('hotel_id', $group['hotel_id'])->where('room_type_id', $group['legacy_room_type_id'])->orderBy('id');
        $imageQuery = $db->table('room_image')->whereIn('room_id', $group['room_ids'])->orderBy('id');
        if ($lock) { $typeQuery->lockForUpdate(); $roomQuery->lockForUpdate(); $imageQuery->lockForUpdate(); }
        $type = $typeQuery->first();
        $rooms = $roomQuery->get()->map(fn($row) => (array) $row)->all();
        $images = $imageQuery->get()->map(fn($row) => (array) $row)->all();
        if (!$hotel || $hotel->name !== $group['hotel_name'] || $hotel->status !== $group['hotel_status'] || !$type
            || $type->hotel_id !== null || $type->name !== $group['room_type_name'] || $type->bed_type !== $group['bed_type']
            || $type->base_price !== $group['price_after'] || $type->capacity !== (int) $group['capacity_after']
            || array_column($rooms, 'id') !== $group['room_ids'] || array_column($images, 'id') !== $group['image_ids']) {
            throw new RuntimeException('Hotel/type/Room/image mapping differs from plan: ' . $group['planned_target_key']);
        }
        foreach ($rooms as $room) {
            if ($room['price'] !== $group['price_before_room'] || $room['capacity'] !== (int) $group['capacity_before'] || $room['status'] !== 'available') {
                throw new RuntimeException('Room price/capacity/status differs from plan: ' . $room['id']);
            }
        }
        $canonical = [];
        foreach ($images as $image) {
            if (!in_array($image['room_id'], $group['room_ids'], true) || $image['image_url'] !== $group['source_image_url']
                || !($url = AccommodationImage::localUrl($image['image_url']))) throw new RuntimeException('Invalid or mismapped existing image: ' . $image['id']);
            $canonical[$url] = true;
        }
        if (count($canonical) !== 1) throw new RuntimeException('Distinct gallery differs from prepared plan.');
        return ['hotel' => (array) $hotel, 'type' => (array) $type, 'rooms' => $rooms, 'images' => $images];
    }

    private function checkCheckpoints(Connection $db, RoomTypeV2Plan $plan, array $snapshots): void
    {
        $maps = $db->table('room_type_legacy_map')->orderBy('hotel_id')->orderBy('old_room_type_id')->get();
        $counts = [];
        foreach ($maps as $map) {
            $key = $map->hotel_id . ':' . $map->old_room_type_id;
            if (!isset($plan->groups[$key])) throw new RuntimeException('Unexpected checkpoint outside prepared plan.');
            $this->checkMap($db, $plan, $plan->groups[$key], $snapshots[$key], $map);
            $counts[$map->hotel_id] = ($counts[$map->hotel_id] ?? 0) + 1;
        }
        foreach ($counts as $count) if ($count !== 5) throw new RuntimeException('Incomplete Hotel checkpoint; inspect before resume.');
        if ($db->table('room_type')->whereNotNull('hotel_id')->count() !== $maps->count()
            || $db->table('room_type_image')->count() !== $maps->count()) throw new RuntimeException('Unmapped/orphan V2 target data exists.');
    }

    private function checkMap(Connection $db, RoomTypeV2Plan $plan, array $group, array $source, object $map): void
    {
        $target = $this->target($db, $map->new_room_type_id);
        if ($map->plan_checksum !== $plan->checksum || $map->source_checksum !== RoomTypeV2Plan::hash($source)
            || $map->target_checksum !== RoomTypeV2Plan::hash($target) || !$target['type']
            || $target['type']['hotel_id'] !== (int) $group['hotel_id'] || $target['type']['id'] === (int) $group['legacy_room_type_id']) {
            throw new RuntimeException('Plan/source/target checksum mismatch at checkpoint: ' . $group['planned_target_key']);
        }
    }

    private function target(Connection $db, int $typeId): array
    {
        $type = $db->table('room_type')->where('id', $typeId)->first();
        return ['type' => $type ? (array) $type : null,
            'images' => $db->table('room_type_image')->where('room_type_id', $typeId)->orderBy('id')->get()->map(fn($image) => (array) $image)->all()];
    }

    public function protectedChecksums(Connection $db): array
    {
        $hashes = [];
        foreach (['hotel', 'room', 'room_image', 'booking', 'booking_room'] as $table) {
            $hashes[$table] = RoomTypeV2Plan::hash($db->table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all());
        }
        $hashes['legacy_types'] = RoomTypeV2Plan::hash($db->table('room_type')->whereNull('hotel_id')->orderBy('id')->get()->map(fn($row) => (array) $row)->all());
        return $hashes;
    }
}
