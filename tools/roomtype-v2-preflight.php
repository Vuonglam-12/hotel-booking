<?php

// This tool never loads .env. Primary access requires a separate operator-approved, read-only target artifact.
require dirname(__DIR__).'/vendor/autoload.php';
$options=getopt('',['approval:','confirm-target:','test-settings:','database:','output:']);
$output=$options['output']??null; $private=realpath(dirname(__DIR__).'/storage/app/private');
if (!$output || !$private || !realpath(dirname($output)) || !str_starts_with(str_replace('\\','/',realpath(dirname($output))),str_replace('\\','/',$private).'/')) throw new RuntimeException('Report output must be inside private storage; no connection opened.');
if (isset($options['test-settings'])) {
    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$options['test-settings']); putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$options['database']);
    require dirname(__DIR__).'/tests/Support/roomtype_v2_test_bootstrap.php';
    $app=require dirname(__DIR__).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo=App\Support\RoomTypeV2TestDatabase::connection('mysql')->getPdo();
} else {
    $root=realpath(dirname(__DIR__).'/storage/app/private/roomtype-v2-deployment');
    $file=isset($options['approval'])?realpath($options['approval']):false;
    if (!$root || !$file || !str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')) throw new RuntimeException('Separate approved private read-only target artifact required; no connection opened.');
    $a=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    $t=App\Support\RoomTypeV2PreflightApproval::validate($a,$options['confirm-target']??null);
    $pdoOptions=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5];
    if (isset($t['ssl_ca'])) { $pdoOptions[PDO::MYSQL_ATTR_SSL_CA]=$t['ssl_ca']; $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true; }
    $pdo=new PDO('mysql:host='.$t['host'].';port='.$t['port'].';dbname='.$t['database'].';charset=utf8mb4',$t['username'],$t['password'],$pdoOptions);
    $identity=$pdo->query('SELECT DATABASE() db,@@port port,@@server_uuid uuid')->fetch(PDO::FETCH_ASSOC);
    if ($identity['db']!==$t['database'] || (int)$identity['port']!==(int)$t['port'] || $identity['uuid']!==$a['server_uuid']) throw new RuntimeException('Server identity mismatch.');
    App\Support\RoomTypeV2DatabasePrivileges::assert($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN),$t['database'],['SELECT'],(bool)$pdo->query('SELECT @@partial_revokes')->fetchColumn());
    if (!in_array($t['host'],['127.0.0.1','::1','localhost'],true) && !$pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM)[1]) throw new RuntimeException('Remote preflight did not negotiate TLS.');
}
$report=App\Support\RoomTypeV2Preflight::inspect($pdo);
file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['read_only'=>true,'report_written'=>true,'tables'=>count($report['tables']),'no_personal_rows_exported'=>true]);
