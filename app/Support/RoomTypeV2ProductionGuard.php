<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Unregistered deployment path. Operator approval is an explicit, expiring, target-bound artifact. */
final class RoomTypeV2ProductionGuard
{
    private static ?\WeakMap $verified=null;
    public static function validate(array $connection,array $manifest,string $environment): void
    {
        if (!in_array($environment,['production','staging'],true) || ($manifest['approved']??false)!==true
            || ($manifest['session_timezone']??null)!=='+00:00' || ($manifest['historical_timestamps_verified']??false)!==true
            || !in_array($manifest['state']??null,['ready','active'],true) || ($manifest['checkout_enabled']??true)!==false
            || ($manifest['writers_drained']??false)!==true || ($manifest['callback_replay_ready']??false)!==true
            || !preg_match('/^[a-f0-9]{64}$/D',$manifest['restore_proof_sha256']??'')
            || !preg_match('/^[a-f0-9]{64}$/D',$manifest['source_manifest_sha256']??'')
            || !preg_match('/^[a-f0-9]{64}$/D',$manifest['schema_sha256']??'')
            || !preg_match('/^[a-f0-9-]{36}$/D',$manifest['server_uuid']??'')
            || !is_int($manifest['mapping_count']??null) || $manifest['mapping_count']<1
            || !isset($manifest['expires_at']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D',$manifest['expires_at'])
            || strtotime($manifest['expires_at'])<=time() || strtotime($manifest['expires_at'])>time()+86400) {
            throw new \RuntimeException('Explicit deployment approval, cutover checkpoints and recent restore proof required.');
        }
        foreach (['url','unix_socket','read','write'] as $key) if (!empty($connection[$key])) throw new \RuntimeException('Ambiguous connection overrides refused.');
        if (($connection['driver']??null)!=='mysql' || ($connection['username']??null)==='root') throw new \RuntimeException('A dedicated MySQL application account is required.');
        if (($connection['timezone']??null)!=='+00:00') throw new \RuntimeException('An explicitly reviewed UTC connection is required.');
        foreach (['host','port','database','username'] as $key) {
            if (!isset($manifest['target'][$key],$connection[$key]) || (string)$manifest['target'][$key]!== (string)$connection[$key] || (string)$connection[$key]==='') throw new \RuntimeException('Deployment connection does not match approved target.');
        }
    }

    public static function manifest(): array
    {
        $path=config('roomtype-v2.production_manifest'); $hash=config('roomtype-v2.production_manifest_sha256');
        $root=realpath(storage_path('app/private/roomtype-v2-deployment')); $file=is_string($path)?realpath($path):false;
        if (!$root || !$file || !str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')
            || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash) || !hash_equals($hash,hash_file('sha256',$file))) throw new \RuntimeException('Approved private deployment manifest is missing or changed.');
        $manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        if (($manifest['synthetic_rehearsal']??false)!==true) {
            foreach (['source_manifest','restore_proof'] as $proof) {
                $artifact=isset($manifest[$proof.'_path'])?realpath($manifest[$proof.'_path']):false;
                if (!$artifact || !str_starts_with(str_replace('\\','/',$artifact),str_replace('\\','/',$root).'/')
                    || !isset($manifest[$proof.'_sha256']) || !hash_equals($manifest[$proof.'_sha256'],hash_file('sha256',$artifact))) throw new \RuntimeException('Reviewed deployment proof is missing or changed.');
                $data=json_decode(file_get_contents($artifact),true,512,JSON_THROW_ON_ERROR);
                if ($proof==='restore_proof' && (($data['restored_matching']??false)!==true || ($data['primary_backup_verified']??false)!==true || ($data['synthetic_only']??true)!==false)) throw new \RuntimeException('A synthetic restore cannot authorize a production cutover.');
                $identity=$data['identity']??[];
                if (($identity['db']??null)!==($manifest['target']['database']??null) || (string)($identity['port']??'')!==(string)($manifest['target']['port']??null)
                    || ($identity['server_uuid']??null)!==($manifest['server_uuid']??null)) throw new \RuntimeException('Deployment proof belongs to a different target.');
            }
        }
        return $manifest;
    }

    public static function connection(string $name): \Illuminate\Database\Connection
    {
        if (config('app.debug')===true) throw new \RuntimeException('Production V2 requires debug disabled before opening a connection.');
        $manifest=self::manifest(); $settings=config('database.connections.'.$name,[]);
        self::validate($settings,$manifest,app()->environment()); // Before opening a connection.
        $synthetic=($manifest['synthetic_rehearsal']??false)===true;
        if ($synthetic) {
            $owned=RoomTypeV2TestDatabase::settings(); RoomTypeV2TestDatabase::validate(array_merge($owned,$settings),true);
            foreach (['host','port','username','password'] as $key) if ((string)($settings[$key]??'')!==(string)$owned[$key]) throw new \RuntimeException('Synthetic deployment target differs from owned test credentials.');
            if ($manifest['server_uuid']!==$owned['server_uuid']) throw new \RuntimeException('Synthetic deployment identity is not owned.');
        }
        if ($name!==config('database.default')) throw new \RuntimeException('All Eloquent and service traffic must use the approved default connection.');
        if ($manifest['state']==='ready' && !app()->isDownForMaintenance()) throw new \RuntimeException('Initial deployment requires maintenance.');
        $db=DB::connection($name);
        $pdo=$db->getPdo(); self::$verified??=new \WeakMap();
        $approvalHash=hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR));
        if ((self::$verified[$pdo]??null)===$approvalHash) return $db;
        $actual=$db->selectOne('SELECT DATABASE() AS db, @@port AS port, @@server_uuid AS uuid, @@session.time_zone AS session_timezone');
        if ($actual->session_timezone!=='+00:00') throw new \RuntimeException('Database session timezone differs from the reviewed UTC policy.');
        if ($actual->db!==$manifest['target']['database'] || (string)$actual->port!==(string)$manifest['target']['port'] || $actual->uuid!==$manifest['server_uuid']) throw new \RuntimeException('Connected server identity differs from approval.');
        $grants=[];
        foreach ($db->select('SHOW GRANTS') as $row) {
            $grant=array_values((array)$row)[0];
            $grants[]=$grant;
            if (str_starts_with($grant,'GRANT USAGE ON *.* ')) continue;
            if ($synthetic) {
                if (stripos($grant,'hotel_booking')!==false || str_contains($grant,' ON *.* ')) throw new \RuntimeException('Synthetic account has forbidden privileges.');
            } elseif (!preg_match('/^GRANT ([A-Z_, ]+) ON `([^`]+)`\.\* TO /',$grant,$m) || str_replace('\\','',$m[2])!==$manifest['target']['database']
                || preg_match('/\b(ALL|CREATE|ALTER|DROP|TRIGGER|EXECUTE|EVENT|GRANT)\b/',$m[1]) || str_contains($grant,'WITH GRANT OPTION')) throw new \RuntimeException('Production runtime requires a dedicated account with no DDL/global/cross-schema rights.');
        }
        if (!$synthetic) RoomTypeV2DatabasePrivileges::assert($grants,$manifest['target']['database'],['SELECT','INSERT','UPDATE','DELETE'],(bool)$db->selectOne('SELECT @@partial_revokes AS literal_grants')->literal_grants);
        RoomTypeV2SchemaReadiness::assert($db,$manifest['mapping_count']);
        if (!hash_equals($manifest['schema_sha256'],RoomTypeV2SchemaReadiness::fingerprint($db))) throw new \RuntimeException('Schema fingerprint differs from approved cutover proof.');
        self::$verified[$pdo]=$approvalHash;
        return $db;
    }
}
