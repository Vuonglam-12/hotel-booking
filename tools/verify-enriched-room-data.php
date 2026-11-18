<?php
// Final read-only audit over the original pre-task inventory and the complete manifest.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
$work=storage_path('app/private/room-type-enrichment-20261031');
$before=json_decode(file_get_contents($work.'/before.json'),true,512,JSON_THROW_ON_ERROR);
$manifest=json_decode(file_get_contents(base_path('database/data/verified-room-types-2026-10-31.json')),true,512,JSON_THROW_ON_ERROR);
$expectedStatus='inactive';$allowedRates=[];
$revisionPath=storage_path('app/private/room-catalog-update-20261031/apply-report.json');
if(is_file($revisionPath)){
    $revision=json_decode(file_get_contents($revisionPath),true,512,JSON_THROW_ON_ERROR);
    $configPath=base_path('database/data/demo-room-catalog-2026-10-31.json');$ratePath=base_path('database/data/verified-room-rate-references-2026-11-01.json');
    if(($revision['checks']['all_passed']??false) && hash_equals($revision['manifest_sha256'],hash('sha256',hash_file('sha256',$configPath).hash_file('sha256',$ratePath)))){
        $expectedStatus='active';
        foreach(json_decode(file_get_contents($ratePath),true,512,JSON_THROW_ON_ERROR)['records'] as $r)$allowedRates[$r['hotel_id'].':'.$r['room_type_id']]=$r;
    }
}
$db->statement('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
$oldIds=[];foreach($before['hotels'] as $hotel){
    if($db->table('room')->where('hotel_id',$hotel['id'])->count()!==$hotel['physical_rooms'])throw new RuntimeException('Physical inventory changed.');
    foreach($hotel['types'] as $type)$oldIds[]=$type['id'];
}
$new=$db->table('hotel_room_type')->whereNotIn('id',$oldIds)->get();
$newIds=$new->pluck('room_type_id');
$expected=count(array_filter($manifest['records'],fn($r)=>!$r['existing_room_type_ids']));
if($new->count()!==$expected || !$new->every(fn($r)=>$r->status===$expectedStatus && $r->base_price===null))throw new RuntimeException('New categories have an unexpected status or fixed base price.');
if($db->table('room')->whereIn('room_type_id',$newIds)->exists())throw new RuntimeException('New categories have fabricated physical inventory.');
$referenceRates=$db->table('hotel_room_rate')->whereIn('room_type_id',$newIds)->get();
foreach($referenceRates as $rate){$r=$allowedRates[$rate->hotel_id.':'.$rate->room_type_id]??null;
    if(!$r || $rate->kind!=='season' || $rate->start_date!==$r['demo_allocation']['start_date'] || $rate->end_date!==$r['demo_allocation']['end_date'] || $rate->price!==$r['nightly_average_vnd'] || $rate->label!==$r['demo_allocation']['label'])throw new RuntimeException('New category has an unverified or extrapolated rate.');
}
$imageMap=[];foreach($manifest['records'] as $row)foreach($row['images'] as $img)$imageMap[$img['local_path']]=$img;
$attached=$db->table('hotel_room_type_image')->where('image_url','like','storage/accommodations/verified-room-types/20261031/%')->get();
foreach($attached as $image){
    $source=$imageMap[$image->image_url]??null;
    if(!$source || !hash_equals($source['sha256'],hash_file('sha256',public_path($image->image_url))) || !@getimagesize(public_path($image->image_url)))throw new RuntimeException('Attached image lacks matching evidence.');
}
$evidence=$db->table('hotel_room_type_evidence')->count();
$sourceRows=0;
foreach($manifest['records'] as $row){
    $proofs=$db->table('hotel_room_type_evidence as e')->join('hotel_room_type as c','c.id','=','e.hotel_room_type_id')
        ->where('c.hotel_id',$row['hotel_id'])->where('e.source_key',$row['source_key'])->where('e.checked_on','2026-10-31')->get(['e.source_url']);
    if($proofs->isEmpty() || $proofs->contains(fn($p)=>$p->source_url!==$row['source_url']))throw new RuntimeException('Missing or mismatched room-type source.');
    $sourceRows+=$proofs->count();
}
$result=['hotels'=>$db->table('hotel')->count(),'catalog_before'=>count($oldIds),'non_archived_before'=>array_sum(array_map(fn($h)=>count(array_filter($h['types'],fn($t)=>$t['status']!=='archived')),$before['hotels'])),
    'non_archived_after'=>$db->table('hotel_room_type')->where('status','!=','archived')->count(),'new_types'=>$new->count(),
    'expected_new_category_status'=>$expectedStatus,'new_types_without_fixed_base_prices_or_physical_inventory'=>true,'dated_reference_rates_for_new_types_verified'=>$referenceRates->count(),'physical_rooms'=>$db->table('room')->count(),
    'evidence_rows'=>$evidence,'source_records_verified'=>$sourceRows,'new_images_verified'=>$attached->count(),
    'existing_hotels_only'=>true,'checked_on'=>'2026-10-31'];
$db->rollBack();
file_put_contents($work.'/final-verification.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result),PHP_EOL;
