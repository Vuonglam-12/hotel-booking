<?php
// Read-only catalog/schema audit. No customer data or credentials.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();$db->statement('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
$rows=$db->table('hotel_room_type as t')->join('hotel as h','h.id','=','t.hotel_id')->where('t.status','!=','archived')->orderBy('t.hotel_id')->orderBy('t.id')->get(['t.id','t.hotel_id','h.name as hotel','t.room_type_id','t.name','t.base_price','t.capacity','t.status']);
foreach($rows as $r){$r->total_rooms=$db->table('room')->where('hotel_id',$r->hotel_id)->where('room_type_id',$r->room_type_id)->where('catalog_retired',false)->count();}
$columns=$db->select('SHOW COLUMNS FROM room');$rates=$db->table('hotel_room_rate')->orderBy('id')->get();
$inventory=['physical'=>$db->table('room')->count(),'managed'=>$db->table('room')->where('catalog_retired',false)->count(),'without_price'=>$db->table('room')->whereNull('price')->count(),'pending_without_price'=>$db->table('room')->whereNull('price')->where('status','maintenance')->count()];$db->rollBack();
$dir=storage_path('app/private/room-admin-update-20261102');if(!is_dir($dir))mkdir($dir,0700,true);
$auditOptions=getopt('',['checked-on:']);$checkedOn=$auditOptions['checked-on']??'2026-11-02';if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$checkedOn))throw new RuntimeException('Invalid audit date.');
$out=['checked_on'=>$checkedOn,'room_columns'=>$columns,'types'=>$rows,'rates'=>$rates,'inventory'=>$inventory];
$file=$dir.'/audit-before.json';if(file_exists($file))$file=$dir.'/audit-current.json';file_put_contents($file,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['types'=>$rows->count(),'missing_price'=>$rows->whereNull('base_price')->count(),'zero_inventory'=>$rows->where('total_rooms',0)->count(),'inventory'=>$inventory,'room_price_column'=>array_values(array_filter($columns,fn($c)=>$c->Field==='price')),'rates'=>$rates->count()]),PHP_EOL;
