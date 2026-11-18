<?php

if(empty($argv[1]))throw new RuntimeException('Owned isolated test settings required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($argv[1]));
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Tests\Support\HotelRoomTypeFixture::initialize();Tests\Support\HotelRoomTypeFixture::migrate();
foreach(['2026_10_12_000005_replace_booking_status_unique.php','2026_10_26_000001_create_local_booking_demo_audit.php',
    '2026_10_31_000004_create_booking_demo_reconciliation.php','2026_10_31_000005_create_booking_demo_payment_notices.php']as$file){(require base_path('database/migrations/'.$file))->up();}
$db->table('hotel')->where('id',1)->update(['name'=>'Khách sạn kiểm thử giá']);
$db->table('hotel_room_type')->where('hotel_id',1)->where('room_type_id',1)->update(['name'=>'Deluxe — dữ liệu kiểm thử','base_price'=>'6500000.00']);
$db->table('room')->where('hotel_id',1)->where('room_type_id',1)->update(['price'=>'6500000.00']);
$runtime=dirname(realpath($argv[1])).'/pricing-admin-browser';
foreach([$runtime,$runtime.'/views',$runtime.'/uploads']as$directory)if(!is_dir($directory))mkdir($directory,0700,true);
$credential=App\Models\Customer::findOrFail(1)->createToken('admin_token',['admin'])->plainTextToken;
$descriptor=['synthetic_only'=>true,'pricing_admin_crud'=>true,'settings_file'=>realpath($argv[1]),'database'=>$db->getDatabaseName(),
    'runtime'=>$runtime,'admin_token'=>$credential,'hotel_id'=>1,'room_type_id'=>1,'base_price'=>'6500000.00'];
file_put_contents($runtime.'/session.json',json_encode($descriptor,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode(['descriptor'=>$runtime.'/session.json','synthetic_only'=>true,'main_database_access'=>false]),PHP_EOL;
