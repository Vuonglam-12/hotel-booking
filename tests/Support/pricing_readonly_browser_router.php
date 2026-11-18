<?php

// Loopback-only, read-only review of real catalog data. Never register this router in the website.
if(getenv('HOTEL_PRICING_READONLY_REVIEW')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){
    http_response_code(403);exit;
}
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    http_response_code(405);header('Content-Type: application/json');echo '{"message":"Read-only price review: writes are disabled."}';exit;
}
$root=dirname(__DIR__,2);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$public=realpath($root.'/public');
$asset=realpath($public.'/'.rawurldecode($path));
if($asset&&str_starts_with($asset,$public.DIRECTORY_SEPARATOR)&&is_file($asset)&&preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$asset))return false;
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$_SERVER['SCRIPT_NAME']='/index.php';$_SERVER['SCRIPT_FILENAME']=$public.'/index.php';
$request=Illuminate\Http\Request::capture();$app->instance('request',$request);
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
// Optional historical screenshot only; this router rejects every business write.
if(getenv('HOTEL_PRICING_SNAPSHOT_DATE')==='2026-10-31'){
    Carbon\Carbon::setTestNow('2026-10-31 12:00:00');
    Carbon\CarbonImmutable::setTestNow('2026-10-31 12:00:00');
}
config(['app.debug'=>false,'app.url'=>'http://'.$_SERVER['HTTP_HOST'],'session.driver'=>'array','cache.default'=>'array','mail.default'=>'array']);
$db=App\Support\LocalBookingDemo::connection();
$db->beforeExecuting(function(string $sql){if(!preg_match('/^\s*(select|show|explain)\b/i',$sql))throw new RuntimeException('Read-only catalog review refused a database write.');});
$app->make(Illuminate\Foundation\Vite::class)->useHotFile(storage_path('app/private/pricing-review-absent-hot'));
// An in-memory review identity permits only GETs; no password/token/account is created or copied.
$reviewer=new App\Models\Customer(['id'=>0,'name'=>'Kiểm tra giao diện','email'=>null,'role'=>'admin']);
$reviewer->withAccessToken(new Laravel\Sanctum\PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
$app['auth']->guard('admin')->setUser($reviewer);
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
