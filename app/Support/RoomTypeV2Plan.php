<?php

namespace App\Support;

use RuntimeException;

final class RoomTypeV2Plan
{
    public readonly array $groups;
    public readonly array $types;
    public readonly string $checksum;

    public function __construct(string $csvPath, string $typesPath)
    {
        $handle = fopen($csvPath, 'rb');
        if (!$handle) throw new RuntimeException('Cannot read Phase 2A mapping.');
        try {
            $headers = fgetcsv($handle);
            $groups = [];
            $rooms = $images = [];
            $unassigned = [];
            while (($values = fgetcsv($handle)) !== false) {
                if (count($values) !== count($headers)) throw new RuntimeException('Malformed mapping row.');
                $row = array_combine($headers, $values);
                if (($row['new_room_type_id_NOT_ASSIGNED'] ?? null) !== '') throw new RuntimeException('Plan must not guess new IDs.');
                if ($row['mapping_state'] === 'MANUAL_DECISION_NO_HOTEL') {
                    $unassigned[] = (int) $row['legacy_room_type_id'];
                    continue;
                }
                if ($row['mapping_state'] !== 'PLANNED_NOT_APPLIED') throw new RuntimeException('Unknown mapping state.');
                foreach (['hotel_id', 'legacy_room_type_id', 'total_rooms_after', 'capacity_before', 'capacity_after'] as $field) {
                    if (!ctype_digit($row[$field]) || (int) $row[$field] < 1) throw new RuntimeException('Invalid plan integer: ' . $field);
                }
                foreach (['price_before_room', 'price_before_room_type', 'price_after'] as $field) {
                    if (!preg_match('/^\d{1,10}\.\d{2}$/D', $row[$field])) throw new RuntimeException('Plan price must be an exact DECIMAL string.');
                }
                if ($row['price_after'] !== $row['price_before_room'] || $row['price_after'] !== $row['price_before_room_type']
                    || $row['capacity_before'] !== $row['capacity_after'] || $row['planned_room_type_status'] !== 'active'
                    || $row['distinct_images_after'] !== '1') throw new RuntimeException('Plan changes existing business data.');
                $row['room_ids'] = self::ids($row['source_room_ids']);
                $row['image_ids'] = self::ids($row['source_room_image_ids']);
                if (count($row['room_ids']) !== (int) $row['total_rooms_after']) throw new RuntimeException('Plan room count mismatch.');
                $key = $row['hotel_id'] . ':' . $row['legacy_room_type_id'];
                if (isset($groups[$key])) throw new RuntimeException('Duplicate plan group.');
                $groups[$key] = $row;
                array_push($rooms, ...$row['room_ids']);
                array_push($images, ...$row['image_ids']);
            }
        } finally { fclose($handle); }
        if (count($groups) !== 105 || count(array_unique(array_column($groups, 'hotel_id'))) !== 21
            || count($rooms) !== 336 || count(array_unique($rooms)) !== 336
            || count($images) !== 336 || count(array_unique($images)) !== 336 || $unassigned !== [6]) {
            throw new RuntimeException('Plan must retain 21 Hotels, 105 groups, 336 Rooms/images and Presidential legacy.');
        }
        $typeData = json_decode(file_get_contents($typesPath), true, 512, JSON_THROW_ON_ERROR);
        $types = array_column($typeData['legacy_types'], null, 'id');
        if (array_keys($types) !== [1, 2, 3, 4, 5, 6]) throw new RuntimeException('Legacy type plan must retain IDs 1–6.');
        uasort($groups, fn($a, $b) => [(int) $a['hotel_id'], (int) $a['legacy_room_type_id']] <=> [(int) $b['hotel_id'], (int) $b['legacy_room_type_id']]);
        $this->groups = $groups;
        $this->types = $types;
        $this->checksum = self::hash(['groups' => $groups, 'types' => $types]);
    }

    public static function prepared(): self
    {
        return new self(base_path('docs/hotel-roomtype-phase2a-mapping.csv'), base_path('docs/hotel-roomtype-phase2b1-source-types.json'));
    }

    public static function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function ids(string $value): array
    {
        $parts = explode('|', $value);
        foreach ($parts as $part) if (!ctype_digit($part) || (int) $part < 1) throw new RuntimeException('Invalid source ID in plan.');
        $ids = array_map('intval', $parts);
        sort($ids, SORT_NUMERIC);
        if (count($ids) !== count(array_unique($ids))) throw new RuntimeException('Duplicate ID in group.');
        return $ids;
    }
}
