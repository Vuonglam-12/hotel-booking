<?php

if (!isset($argv[1]) || !realpath($argv[1])) throw new RuntimeException('Owned test settings required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($argv[1]));
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Tests\Support\RoomTypeV2AcceptanceFixture::initialize();
$password=bin2hex(random_bytes(18));
$db->table('customer')->whereIn('id',[9001,9004])->update(['password_hash'=>Illuminate\Support\Facades\Hash::make($password)]);
$runtime=dirname(realpath($argv[1]));
$checkIn=Illuminate\Support\Carbon::today(config('app.timezone'))->addDays(3);
$descriptor=['version'=>1,'database'=>$db->getDatabaseName(),'runtime'=>$runtime,'settings_file'=>realpath($argv[1]),
    'customer_email'=>'fixture-9001@example.test','admin_email'=>'fixture-9004@example.test','password'=>$password,'synthetic_only'=>true,
    'check_in'=>$checkIn->toDateString(),'check_out'=>$checkIn->copy()->addDays(2)->toDateString()];
file_put_contents($runtime.'/staging-session.json',json_encode($descriptor,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['staging_descriptor'=>$runtime.'/staging-session.json','database'=>$db->getDatabaseName(),'production_access'=>false]);
