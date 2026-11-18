<?php

namespace App\Support;

final class RoomTypeV2SchemaReadiness
{
    public static function normalizeDefinition(string $sql): string
    {
        $sql=preg_replace('/ AUTO_INCREMENT=\d+/','',$sql);
        // mysqldump can make a charset explicit although the same collation already determines it.
        // Keep the collation, field types/order/defaults, indexes, CHECKs, FK rules and table options.
        return preg_replace_callback('/ CHARACTER SET ([a-z0-9_]+) COLLATE ([a-z0-9_]+)/i',function ($m) {
            return str_starts_with(strtolower($m[2]),strtolower($m[1]).'_')?' COLLATE '.$m[2]:$m[0];
        },$sql);
    }
    public static function fingerprint(\Illuminate\Database\Connection $db): string
    {
        $schema=[];
        foreach ($db->select('SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE=? ORDER BY TABLE_NAME',[$db->getDatabaseName(),'BASE TABLE']) as $row) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D',$row->name)) throw new \RuntimeException('Unexpected schema identifier.');
            $definition=array_values((array)$db->selectOne('SHOW CREATE TABLE `'.$row->name.'`'))[1];
            $schema[$row->name]=self::normalizeDefinition($definition);
        }
        return hash('sha256',json_encode($schema,JSON_THROW_ON_ERROR));
    }
    public static function assert(\Illuminate\Database\Connection $db,int $mappingCount): void
    {
        foreach (['room_type_image','room_type_legacy_map','payment_gateway_receipt','payment_gateway_unmatched_event','booking_effect','manual_payment_verification',
            'booking_state_audit','booking_effect_notification','booking_delivery_audit'] as $table) if (!$db->getSchemaBuilder()->hasTable($table)) throw new \RuntimeException('Missing V2 prerequisite: '.$table);
        foreach (['hotel_id','total_rooms','status'] as $field) if (!$db->getSchemaBuilder()->hasColumn('room_type',$field)) throw new \RuntimeException('Owned Type fields required.');
        foreach (['next_attempt_at','transport_started_at'] as $field) if (!$db->getSchemaBuilder()->hasColumn('booking_effect',$field)) throw new \RuntimeException('Delivery safety columns required.');
        $key=$db->selectOne('SELECT EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',[$db->getDatabaseName(),'booking','active_duplicate_key']);
        $index=$db->select('SHOW INDEX FROM booking WHERE Key_name=?',['uq_booking_active']);
        if (!$key || !str_contains($key->EXTRA,'GENERATED') || !str_contains($key->GENERATION_EXPRESSION,'confirmed') || count($index)!==5
            || (int)$index[0]->Non_unique!==0) throw new \RuntimeException('Verified active Booking uniqueness required.');
        if ($db->table('room_type_legacy_map')->count()!==$mappingCount) throw new \RuntimeException('Mapping count differs from approved source manifest.');
        $bad=$db->table('booking_room as br')->join('booking as b','b.id','=','br.booking_id')->leftJoin('room_type as rt','rt.id','=','br.room_type_id')
            ->where(fn($q)=>$q->whereNull('rt.hotel_id')->orWhereColumn('rt.hotel_id','!=','b.hotel_id'))->exists();
        if ($bad || $db->table('room_type')->whereNotNull('hotel_id')->where(fn($q)=>$q->whereNull('total_rooms')->orWhereNull('status'))->exists()) throw new \RuntimeException('Remap or owned inventory is incomplete.');
        // Exact DDL/FK fingerprints must also match the independently reviewed cutover preflight artifact.
    }
}
