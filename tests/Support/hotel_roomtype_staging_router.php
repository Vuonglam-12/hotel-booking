<?php

$file=getenv('AUREL_HOTEL_ROOMTYPE_STAGING');
$stage=$file&&is_file($file)?json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR):null;
if(!$stage||($stage['synthetic_only']??false)!==true||($stage['scoped_catalog']??false)!==true)throw new RuntimeException('Owned synthetic scoped staging required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']);putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$stage['database']);
require __DIR__.'/roomtype_v2_test_bootstrap.php';
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$public=realpath(dirname(__DIR__,2).'/public');
$asset=realpath($public.'/'.rawurldecode($path));
if($asset&&str_starts_with($asset,$public.DIRECTORY_SEPARATOR)&&preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$asset)&&!str_starts_with($path,'/storage/'))return false;
if(str_starts_with($path,'/storage/')){
    $root=realpath($stage['runtime'].'/uploads');$asset=realpath($root.'/'.rawurldecode(substr($path,9)));
    if($asset&&str_starts_with($asset,$root.DIRECTORY_SEPARATOR)&&preg_match('/\.(png|jpe?g|webp)$/i',$asset)){header('Content-Type: '.mime_content_type($asset));readfile($asset);exit;}
    http_response_code(404);exit;
}
if($path==='/__scoped/health'){header('Content-Type: application/json');echo '{"synthetic_only":true,"checkout_enabled":false}';exit;}
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['SCRIPT_FILENAME']=$public.'/index.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->usePublicPath($stage['runtime'].'/public');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
config(['app.debug'=>false,'app.url'=>'http://'.$_SERVER['HTTP_HOST'],'view.compiled'=>$stage['runtime'].'/views',
    'filesystems.disks.public.root'=>$stage['runtime'].'/uploads','roomtype-v2.prepared_routes'=>false,'roomtype-v2.production_manifest'=>null]);
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($stage['runtime'].'/absent-hot');
App\Support\RoomTypeV2TestDatabase::connection('mysql');
$request=Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
