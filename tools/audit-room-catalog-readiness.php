<?php
// Read-only readiness audit. No customer details, credentials, or booking content are exported.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();$db->statement('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
$hotels=$db->table('hotel')->orderBy('id')->get(['id','name','status','star_rating']);
$out=[];
foreach($hotels as $h){
    $types=$db->table('hotel_room_type')->where('hotel_id',$h->id)->orderBy('id')->get(['id','room_type_id','name','status','base_price','capacity','bed_type','amenities','area_min_m2','area_max_m2']);
    foreach($types as $t){$t->physical_rooms=$db->table('room')->where('hotel_id',$h->id)->where('room_type_id',$t->room_type_id)->count();$t->amenities=json_decode($t->amenities,true)??[];}
    $out[]=[...(array)$h,'types'=>$types->all(),'hotel_amenities'=>$db->table('hotel_amenity')->where('hotel_id',$h->id)->get(['id','amenity','icon','catalog_identity'])->all()];
}
$catalog=$db->table('hotel_amenity')->whereNull('hotel_id')->get(['id','amenity','icon','catalog_identity']);
$db->rollBack();
$dir=storage_path('app/private/room-catalog-readiness-20261031');if(!is_dir($dir))mkdir($dir,0700,true);
$file=$dir.(in_array('--current',$argv,true)?'/audit-current.json':'/audit.json');
if(file_exists($file) && basename($file)==='audit.json')throw new RuntimeException('Preserve the pre-update baseline; use --current for a fresh read-only audit.');
file_put_contents($file,json_encode(['checked_on'=>in_array('--current',$argv,true)?'2026-11-01':'2026-10-31','hotels'=>$out,'amenity_catalog'=>$catalog],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$types=collect($out)->flatMap(fn($h)=>$h['types'])->where('status','!=','archived');
echo json_encode(['hotels'=>count($out),'types'=>$types->count(),'missing_price'=>$types->whereNull('base_price')->count(),'missing_capacity'=>$types->whereNull('capacity')->count(),
    'empty_room_amenities'=>$types->filter(fn($t)=>!$t->amenities)->count(),'inactive'=>$types->where('status','inactive')->count(),'without_physical_rooms'=>$types->where('physical_rooms',0)->count(),
    'archived_kept'=>collect($out)->flatMap(fn($h)=>$h['types'])->where('status','archived')->count(),'hotel_amenity_assignments'=>collect($out)->sum(fn($h)=>count($h['hotel_amenities'])),'amenity_catalog'=>$catalog->count()]),PHP_EOL;
