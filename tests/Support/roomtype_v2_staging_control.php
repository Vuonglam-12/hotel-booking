<?php

if (!isset($argv[1],$argv[2])) throw new RuntimeException('Owned staging descriptor and allowlisted action required.');
$stage=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if (($stage['synthetic_only']??false)!==true) throw new RuntimeException('Synthetic staging only.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']); putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$stage['database']);
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.mysql.timezone'=>'+00:00']);
App\Support\RoomTypeV2Integration::acceptance();
if ($argv[2]==='deliver') $result=Illuminate\Support\Facades\Artisan::call('bookings:v2-deliver-prepared');
elseif ($argv[2]==='expire' && isset($argv[3]) && ctype_digit($argv[3])) {
    $db=App\Support\RoomTypeV2TestDatabase::connection('mysql');
    $b=$db->table('booking')->where('id',(int)$argv[3])->first(); if (!$b) throw new RuntimeException('Unknown synthetic Booking.');
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse($b->expires_at)->addSecond());
    $result=Illuminate\Support\Facades\Artisan::call('bookings:cancel-expired');
} elseif ($argv[2]==='scheduler-tick') {
    // Bounded cron rehearsal in this guarded process. Never spawn default artisan with production .env.
    $result=Illuminate\Support\Facades\Artisan::call('bookings:cancel-expired');
    $result=max($result,Illuminate\Support\Facades\Artisan::call('bookings:v2-deliver-prepared'));
} else throw new RuntimeException('Unsupported staging control action.');
echo json_encode(['exit_code'=>$result,'synthetic_only'=>true,'database'=>$stage['database']]);
exit($result);
