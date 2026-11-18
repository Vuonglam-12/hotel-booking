<?php

// Explicit backup/restore verification and exact-path additive deployment. Never runs V2 migrations.
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$options = getopt('', ['backup', 'settings:', 'apply:', 'verify:']);
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $error){fwrite(STDERR,'Deployment stopped: '.$error->getMessage().PHP_EOL);exit(1);});
$config = config('database.connections.mysql');
if (($config['database']??null)!=='hotel_booking' || !in_array($config['host']??null,['127.0.0.1','localhost'],true)
    || (int)($config['port']??0)!==3306 || config('roomtype-v2.production_manifest')!==null || config('roomtype-v2.prepared_routes')) throw new RuntimeException('Unexpected primary target or V2 state.');
$db = Illuminate\Support\Facades\DB::connection('mysql');
$source = $db->getPdo(); $source->exec("SET time_zone='+00:00'");
$private = $root . '/storage/app/private/hotel-roomtype-deployment';
if (!is_dir($private)) mkdir($private,0700,true);
$binary = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';

function snapshot(PDO $pdo): array {
    $pdo->exec('SET SESSION show_create_table_verbosity=1');
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = $pdo->query('SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_ASSOC);
    $out=[];
    foreach ($tables as $table) {
        $name=$table['TABLE_NAME'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$name))throw new RuntimeException('Unexpected SQL identifier.');
        $ddl=$pdo->query('SHOW CREATE TABLE `'.$name.'`')->fetch(PDO::FETCH_NUM)[1];
        $ddl=str_replace('`'.$database.'`','`{database}`',$ddl);
        // SHOW CREATE may spell out a column charset implied by its explicit collation after restore.
        $ddl=preg_replace_callback('/ CHARACTER SET ([a-zA-Z0-9_]+)( COLLATE ([a-zA-Z0-9_]+))/',
            fn($match)=>str_starts_with($match[3],$match[1].'_')?$match[2]:$match[0],$ddl);
        $item=['type'=>$table['TABLE_TYPE'],'schema_sha256'=>hash('sha256',$ddl)];
        $columnQuery=$pdo->prepare('SELECT COLUMN_NAME,ORDINAL_POSITION,COLUMN_DEFAULT,IS_NULLABLE,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA,GENERATION_EXPRESSION,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $columnQuery->execute([$database,$name]);$item['columns_sha256']=hash('sha256',serialize($columnQuery->fetchAll(PDO::FETCH_ASSOC)));
        if ($table['TABLE_TYPE']==='BASE TABLE') {
            $hashes=[];$statement=$pdo->query('SELECT * FROM `'.$name.'`');
            while($row=$statement->fetch(PDO::FETCH_ASSOC))$hashes[]=hash('sha256',serialize($row));
            sort($hashes,SORT_STRING);$item['count']=count($hashes);$item['rows_sha256']=hash('sha256',implode("\n",$hashes));
        }
        $out[$name]=$item;
    }
    return $out;
}
function defaults(array $config,string $path): void {
    $quote=fn($value)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$value).'"';
    file_put_contents($path,"[client]\nprotocol=TCP\nhost=".$quote($config['host'])."\nport=".(int)$config['port']."\nuser=".$quote($config['username'])."\npassword=".$quote($config['password'])."\n");
}
function process(array $command, ?string $input=null): string {
    $process=new Symfony\Component\Process\Process($command);$process->setTimeout(180);
    if($input!==null)$process->setInput($input);
    $process->mustRun();return $process->getOutput();
}

