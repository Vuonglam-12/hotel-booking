<?php
// Read only. Never print connection settings or customer/booking content.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = storage_path('app/private/room-type-enrichment-20261031');
if(is_file($path.'/before.json')) throw new RuntimeException('The original pre-import audit already exists; preserve it for comparison.');
$db = Illuminate\Support\Facades\DB::connection();
$db->statement('SET SESSION TRANSACTION READ ONLY');
$db->beginTransaction();
$result = ['catalog_enabled'=>App\Services\HotelRoomTypeService::enabled(), 'columns'=>[], 'hotels'=>[]];
foreach (['room_type','hotel_room_type','hotel_room_type_image','room','hotel_room_rate'] as $table) {
    $result['columns'][$table] = $db->select('SHOW COLUMNS FROM `'.$table.'`');
}
foreach ($db->table('hotel')->orderBy('id')->get(['id','name','address','location_id','status']) as $hotel) {
    $hotel->physical_rooms = $db->table('room')->where('hotel_id',$hotel->id)->count();
    $hotel->managed_rooms = $db->table('room')->where('hotel_id',$hotel->id)->where('catalog_retired',false)->count();
    $hotel->types = $db->table('hotel_room_type as t')->where('hotel_id',$hotel->id)->orderBy('t.id')->get()
        ->map(function($type) use($db) {
            $type->physical_rooms = $db->table('room')->where('hotel_id',$type->hotel_id)->where('room_type_id',$type->room_type_id)->count();
            $type->images = $db->table('hotel_room_type_image')->where('hotel_room_type_id',$type->id)->get(['id','image_url']);
            return $type;
        });
    $result['hotels'][]=$hotel;
}
$db->rollBack();
if(!is_dir($path)) mkdir($path,0700,true);
file_put_contents($path.'/before.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode(['catalog_enabled'=>$result['catalog_enabled'],'columns'=>$result['columns'],'hotels'=>array_map(fn($h)=>['id'=>$h->id,'name'=>$h->name,'address'=>$h->address,'status'=>$h->status,'rooms'=>$h->physical_rooms,'managed'=>$h->managed_rooms,'types'=>$h->types->map(fn($t)=>['id'=>$t->room_type_id,'name'=>$t->name,'status'=>$t->status,'capacity'=>$t->capacity,'bed'=>$t->bed_type,'rooms'=>$t->physical_rooms])->all()],$result['hotels'])],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
