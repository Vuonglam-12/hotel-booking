<?php
// Targeted additive deployment. Never runs a general migrate/reset or rewrites booking history.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=App\Support\LocalBookingDemo::connection();
$rate=$db->table('hotel_room_rate')->where('id',1)->first();
$base=$db->table('hotel_room_type')->where('hotel_id',61)->where('room_type_id',64)->value('base_price');
if(!$rate||(int)$rate->hotel_id!==61||(int)$rate->room_type_id!==64||$rate->kind!=='day'
    ||$rate->start_date!=='2026-10-31'||$rate->end_date!=='2026-10-31'||$rate->price!=='100000.00'||$base!=='6500000.00'){
    throw new RuntimeException('Current data differs from the user-confirmed evidence. No data changed.');
}
$tables=['hotel_room_rate','hotel_room_type','room_type','room','booking','booking_room','booking_nightly_rate','payment','invoice','invoice_item'];
$columns=[];$before=[];
foreach($tables as $table){$columns[$table]=$db->getSchemaBuilder()->getColumnListing($table);$before[$table]=hash('sha256',$db->table($table)->select($columns[$table])->orderBy('id')->get()->toJson());}
$directory=storage_path('app/private/pricing-surcharge-conversion');
if(!is_dir($directory))mkdir($directory,0700,true);
$audit=['confirmed_rule'=>(array)$rate,'base_price'=>$base,'before'=>$before,'columns'=>$columns,'confirmed_by'=>'User explicitly identified the 100000 VND entry as surcharge.'];
$file=$directory.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
file_put_contents($file,json_encode($audit,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
if(!$db->getSchemaBuilder()->hasColumn('hotel_room_rate','amount_mode')){
    (require base_path('database/migrations/2026_11_01_000001_add_explicit_room_surcharges.php'))->up();
}
$db->transaction(function()use($db){
    $db->table('hotel')->where('id',61)->lockForUpdate()->first();
    $row=$db->table('hotel_room_rate')->where('id',1)->lockForUpdate()->first();
    if($row->price!=='100000.00'||(int)$row->hotel_id!==61||(int)$row->room_type_id!==64)throw new RuntimeException('Rule changed concurrently.');
    $db->table('hotel_room_rate')->where('id',1)->update(['amount_mode'=>'surcharge']);
});
// Register only this migration, so normal subsequent migrations will not re-add its columns.
$migration='2026_11_01_000001_add_explicit_room_surcharges';
if($db->getSchemaBuilder()->hasTable('migrations')&&!$db->table('migrations')->where('migration',$migration)->exists()){
    $db->table('migrations')->insert(['migration'=>$migration,'batch'=>1+(int)$db->table('migrations')->max('batch')]);
}
$unchanged=[];foreach($tables as $table)$unchanged[$table]=hash_equals($before[$table],hash('sha256',$db->table($table)->select($columns[$table])->orderBy('id')->get()->toJson()));
$audit['unchanged']=$unchanged;$audit['converted_rule_id']=1;$audit['completed']=!in_array(false,$unchanged,true);
file_put_contents($file,json_encode($audit,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
if(!$audit['completed'])throw new RuntimeException('Unrelated data changed during deployment; inspect private audit.');
echo json_encode(['converted_rule_id'=>1,'base_price'=>$base,'surcharge'=>'100000.00','nightly_price'=>'6600000.00','unchanged'=>$unchanged],JSON_PRETTY_PRINT),PHP_EOL;
