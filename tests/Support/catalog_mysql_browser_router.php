<?php

require dirname(__DIR__,2).'/vendor/autoload.php';

use Tests\Support\CatalogMysqlEnvironment as Environment;

if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))throw new RuntimeException('Loopback only.');
$stage=Environment::settings();$identity=Environment::verify($stage);$runtime=$stage['runtime'];
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/__catalog/health'){
    header('Content-Type: application/json');echo json_encode(['synthetic_only'=>true,'driver'=>'mysql','identity'=>$identity]);exit;
}
if($path==='/catalog-test.css'){header('Content-Type: text/css');readfile($runtime.'/browser.css');exit;}
if(str_starts_with($path,'/storage/')){
    $storage=realpath($runtime.'/public/storage');$uploads=realpath($runtime.'/uploads');
    $asset=realpath($runtime.'/public/'.rawurldecode(ltrim($path,'/')));
    if($storage!==$uploads||!$asset||!str_starts_with($asset,$uploads.DIRECTORY_SEPARATOR)||!preg_match('/\.(png|jpe?g|webp)$/i',$asset)){http_response_code(404);exit;}
    // Serve via the actual test document root/public-storage junction, not an API or a mocked response.
    return false;
}
$public=realpath(dirname(__DIR__,2).'/public');$asset=realpath($public.'/'.rawurldecode(ltrim($path,'/')));
if($asset&&str_starts_with($asset,$public.DIRECTORY_SEPARATOR)&&preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i',$asset)){
    header('Content-Type: '.mime_content_type($asset));readfile($asset);exit;
}
$allowed=preg_match('~^/(?:cam-nang|trai-nghiem)(?:/[a-z0-9-]+)?$|^/blog(?:/[0-9]+)?$|^/admin/(?:guides|experiences)$~D',$path)
    ||preg_match('~^/api/(?:guides|experiences)(?:/[a-z0-9-]+)?$~D',$path)
    ||preg_match('~^/api/admin/(?:me|guides(?:/[a-z0-9/-]+)?|attractions(?:/[0-9/]+(?:toggle|restore)?)?|locations(?:/[0-9]+/attractions)?|experiences/meta)$~D',$path);
if(!$allowed){http_response_code(403);echo 'Outside the two-module test boundary.';exit;}
$app=Environment::application();$app->make('config')->set('app.url','http://'.$_SERVER['HTTP_HOST']);
$app->instance(\Illuminate\Foundation\Vite::class,new class{
    public function __invoke(...$args):\Illuminate\Support\HtmlString{return new \Illuminate\Support\HtmlString('<link rel="stylesheet" href="/catalog-test.css">');}
});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();$response=$kernel->handle($request);
\Illuminate\Support\Facades\Mail::assertNothingSent();\Illuminate\Support\Facades\Notification::assertNothingSent();\Illuminate\Support\Facades\Http::assertNothingSent();
if(str_contains($response->headers->get('Content-Type',''),'text/html'))$response->setContent(preg_replace('~<script\b[^>]*\bsrc="https?://[^>]*></script>|<link\b[^>]*\bhref="https?://[^>]*>~i','',$response->getContent()));
$response->send();$kernel->terminate($request,$response);
file_put_contents($runtime.'/http-boundary.jsonl',json_encode(['method'=>$request->method(),'path'=>$path,'status'=>$response->getStatusCode(),
    'db'=>$identity['db'],'server_uuid'=>$identity['uuid'],'mail_sent'=>0,'notifications_sent'=>0,'http_outbound'=>0])."\n",FILE_APPEND);
