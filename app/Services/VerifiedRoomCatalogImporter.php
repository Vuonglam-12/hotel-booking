<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

/** Offline, additive research import. Never writes physical inventory, rates, hotels, or bookings. */
final class VerifiedRoomCatalogImporter
{
    public function __construct(private Connection $db) {}

    public function run(array $manifest, bool $dryRun = true): array
    {
        if (($manifest['version'] ?? null) !== 1 || empty($manifest['records'])) throw new RuntimeException('Invalid research manifest.');
        $keys = [];
        foreach ($manifest['records'] as $row) {
            $key = $row['hotel_id'].':'.$row['source_key'];
            if (isset($keys[$key]) || !preg_match('/^[a-z0-9-]{1,150}$/D', $row['source_key'])) throw new RuntimeException('Duplicate or invalid source identity.');
            $keys[$key] = true;
            if (!is_string($row['name']) || trim($row['name']) === '' || mb_strlen($row['name']) > 100
                || (isset($row['bed_type']) && mb_strlen($row['bed_type']) > 50)
                || !preg_match('/^https:\/\//D', $row['source_url']) || empty($row['evidence_sha256'])
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['checked_on'])
                || ($row['price_reference'] ?? null) !== null || ($row['physical_inventory'] ?? null) !== null) {
                throw new RuntimeException('Unsupported or unverified research fields.');
            }
            if (isset($row['capacity']) && (!is_int($row['capacity']) || $row['capacity'] < 1 || $row['capacity'] > 100)) throw new RuntimeException('Invalid verified occupancy.');
            if (($row['area_min_m2'] ?? null) !== null && ($row['area_max_m2'] < $row['area_min_m2'] || $row['area_min_m2'] <= 0)) throw new RuntimeException('Invalid area range.');
            foreach ($row['images'] as $image) {
                $relative = $image['local_path'] ?? '';
                if (!preg_match('#^storage/accommodations/verified-room-types/20261031/[a-z0-9-]+\.(?:jpg|webp)$#D', $relative)) throw new RuntimeException('Unexpected image path.');
                $path = public_path($relative);
                if (!is_file($path) || !hash_equals($image['sha256'], hash_file('sha256', $path)) || @getimagesize($path) === false
                    || empty($image['source_url']) || empty($image['page_url'])) throw new RuntimeException('Unverified image.');
            }
        }

