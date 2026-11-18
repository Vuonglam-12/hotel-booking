<?php
// Concurrent worker: only targets an allowlisted schema on the launcher-owned MySQL server.
putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.($argv[1]??''));require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['booking-demo.local_enabled'=>true,'mail.default'=>'array','logging.channels.stderr.level'=>'critical']);
App\Support\LocalBookingDemo::connection();Illuminate\Support\Facades\Http::preventStrayRequests();Illuminate\Support\Facades\Mail::fake();
try{$service=app(App\Services\LocalBookingDemoService::class);$data=json_decode($argv[4],true,512,JSON_THROW_ON_ERROR);
 $result=$argv[2]==='create'?$service->create((int)$argv[3],$data):$service->simulate($data['booking_id'],(int)$argv[3],'success',$data['payment_id'],true);echo json_encode($result).PHP_EOL;}
catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){fwrite(STDERR,'Rejected: '.$e->getMessage().PHP_EOL);exit(3);}
catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
