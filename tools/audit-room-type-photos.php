<?php
// Read only: category photos, including inactive categories, without reading customer data.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
$db->statement('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
$rows=$db->table('hotel_room_type as c')->join('hotel as h','h.id','=','c.hotel_id')->join('room_type as t','t.id','=','c.room_type_id')->where('c.status','!=','archived')->orderBy('c.hotel_id')->orderBy('c.id')->get(['c.*','h.name as hotel_name','t.name as type_name']);
$result=[];
foreach($rows as $r){
    $images=$db->table('hotel_room_type_image')->where('hotel_room_type_id',$r->id)->get(['id','image_url','is_primary','sort_order'])->all();
    $valid=array_filter($images,fn($i)=>App\Support\AccommodationImage::localUrl($i->image_url)!==null);
    $result[]=['id'=>$r->id,'hotel_id'=>$r->hotel_id,'hotel_name'=>$r->hotel_name,'room_type_id'=>$r->room_type_id,'name'=>$r->name??$r->type_name,'images_configured'=>(bool)$r->images_configured,'images'=>$images,'has_image'=>(bool)$valid,'evidence'=>$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$r->id)->get()->all()];
}
$db->rollBack();
$dir=storage_path('app/private/room-type-photo-completion-20261031');if(!is_dir($dir))mkdir($dir,0700,true);
file_put_contents($dir.'/audit-current.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
if(!file_exists($dir.'/audit-before.json'))copy($dir.'/audit-current.json',$dir.'/audit-before.json');
$hotels=[];foreach($result as $r){$id=$r['hotel_id'];$hotels[$id]??=['hotel'=>$r['hotel_name'],'total'=>0,'missing'=>[]];$hotels[$id]['total']++;if(!$r['has_image'])$hotels[$id]['missing'][]=$r['id'].':'.$r['name'];}
echo json_encode(['total'=>count($result),'with_photo'=>count(array_filter($result,fn($r)=>$r['has_image'])),'hotels'=>$hotels],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
