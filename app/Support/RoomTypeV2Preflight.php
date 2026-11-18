<?php

namespace App\Support;

/** Read-only inspection. Caller must authorize and verify the target before supplying PDO. */
final class RoomTypeV2Preflight
{
    public static function inspect(\PDO $pdo): array
    {
        $one=fn(string $sql)=>$pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
        $rows=fn(string $sql)=>$pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $identity=$one('SELECT DATABASE() AS db, @@port AS port, @@server_uuid AS server_uuid, @@version AS version, @@session.time_zone AS session_timezone, @@system_time_zone AS system_timezone');
        $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            $tables=[]; $ddl=[]; $hashes=[];
            foreach ($rows('SHOW FULL TABLES') as $row) {
                $name=array_values($row)[0]; $kind=array_values($row)[1];
                if (!preg_match('/^[a-zA-Z0-9_]+$/D',$name)) throw new \RuntimeException('Unexpected schema identifier; inspect manually.');
                if ($kind!=='BASE TABLE') { $tables[$name]=['kind'=>$kind,'inspection'=>'manual']; continue; }
                $tables[$name]=['count'=>(int)$one('SELECT COUNT(*) AS n FROM `'.$name.'`')['n']];
                $create=array_values($one('SHOW CREATE TABLE `'.$name.'`'))[1];
                $ddl[$name]=RoomTypeV2SchemaReadiness::normalizeDefinition($create);
                if (in_array($name,['hotel','room_type','room','room_image','hotel_image','hotel_amenity','booking','booking_room','payment','invoice','invoice_item','migrations','room_availability','room_type_image','room_type_legacy_map','payment_gateway_receipt','payment_gateway_unmatched_event','booking_effect','manual_payment_verification','booking_state_audit','booking_effect_notification','booking_delivery_audit'],true)) {
                    $data=$rows('SELECT * FROM `'.$name.'`'); $encoded=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$data); sort($encoded,SORT_STRING);
                    $hashes[$name]=hash('sha256',implode("\n",$encoded));
                }
            }
            ksort($ddl); ksort($hashes);
            $db=$pdo->quote($identity['db']);
            $fks=$rows('SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='.$db.' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION');
            $groups=[]; foreach ($fks as $fk) $groups[$fk['TABLE_NAME'].'.'.$fk['CONSTRAINT_NAME']][]=$fk;
            $orphans=[];
            foreach ($groups as $key=>$cols) {
                $first=$cols[0];
                if ($first['REFERENCED_TABLE_SCHEMA']!==$identity['db']) { $orphans[$key]='Cross-schema reference; manual review required.'; continue; }
                foreach ($cols as $c) foreach (['TABLE_NAME','COLUMN_NAME','REFERENCED_TABLE_NAME','REFERENCED_COLUMN_NAME'] as $n) if (!preg_match('/^[a-zA-Z0-9_]+$/D',$c[$n])) throw new \RuntimeException('Unexpected FK identifier.');
                $join=implode(' AND ',array_map(fn($c)=>'c.`'.$c['COLUMN_NAME'].'`=p.`'.$c['REFERENCED_COLUMN_NAME'].'`',$cols));
                $nonnull=implode(' AND ',array_map(fn($c)=>'c.`'.$c['COLUMN_NAME'].'` IS NOT NULL',$cols));
                $orphans[$key]=(int)$one('SELECT COUNT(*) AS n FROM `'.$first['TABLE_NAME'].'` c LEFT JOIN `'.$first['REFERENCED_TABLE_NAME'].'` p ON '.$join.' WHERE '.$nonnull.' AND p.`'.$first['REFERENCED_COLUMN_NAME'].'` IS NULL')['n'];
            }
            $checks=[];
            if (isset($tables['booking'],$tables['booking_room'])) {
                $checks['active_duplicate_groups']=(int)$one("SELECT COUNT(*) AS n FROM (SELECT customer_id,hotel_id,check_in,check_out FROM booking WHERE status IN ('pending','confirmed') GROUP BY customer_id,hotel_id,check_in,check_out HAVING COUNT(*)>1) x")['n'];
                $checks['invalid_booking_lines']=(int)$one('SELECT COUNT(*) AS n FROM booking_room br JOIN booking b ON b.id=br.booking_id WHERE br.quantity IS NULL OR br.quantity<1 OR br.nights IS NULL OR br.nights<1 OR br.nights<>DATEDIFF(b.check_out,b.check_in) OR br.price_at_booking IS NULL OR br.price_at_booking<0')['n'];
                $checks['booking_money_mismatches']=(int)$one('SELECT COUNT(*) AS n FROM booking b JOIN (SELECT booking_id,SUM(quantity*nights*price_at_booking) total FROM booking_room GROUP BY booking_id) br ON br.booking_id=b.id WHERE b.total_price<>br.total')['n'];
                $checks['dangling_physical_history']=(int)$one('SELECT COUNT(*) AS n FROM booking_room br LEFT JOIN room r ON r.id=br.room_id WHERE br.room_id IS NOT NULL AND r.id IS NULL')['n'];
                $checks['wrong_hotel_physical_history']=(int)$one('SELECT COUNT(*) AS n FROM booking_room br JOIN booking b ON b.id=br.booking_id JOIN room r ON r.id=br.room_id WHERE r.hotel_id<>b.hotel_id')['n'];
                $checks['booking_timestamps']=$one('SELECT MIN(created_at) oldest_created,MAX(created_at) newest_created,SUM(created_at IS NULL) missing_created,SUM(expires_at IS NULL AND status=\'pending\') pending_without_deadline FROM booking');
            }
            if (isset($tables['payment'],$tables['booking'])) $checks['payment_snapshot_mismatches']=(int)$one('SELECT COUNT(*) AS n FROM payment p JOIN booking b ON b.id=p.booking_id WHERE p.amount<>b.total_price')['n'];
            $source=[];
            if (isset($tables['room'],$tables['room_type'])) $source=$rows('SELECT r.hotel_id,r.room_type_id,COUNT(*) total_rooms,COUNT(DISTINCT r.price) price_variants,COUNT(DISTINCT r.capacity) capacity_variants,MIN(r.price) price,MIN(r.capacity) capacity,SUM(r.price<>rt.base_price OR r.capacity<>rt.capacity) type_mismatches,SUM(r.status<>\'available\') unavailable_rooms FROM room r JOIN room_type rt ON rt.id=r.room_type_id GROUP BY r.hotel_id,r.room_type_id ORDER BY r.hotel_id,r.room_type_id');
            $ledger=isset($tables['migrations'])?$rows('SELECT migration,batch FROM migrations ORDER BY id'):[];
            return ['identity'=>$identity,'tables'=>$tables,'schema_definitions'=>$ddl,'schema_sha256'=>hash('sha256',json_encode($ddl,JSON_THROW_ON_ERROR)),
                'business_table_checksums'=>$hashes,'foreign_keys'=>$fks,'fk_orphans'=>$orphans,'checks'=>$checks,'source_groups'=>$source,
                'migration_ledger'=>$ledger,'read_only'=>true,'counts_are_observed_not_plan'=>true,'private_personal_rows_exported'=>false];
        } finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    }
}
