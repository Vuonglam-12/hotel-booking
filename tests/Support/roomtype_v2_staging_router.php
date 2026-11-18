<?php

// php -S 127.0.0.1:<owned-http-port> -t public tests/Support/roomtype_v2_staging_router.php
$stageFile=getenv('AUREL_ROOMTYPE_V2_STAGING');
$stage=$stageFile && is_file($stageFile)?json_decode(file_get_contents($stageFile),true,512,JSON_THROW_ON_ERROR):null;
if (!$stage || ($stage['synthetic_only']??false)!==true) throw new RuntimeException('Isolated staging descriptor required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']); putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$stage['database']);
require __DIR__.'/roomtype_v2_test_bootstrap.php'; // Refuses primary before Laravel/PDO.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) { http_response_code(403); exit; }
$root=realpath(dirname(__DIR__,2).'/public'); $file=realpath($root.'/'.rawurldecode($path));
if ($file && str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/') && preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$file)) return false;
if ($path==='/__stage/health') { header('Content-Type: application/json'); echo '{"isolated":true,"synthetic_only":true}'; exit; }
$_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['SCRIPT_FILENAME']=$root.'/index.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php'; $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class); $kernel->bootstrap();
config(['app.debug'=>false,'app.url'=>'http://'.$_SERVER['HTTP_HOST'],'view.compiled'=>$stage['runtime'].'/views',
    'database.connections.mysql.timezone'=>'+00:00',
    'roomtype-v2.staging_checkout'=>true,'roomtype-v2.production_manifest'=>null]);
$qrPath=isset($stage['qr_banking_descriptor'])?realpath($stage['qr_banking_descriptor']):false;
if ($qrPath) {
    $qr=json_decode(file_get_contents($qrPath),true,512,JSON_THROW_ON_ERROR);
    $identity=App\Support\RoomTypeV2TestDatabase::connection('mysql')->selectOne('SELECT DATABASE() db,@@server_uuid uuid');
    if (dirname($qrPath)!==realpath($stage['runtime']) || basename($qrPath)!=='qr-banking-staging.json'
        || ($qr['synthetic_only']??false)!==true || ($qr['mode']??null)!=='sepay_test'
        || ($qr['database']??null)!==$stage['database'] || $identity->db!==$qr['database'] || $identity->uuid!==($qr['server_uuid']??null)
        || strtotime($qr['expires_at']??'')<=time()) throw new RuntimeException('Expired or mismatched QR staging artifact.');
    config(['roomtype-v2.banking_instructions'=>$qr['banking_instructions']??null]);
}
if (!is_dir($stage['runtime'].'/views')) mkdir($stage['runtime'].'/views',0700,true);
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($stage['runtime'].'/absent-hot');
Tests\Support\RoomTypeV2CutoverFixture::protocol();
App\Support\RoomTypeV2Integration::acceptance();
$request=Illuminate\Http\Request::capture(); $response=$kernel->handle($request); $response->send(); $kernel->terminate($request,$response);
