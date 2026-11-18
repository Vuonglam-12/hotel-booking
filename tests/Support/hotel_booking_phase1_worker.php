<?php

putenv('HOLIDAYVIET_TEST_DATABASE=' . ($argv[1] ?? ''));
require __DIR__ . '/booking_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$job=$argv[2]??'';$directory=realpath($argv[3]??'');$root=realpath(storage_path('framework/testing'));
$payload=json_decode($argv[4]??'',true,512,JSON_THROW_ON_ERROR);
if(!$directory||!$root||!str_starts_with($directory,$root.DIRECTORY_SEPARATOR.'phase1-gate-')
    || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name!==getenv('HOLIDAYVIET_TEST_DATABASE'))throw new RuntimeException('Unsafe test worker.');
Illuminate\Support\Facades\Mail::fake();
file_put_contents($directory.'/'.$job.'-connected',(string)Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$wait=static function(string $path):void{$end=microtime(true)+15;while(!is_file($path)){if(microtime(true)>$end)throw new RuntimeException('Worker barrier timeout');usleep(20000);clearstatcache();}};
try {
    if(str_starts_with($job,'booking')) {
        if($payload['pause_booking']??false){$paused=false;Illuminate\Support\Facades\DB::listen(static function($query)use($job,$directory,$wait,&$paused){
            $sql=strtolower($query->sql);if(!$paused&&str_contains($sql,'from '.chr(96).'hotel'.chr(96))&&str_contains($sql,'for update')){$paused=true;file_put_contents($directory.'/'.$job.'-locked','1');$wait($directory.'/release-'.$job);}
        });}
        $booking=$app->make(App\Services\BookingService::class)->create($payload['customer_id'],$payload['data']);$result=['ok'=>true,'booking_id'=>$booking->id];
    } elseif($job==='expire') {
        if($payload['pause_scan']??false){$paused=false;Illuminate\Support\Facades\DB::listen(static function($query)use($directory,$wait,&$paused){
            $sql=strtolower($query->sql);if(!$paused&&str_starts_with($sql,'select')&&str_contains($sql,'from '.chr(96).'booking'.chr(96))&&str_contains($sql,'expires_at')&&!str_contains($sql,'for update')){$paused=true;file_put_contents($directory.'/expire-scanned','1');$wait($directory.'/release-expire');}
        });}
        $app->make(Illuminate\Contracts\Console\Kernel::class)->call('bookings:cancel-expired');$result=['ok'=>true];
    } else throw new RuntimeException('Unknown job');
}catch(Throwable $e){$result=['ok'=>false,'exception'=>get_class($e),'message'=>$e->getMessage()];}
finally{while(Illuminate\Support\Facades\DB::transactionLevel()>0)Illuminate\Support\Facades\DB::rollBack();}
file_put_contents($directory.'/'.$job.'-result.json',json_encode($result,JSON_THROW_ON_ERROR));
