<?php

namespace Tests\Support;

use App\Support\RoomTypeV2TestDatabase;
use App\Support\RoomTypeV2Preflight;

/** Synthetic backup/restore proof only; this helper cannot accept a production target. */
final class RoomTypeV2BackupRehearsal
{
    public static function run(): array
    {
        $db=RoomTypeV2TestDatabase::connection('mysql'); $settings=RoomTypeV2TestDatabase::settings();
        $before=RoomTypeV2Preflight::inspect($db->getPdo()); $target=null;
        foreach ($settings['schemas'] as $candidate) if ((int)$db->selectOne('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$candidate])->n===0) { $target=$candidate; break; }
        if (!$target) throw new \RuntimeException('A new empty owned restore schema is required.');
        config(['database.connections.v2_restore'=>array_replace(config('database.connections.mysql'),['database'=>$target])]);
        $restore=RoomTypeV2TestDatabase::connection('v2_restore');
        $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))); $run=$runtime.'/backup-'.bin2hex(random_bytes(6)); mkdir($run,0700);
        $defaults=$run.'/client.cnf'; file_put_contents($defaults,"[client]\nhost=127.0.0.1\nport=".$settings['port']."\nuser=".$settings['username']."\npassword=".$settings['password']."\nprotocol=TCP\n");
        $binary='C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';
        $dump=new \Symfony\Component\Process\Process([$binary.'mysqldump.exe','--defaults-file='.$defaults,'--single-transaction','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--skip-comments',$db->getDatabaseName()]);
        $dump->setTimeout(60); $dump->mustRun(); $sql=$dump->getOutput();
        if (str_contains($sql,'hotel_booking') || str_contains($sql,'CREATE DATABASE')) throw new \RuntimeException('Unexpected database directive.');
        file_put_contents($run.'/schema-data-ledger-financial.sql',$sql);
        $import=new \Symfony\Component\Process\Process([$binary.'mysql.exe','--defaults-file='.$defaults,'--default-character-set=utf8mb4',$target]);
        $import->setInput($sql); $import->setTimeout(60); $import->mustRun();
        $after=RoomTypeV2Preflight::inspect($restore->getPdo());
        foreach (['tables','schema_sha256','business_table_checksums','foreign_keys','fk_orphans','checks','source_groups','migration_ledger'] as $field) {
            $expected=$before[$field]; $actual=$after[$field];
            if ($field==='foreign_keys') foreach ($expected as &$fk) { if ($fk['REFERENCED_TABLE_SCHEMA']===$db->getDatabaseName()) $fk['REFERENCED_TABLE_SCHEMA']=$target; } unset($fk);
            if ($expected!==$actual) {
                $definitions=[];
                foreach (array_keys($before['tables']) as $table) $definitions[$table]=['source'=>(array)$db->selectOne('SHOW CREATE TABLE `'.$table.'`'),'restored'=>(array)$restore->selectOne('SHOW CREATE TABLE `'.$table.'`')];
                file_put_contents($run.'/restore-diagnostic.json',json_encode(['source'=>$db->getDatabaseName(),'restore'=>$target,'mismatch'=>$field,'definitions'=>$definitions],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
                throw new \RuntimeException('Restore mismatch: '.$field.'; private diagnostic retained.');
            }
        }
        $assets=[];
        foreach ($db->table('room_type_image')->distinct()->pluck('image_url') as $asset) {
            if (!preg_match('~^image/[a-zA-Z0-9_ ./()-]+$~D',$asset) || str_contains($asset,'..')) throw new \RuntimeException('Unexpected fixture asset.');
            $source=realpath(public_path($asset)); if (!$source) throw new \RuntimeException('Fixture asset missing.');
            foreach (['assets-backup','assets-restored'] as $folder) { $file=$run.'/'.$folder.'/'.$asset; if (!is_dir(dirname($file))) mkdir(dirname($file),0700,true); copy($folder==='assets-backup'?$source:$run.'/assets-backup/'.$asset,$file); }
            $hash=hash_file('sha256',$source); if ($hash!==hash_file('sha256',$run.'/assets-restored/'.$asset)) throw new \RuntimeException('Restored asset checksum mismatch.'); $assets[$asset]=$hash;
        }
        $proof=['source'=>$db->getDatabaseName(),'restore'=>$target,'port'=>$settings['port'],'schema_sha256'=>$before['schema_sha256'],
            'dump_sha256'=>hash('sha256',$sql),'tables'=>$before['tables'],'business_checksums'=>$before['business_table_checksums'],
            'assets'=>$assets,'restored_matching'=>true,'synthetic_only'=>true,'primary_backup_verified'=>false];
        file_put_contents($run.'/restore-proof.json',json_encode($proof,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        return $proof;
    }
}
