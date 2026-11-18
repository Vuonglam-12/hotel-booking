<?php

$file=getenv('HOTEL_PRICING_ADMIN_STAGING');
$stage=$file&&is_file($file)?json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR):null;
if(!$stage||($stage['synthetic_only']??false)!==true||($stage['pricing_admin_crud']??false)!==true)throw new RuntimeException('Owned isolated pricing stage required.');
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit;}
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']);putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$stage['database']);
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$root=dirname(__DIR__,2);$public=realpath($root.'/public');$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$asset=realpath($public.'/'.rawurldecode($path));
if($asset&&str_starts_with($asset,$public.DIRECTORY_SEPARATOR)&&is_file($asset)&&preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$asset))return false;
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)&&!preg_match('~^/api/admin/hotels/1/room-types/1/rates(?:/\d+)?$~',$path)){
    http_response_code(405);header('Content-Type: application/json');echo '{"message":"Pricing test stage permits only rate mutations."}';exit;
}
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['SCRIPT_FILENAME']=$public.'/index.php';
$app=require $root.'/bootstrap/app.php';$request=Illuminate\Http\Request::capture();$app->instance('request',$request);
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
config(['app.debug'=>false,'app.url'=>'http://'.$_SERVER['HTTP_HOST'],'view.compiled'=>$stage['runtime'].'/views',
    'filesystems.disks.public.root'=>$stage['runtime'].'/uploads','session.driver'=>'array','cache.default'=>'array','mail.default'=>'array',
    'roomtype-v2.prepared_routes'=>false,'roomtype-v2.integration_complete'=>false,'roomtype-v2.final_integration'=>false,
    'roomtype-v2.production_manifest'=>null,'services.sepay.webhook.enabled'=>false]);
$db=App\Support\RoomTypeV2TestDatabase::connection('mysql');
$db->beforeExecuting(function(string $sql){
    if(preg_match('/^\s*(select|show|explain)\b/i',$sql))return;
    if(preg_match('/^\s*(insert(?:\s+ignore)?\s+into|update|delete\s+from)\s+`?(hotel_room_rate|personal_access_tokens)`?(?=\s|\()/i',$sql))return;
    throw new RuntimeException('Pricing stage refused an unrelated database write.');
});
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($stage['runtime'].'/absent-hot');
Illuminate\Support\Facades\Http::preventStrayRequests();Illuminate\Support\Facades\Mail::fake();
if($path==='/__pricing/health'){
    header('Content-Type: application/json');echo json_encode(['isolated'=>true,'database'=>$db->getDatabaseName(),'port'=>$db->getConfig('port'),'synthetic_only'=>true]);exit;
}
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
