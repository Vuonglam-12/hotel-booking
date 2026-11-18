<?php

if(empty($argv[1]))throw new RuntimeException('Owned test settings required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($argv[1]));
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Tests\Support\HotelRoomTypeFixture::initialize();Tests\Support\HotelRoomTypeFixture::migrate();
$runtime=dirname(realpath($argv[1])).'/scoped-browser';if(!is_dir($runtime))mkdir($runtime,0700);
$token=App\Models\Customer::findOrFail(1)->createToken('admin_token',['admin'])->plainTextToken;
$stage=['synthetic_only'=>true,'scoped_catalog'=>true,'settings_file'=>realpath($argv[1]),'database'=>$db->getDatabaseName(),'runtime'=>$runtime,'admin_token'=>$token];
file_put_contents($runtime.'/session.json',json_encode($stage,JSON_PRETTY_PRINT));
$image=imagecreatetruecolor(80,60);imagefill($image,0,0,imagecolorallocate($image,50,100,180));imagejpeg($image,$runtime.'/sample.jpg');imagedestroy($image);
echo json_encode(['descriptor'=>$runtime.'/session.json','synthetic_only'=>true,'checkout_enabled'=>false]),PHP_EOL;