if(isset($options['backup'])) {
    if(empty($options['settings']))throw new RuntimeException('Owned isolated server settings required to verify restore.');
    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($options['settings']));
    $settings=App\Support\RoomTypeV2TestDatabase::settings();App\Support\RoomTypeV2TestDatabase::validate([...$settings,'driver'=>'mysql'],true);
    $runtime=dirname(realpath($options['settings']));
    $server=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($runtime.'/server.json')),true,512,JSON_THROW_ON_ERROR);
    $control=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($runtime.'/control-bootstrap.json')),true,512,JSON_THROW_ON_ERROR);
    $restore=new PDO('mysql:host=127.0.0.1;port='.(int)$settings['port'].';charset=utf8mb4',$control['username'],$control['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $identity=$restore->query('SELECT @@datadir directory,@@server_uuid uuid')->fetch(PDO::FETCH_ASSOC);
    if(realpath($identity['directory'])!==realpath($server['data'])||$identity['uuid']!==$settings['server_uuid'])throw new RuntimeException('Restore server identity mismatch.');
    $target=null;
    foreach($settings['schemas'] as $schema){$check=$restore->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');$check->execute([$schema]);if((int)$check->fetchColumn()===0){$target=$schema;break;}}
    if(!$target)throw new RuntimeException('No empty owned restore schema.');
    $run=$private.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(4));mkdir($run,0700);
    $client=$run.'/source-client.cnf';$restoreClient=$run.'/restore-client.cnf';
    try {
        defaults($config,$client);defaults(['host'=>'127.0.0.1','port'=>$settings['port'],...$control],$restoreClient);
        $before=snapshot($source);
        $sql=process([$binary.'mysqldump.exe','--defaults-file='.$client,'--single-transaction','--skip-lock-tables','--no-tablespaces','--set-gtid-purged=OFF','--hex-blob','--routines','--events','--triggers','--default-character-set=utf8mb4','--skip-comments','hotel_booking']);
        if(!$sql||str_contains($sql,'CREATE DATABASE')||preg_match('/^USE /m',$sql))throw new RuntimeException('Unexpected or empty dump.');
        file_put_contents($run.'/database.sql',$sql);
        if(snapshot($source)!==$before)throw new RuntimeException('Primary data changed during backup; retry before deploying.');
        $restoreSql=str_replace('`hotel_booking`.','`'.$target.'`.',$sql);
        process([$binary.'mysql.exe','--defaults-file='.$restoreClient,'--default-character-set=utf8mb4',$target],$restoreSql);
        $restore->exec('USE `'.$target.'`');$restore->exec("SET time_zone='+00:00'");
        $restored=snapshot($restore);
        if($before!==$restored){file_put_contents($run.'/restore-mismatch.json',json_encode(['source'=>$before,'restored'=>$restored],JSON_PRETTY_PRINT));throw new RuntimeException('Backup restore differs from primary snapshot.');}
        $proof=['source'=>'hotel_booking','source_port'=>3306,'verified_at'=>gmdate(DATE_ATOM),'dump_sha256'=>hash_file('sha256',$run.'/database.sql'),
            'restore_database'=>$target,'restore_port'=>$settings['port'],'restored_matching'=>true,'snapshot'=>$before];
        file_put_contents($run.'/verified-backup.json',json_encode($proof,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        echo json_encode(['verified'=>true,'tables_and_views'=>count($before),'proof'=>$run.'/verified-backup.json','restore_port'=>$settings['port']]),PHP_EOL;
    } finally {if(is_file($client))unlink($client);if(is_file($restoreClient))unlink($restoreClient);}
    exit;
}

$proofPath=$options['apply']??$options['verify']??null;
$resolved=$proofPath?realpath($proofPath):false;
if(!$resolved||!str_starts_with($resolved,realpath($private).DIRECTORY_SEPARATOR)||basename($resolved)!=='verified-backup.json')throw new RuntimeException('Verified private backup proof required.');
$proof=json_decode(file_get_contents($resolved),true,512,JSON_THROW_ON_ERROR);
if(($proof['source']??'')!=='hotel_booking'||($proof['restored_matching']??false)!==true||!hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($resolved).'/database.sql')))throw new RuntimeException('Backup proof or dump mismatch.');
if(isset($options['apply'])){
    if(time()-strtotime($proof['verified_at'])>3600||snapshot($source)!==$proof['snapshot'])throw new RuntimeException('Backup is stale or primary changed; repeat backup verification.');
    $code=Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>'database/migrations/2026_10_13_000010_create_hotel_room_type_catalog.php','--force'=>true]);
    echo Illuminate\Support\Facades\Artisan::output();if($code!==0)throw new RuntimeException('Exact-path migration failed.');
}
// Original tables, relationships and snapshots must survive the additive migration unchanged.
$after=snapshot($source);
foreach($proof['snapshot'] as $table=>$expected){
    if($table==='migrations'||$table==='room')continue;
    if(($after[$table]??null)!==$expected)throw new RuntimeException('Original data/schema changed: '.$table);
}
$columns=$source->query('SHOW COLUMNS FROM room')->fetchAll(PDO::FETCH_COLUMN);$columns=array_values(array_diff($columns,['catalog_retired']));
$hashes=[];foreach($source->query('SELECT `'.implode('`,`',$columns).'` FROM room')->fetchAll(PDO::FETCH_ASSOC) as $row)$hashes[]=hash('sha256',serialize($row));sort($hashes,SORT_STRING);
if(count($hashes)!==$proof['snapshot']['room']['count']||hash('sha256',implode("\n",$hashes))!==$proof['snapshot']['room']['rows_sha256'])throw new RuntimeException('Original physical room data changed.');
$roomDDL=$source->query('SHOW CREATE TABLE room')->fetch(PDO::FETCH_NUM)[1];
$roomDDL=preg_replace('/^  `catalog_retired`[^\n]*\n/m','',$roomDDL);
$roomDDL=str_replace('`hotel_booking`','`{database}`',$roomDDL);
$roomDDL=preg_replace_callback('/ CHARACTER SET ([a-zA-Z0-9_]+)( COLLATE ([a-zA-Z0-9_]+))/',fn($match)=>str_starts_with($match[3],$match[1].'_')?$match[2]:$match[0],$roomDDL);
if(hash('sha256',$roomDDL)!==$proof['snapshot']['room']['schema_sha256'])throw new RuntimeException('Original physical room schema changed beyond the additive retirement marker.');
if($db->table('room')->where('catalog_retired',true)->exists())throw new RuntimeException('Migration unexpectedly retired physical rooms.');
$ledger=[];foreach($source->query("SELECT * FROM migrations WHERE migration!='2026_10_13_000010_create_hotel_room_type_catalog'")->fetchAll(PDO::FETCH_ASSOC) as $row)$ledger[]=hash('sha256',serialize($row));sort($ledger,SORT_STRING);
if(count($ledger)!==$proof['snapshot']['migrations']['count']||hash('sha256',implode("\n",$ledger))!==$proof['snapshot']['migrations']['rows_sha256'])throw new RuntimeException('Original migration ledger changed.');
$result=['original_data_preserved'=>true,'catalog_pairs'=>$db->table('hotel_room_type')->count(),'room_count'=>$db->table('room')->count(),
    'meander'=>$db->table('room')->where('hotel_id',1)->select('room_type_id')->selectRaw('COUNT(*) count')->groupBy('room_type_id')->get()->toArray()];
file_put_contents(dirname($resolved).'/post-migration-verification.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result),PHP_EOL;
