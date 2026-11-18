<?php
// Loopback-only HTTP integration fixture. Owned MySQL + fake providers, every request rolled back.
declare(strict_types=1);
$root=dirname(__DIR__,2);
require $root.'/tests/Support/itinerary_test_bootstrap.php';
$settings=Tests\Support\ItineraryFixture::settings();
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)
    || !hash_equals($settings['owner_token'],$_SERVER['HTTP_X_TEST_OWNER']??'')
    || ($_SERVER['REQUEST_METHOD']??'')!=='POST' || parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)!=='/api/chat/fixture/message') {
    http_response_code(404);exit;
}
$app=Tests\Support\ItineraryFixture::application();Tests\Support\ItineraryFixture::guard();
config(['services.groq.api_key'=>'synthetic-provider','services.geoapify.api_key'=>'synthetic-map','services.geoapify.external_photos'=>false]);
Illuminate\Support\Facades\Mail::fake();Illuminate\Support\Facades\Notification::fake();
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(function($request){
    usleep(120000); // Simulated provider latency; never used by the product to invent progress.
    $url=$request->url();
    if(str_contains($url,'/geocode/'))return Illuminate\Support\Facades\Http::response(['features'=>[['properties'=>['place_id'=>'http-owned-region','name'=>'Hồ Chí Minh','country_code'=>'vn','result_type'=>'city','category'=>'administrative','lon'=>106.7,'lat'=>10.77]]]]);
    if(str_contains($url,'/place-details'))return Illuminate\Support\Facades\Http::response(['features'=>[['properties'=>['place_id'=>'http-owned-region'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]]]]]]]);
    if(str_contains($url,'/v2/places'))return Illuminate\Support\Facades\Http::response(['features'=>array_map(fn($i)=>['properties'=>['place_id'=>'http-place-'.$i,'name'=>'HTTP fixture POI '.$i,'country_code'=>'vn','categories'=>['tourism.sights']],'geometry'=>['coordinates'=>[106.7+$i/1000,10.77]]],range(0,7))]);
    if(str_contains($url,'/routematrix')){$n=count($request['sources']);return Illuminate\Support\Facades\Http::response(['sources_to_targets'=>array_fill(0,$n,array_fill(0,$n,['time'=>120,'distance'=>300]))]);}
    if(str_contains($url,'api.groq.com')) {
        $system=$request['messages'][0]['content'];$payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);
        if(str_contains($system,'AUREL:UNDERSTAND')) {
            $message=$payload['message'];$intent=str_contains($message,'toán')?'out_of_scope':'plan';
            $patch=$intent==='out_of_scope'?[]:['destination_name'=>'SG','adults'=>2];
            if(str_contains($message,'2 ngày'))$patch['duration_days']=2;
            if(str_contains($message,'5 triệu'))$patch['budget']=5000000;
            $content=['intent'=>$intent,'patch'=>$patch,'action_quote'=>$intent==='plan'?$message:null];
        }else{
            $days=[];$index=0;foreach($payload['dates'] as $date)$days[]=['date'=>$date,'items'=>[
                ['candidate_id'=>$index++,'duration_minutes'=>60,'description'=>'Điểm tham quan kiểm thử HTTP.'],
                ['candidate_id'=>$index++,'duration_minutes'=>60,'description'=>'Điểm tiếp theo kiểm thử HTTP.']]];
            $content=['is_valid'=>true,'title'=>'HTTP streaming fixture','days'=>$days];
        }
        return Illuminate\Support\Facades\Http::response(['choices'=>[['message'=>['content'=>json_encode($content,JSON_UNESCAPED_UNICODE)]]]]);
    }
    throw new RuntimeException('Unexpected integration provider.');
});
$counts=fn()=>['sessions'=>App\Models\ChatSession::count(),'messages'=>App\Models\ChatMessage::count(),'trips'=>App\Models\Itinerary::count()];
$before=$counts();Illuminate\Support\Facades\DB::beginTransaction();
try{
    Laravel\Sanctum\Sanctum::actingAs(App\Models\Customer::findOrFail(41));
    $session=App\Models\ChatSession::create(['customer_id'=>41,'session_token'=>Illuminate\Support\Str::random(32),'title'=>'HTTP progress fixture','context_json'=>[],'status'=>'active','started_at'=>now()]);
    $request=Illuminate\Http\Request::create('/api/chat/'.$session->id.'/message','POST',[],[],[],[
        'CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'text/event-stream','HTTP_IDEMPOTENCY_KEY'=>$_SERVER['HTTP_IDEMPOTENCY_KEY']??'http-progress-key',
        'HTTP_X_AUREL_REQUEST_ID'=>$_SERVER['HTTP_X_AUREL_REQUEST_ID']??'http-progress-attempt'],file_get_contents('php://input'));
    $kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$response=$kernel->handle($request);$response->headers->set('X-Test-Providers','fake');$response->send();$kernel->terminate($request,$response);
}finally{
    while(Illuminate\Support\Facades\DB::transactionLevel()>0)Illuminate\Support\Facades\DB::rollBack();
    file_put_contents($root.'/storage/app/private/itinerary-review-20261027/progress-http-db-proof.json',json_encode(['rollback_verified'=>$before===$counts(),'target'=>$settings['database'],'port'=>$settings['port'],'main_database_writes'=>0]));
}
