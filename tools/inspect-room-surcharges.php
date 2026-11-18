<?php
// Read only: emit business price fields, never connection settings or credentials.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
$db->beforeExecuting(function($sql){if(!preg_match('/^\s*(select|show|explain)\b/i',$sql))throw new RuntimeException('Read only inspection.');});
$result=['rates'=>$db->table('hotel_room_rate')->orderBy('id')->get(),
    'scoped_base'=>$db->table('hotel_room_type')->where('hotel_id',61)->where('room_type_id',64)->get(['hotel_id','room_type_id','name','base_price']),
    'shared_base'=>$db->table('room_type')->where('id',64)->get(['id','name','base_price']),
    'physical_prices'=>$db->table('room')->where('hotel_id',61)->where('room_type_id',64)->get(['id','price']),
    'booking_snapshot_count'=>$db->table('booking_nightly_rate')->count(),
    'surcharge_schema'=>Illuminate\Support\Facades\Schema::hasColumn('hotel_room_rate','amount_mode')];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
