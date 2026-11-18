<?php

$file=getenv('AUREL_BOOKING_DEMO_STAGE');
$stage=$file&&is_file($file)?json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR):null;
$root=dirname(__DIR__);$allowed=realpath($root.'/storage/app/private/roomtype-v2-tests');
if (!$stage||($stage['synthetic_only']??false)!==true||($stage['booking_demo']??false)!==true
    ||!$allowed||!str_starts_with(realpath($file),$allowed.DIRECTORY_SEPARATOR)
    ||basename($file)!=='staging-session.json'||realpath($stage['runtime'])!==dirname(realpath($file))) throw new RuntimeException('Owned DEMO descriptor required.');
if (!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) { http_response_code(403);exit; }
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']);putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$stage['database']);
require $root.'/tests/Support/roomtype_v2_test_bootstrap.php';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/__demo/health') {header('Content-Type: application/json');echo json_encode(['demo'=>true,'isolated'=>true,'database'=>$stage['database']]);exit;}
$public=realpath($root.'/public');$asset=realpath($public.'/'.rawurldecode($path));
if ($asset&&str_starts_with($asset,$public.DIRECTORY_SEPARATOR)&&preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$asset)) return false;
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['SCRIPT_FILENAME']=$public.'/index.php';
$app=require $root.'/bootstrap/app.php';$app->useEnvironmentPath($stage['runtime'])->loadEnvironmentFrom('absent.env');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
config(['app.debug'=>false,'app.url'=>'http://'.$_SERVER['HTTP_HOST'],'view.compiled'=>$stage['runtime'].'/demo-views',
    'database.connections.mysql.timezone'=>'+00:00','roomtype-v2.staging_checkout'=>true,'roomtype-v2.production_manifest'=>null,'booking-demo.enabled'=>true,
    'filesystems.disks.public.root'=>$stage['runtime'].'/demo-uploads']);
foreach (['demo-views','demo-uploads'] as $dir) if (!is_dir($stage['runtime'].'/'.$dir))mkdir($stage['runtime'].'/'.$dir,0700,true);
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($stage['runtime'].'/absent-hot');
App\Support\RoomTypeV2Integration::acceptance();App\Support\BookingDemo::activate();
$request=Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