        $this->db->beginTransaction();
        try {
            $report = ['created'=>0, 'enriched'=>0, 'images_added'=>0, 'evidence_added'=>0, 'unchanged'=>0, 'conflicts'=>[], 'hotels'=>[]];
            foreach (array_unique(array_column($manifest['records'], 'hotel_id')) as $hotelId) {
                $hotel = $this->db->table('hotel')->where('id', $hotelId)->lockForUpdate()->first();
                $expected = collect($manifest['records'])->firstWhere('hotel_id', $hotelId);
                if (!$hotel || $hotel->name !== $expected['hotel_name'] || $hotel->address !== $expected['hotel_address']) throw new RuntimeException('Hotel/branch identity changed; recheck research.');
                $report['hotels'][$hotelId] = ['name'=>$hotel->name,
                    'before'=>$this->db->table('hotel_room_type')->where('hotel_id',$hotelId)->where('status','!=','archived')->count(),
                    'physical_rooms'=>$this->db->table('room')->where('hotel_id',$hotelId)->count(), 'created'=>0];
            }
            foreach ($manifest['records'] as $row) {
                $records = [];
                foreach ($row['existing_room_type_ids'] as $typeId) {
                    $record = $this->db->table('hotel_room_type')->where('hotel_id',$row['hotel_id'])->where('room_type_id',$typeId)->lockForUpdate()->first();
                    if (!$record || $record->status === 'archived') throw new RuntimeException('Research alias no longer identifies a managed record.');
                    $records[] = $record;
                }
                if (!$records) {
                    $records = $this->db->table('hotel_room_type as c')->join('hotel_room_type_evidence as e','e.hotel_room_type_id','=','c.id')
                        ->where('c.hotel_id',$row['hotel_id'])->where('e.source_key',$row['source_key'])->select('c.*')->get()->all();
                    if (count($records)>1) throw new RuntimeException('Ambiguous imported source identity.');
                }
                if (!$records) {
                    $records = $this->db->table('hotel_room_type')->where('hotel_id',$row['hotel_id'])->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($row['name']))])->get()->all();
                    if (count($records)>1 || ($records && $records[0]->status==='archived')) throw new RuntimeException('Existing name needs an explicit reviewed alias: '.$row['hotel_id'].'/'.$row['source_key']);
                }
                if (!$records) {
                    $fields = collect($row)->only(['name','description','capacity','bed_type'])->all();
                    // No placeholder monetary amount, no implicit default occupancy, and no Room::create.
                    $typeId = $this->db->table('room_type')->insertGetId([...$fields,'base_price'=>null]);
                    $id = $this->db->table('hotel_room_type')->insertGetId([...$fields,'hotel_id'=>$row['hotel_id'],'room_type_id'=>$typeId,
                        'base_price'=>null,'amenities'=>$this->json($row['amenities']),'status'=>'inactive','images_configured'=>false,
                        'area_min_m2'=>$row['area_min_m2'],'area_max_m2'=>$row['area_max_m2']]);
                    $records = [$this->db->table('hotel_room_type')->where('id',$id)->first()];
                    $report['created']++;
                    $report['hotels'][$row['hotel_id']]['created']++;
                }
                foreach ($records as $record) {
                    $updates = [];
                    foreach (['description','bed_type','capacity','area_min_m2','area_max_m2'] as $field) {
                        if (($record->$field === null || $record->$field === '') && ($row[$field] ?? null) !== null) $updates[$field] = $row[$field];
                    }
                    if (empty(json_decode($record->amenities,true)) && $row['amenities']) $updates['amenities'] = $this->json($row['amenities']);
                    $conflicts = [];
                    foreach (['capacity','bed_type'] as $field) {
                        if (($row[$field] ?? null) !== null && $record->$field !== null && $record->$field !== ''
                            && (string)$record->$field !== (string)$row[$field]) $conflicts[$field] = ['retained'=>$record->$field,'source'=>$row[$field]];
                    }
                    if ($updates) { $this->db->table('hotel_room_type')->where('id',$record->id)->update($updates); $report['enriched']++; }
                    else $report['unchanged']++;
                    if ($conflicts) $report['conflicts'][] = ['hotel_id'=>$row['hotel_id'],'room_type_id'=>$record->room_type_id,'fields'=>$conflicts];
                    foreach ($row['images'] as $image) {
                        if ($this->db->table('hotel_room_type_image')->where('hotel_room_type_id',$record->id)->where('image_url',$image['local_path'])->exists()) continue;
                        // Keep configured galleries and cover choices. Enrich only a missing gallery.
                        if ($this->db->table('hotel_room_type_image')->where('hotel_room_type_id',$record->id)->exists()) continue;
                        $this->db->table('hotel_room_type_image')->insert(['hotel_room_type_id'=>$record->id,'image_url'=>$image['local_path'],'is_primary'=>true,'sort_order'=>0]);
                        $this->db->table('hotel_room_type')->where('id',$record->id)->update(['images_configured'=>true]);
                        $report['images_added']++;
                    }
                    $evidence = ['hotel_room_type_id'=>$record->id,'source_key'=>$row['source_key'],'source_url'=>$row['source_url'],'checked_on'=>$row['checked_on'],
                        'verified_fields'=>$this->json(['source_name'=>$row['name'],'description'=>$row['description'],'capacity'=>$row['capacity'],'occupancy_text'=>$row['occupancy_text'],
                            'bed_type'=>$row['bed_type'],'area_min_m2'=>$row['area_min_m2'],'area_max_m2'=>$row['area_max_m2'],'amenities'=>$row['amenities'],
                            'price_reference'=>null,'physical_inventory'=>null,'evidence_sha256'=>$row['evidence_sha256'],'catalog_source_url'=>$row['catalog_source_url']]),
                        'image_sources'=>$this->json($row['images']), 'issues'=>$this->json(['research'=>$row['issues'],'retained_conflicts'=>$conflicts])];
                    $existingEvidence = $this->db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$record->id)->where('source_key',$row['source_key'])->first();
                    if (!$existingEvidence) { $this->db->table('hotel_room_type_evidence')->insert($evidence); $report['evidence_added']++; }
                }
            }
            foreach ($report['hotels'] as $id => &$hotel) $hotel['after'] = $this->db->table('hotel_room_type')->where('hotel_id',$id)->where('status','!=','archived')->count();
            unset($hotel);
            if ($dryRun) $this->db->rollBack(); else $this->db->commit();
            return ['dry_run'=>$dryRun, ...$report];
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function json(array $data): string { return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
}
