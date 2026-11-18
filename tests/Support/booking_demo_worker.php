<?php

putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$argv[1]);require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->useEnvironmentPath(dirname(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')))->loadEnvironmentFrom('absent.env');$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.mysql.timezone'=>'+00:00','booking-demo.enabled'=>true]);
App\Support\RoomTypeV2Integration::acceptance();App\Support\BookingDemo::activate();
try{App\Support\BookingDemo::connection();if(($argv[4]??'')==='success'){app(App\Services\DemoBookingPaymentService::class)->simulate((int)$argv[3],(int)$argv[2],'success');echo "settled\n";}else{app(App\Services\BookingServiceV2::class)->create('mysql',(int)$argv[2],json_decode($argv[3],true,512,JSON_THROW_ON_ERROR));echo "created\n";}}catch(Illuminate\Validation\ValidationException $e){echo "stock rejected\n";exit(3);}
