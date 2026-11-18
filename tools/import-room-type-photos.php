<?php
// Image-only supplement: restore-tested backup, isolated rehearsal, preservation checks and idempotency.
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);$work=$root.'/storage/app/private/room-type-photo-completion-20261031';
$options=getopt('', ['rehearse:','apply:','settings:']);
$mode=isset($options['rehearse'])?'rehearsal':'apply';
$proofPath=realpath($options['rehearse']??$options['apply']??'');
$backupRoot=realpath($root.'/storage/app/private/hotel-roomtype-deployment');
if(!$proofPath || !str_starts_with($proofPath,$backupRoot.DIRECTORY_SEPARATOR) || basename($proofPath)!=='verified-backup.json')throw new RuntimeException('A verified private backup is required.');
$proof=json_decode(file_get_contents($proofPath),true,512,JSON_THROW_ON_ERROR);
if(($proof['restored_matching']??false)!==true || !hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($proofPath).'/database.sql')))throw new RuntimeException('Backup restore verification failed.');
if($mode==='rehearsal'){
    if(empty($options['settings']))throw new RuntimeException('Owned isolated settings required.');
    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($options['settings']));
    putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$proof['restore_database']);
    require $root.'/tests/Support/roomtype_v2_test_bootstrap.php';
}
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,'Photo import stopped: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).PHP_EOL);exit(1);});
$db=$mode==='rehearsal'?App\Support\RoomTypeV2TestDatabase::connection('mysql'):Illuminate\Support\Facades\DB::connection('mysql');
$db->statement("SET time_zone='+00:00'");$port=(int)$db->selectOne('SELECT @@port p')->p;
if($mode==='rehearsal'){
    if($port===3306 || $port!==$proof['restore_port'] || $db->getDatabaseName()!==$proof['restore_database'])throw new RuntimeException('Rehearsal isolation mismatch.');
}elseif($port!==3306 || $db->getDatabaseName()!=='hotel_booking' || config('app.env')!=='local' || !in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost'],true)
    || config('roomtype-v2.production_manifest')!==null || config('roomtype-v2.prepared_routes'))throw new RuntimeException('Unexpected application target/mode.');
$manifestPath=base_path('database/data/verified-room-type-photos-2026-10-31.json');
$manifestHash=hash_file('sha256',$manifestPath);$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
$entries=$manifest['records'];$seen=[];
foreach($entries as $r){
    if(isset($seen[$r['id']]))throw new RuntimeException('Duplicate photo category.');$seen[$r['id']]=true;
    if($r['checked_on']!=='2026-10-31' || !preg_match('#^storage/accommodations/verified-room-types/photos-20261031/[a-z0-9-]+\.(jpg|png|webp)$#D',$r['local_path']))throw new RuntimeException('Unexpected date or image path.');
    $path=public_path($r['local_path']);$real=realpath($path);$base=realpath(storage_path('app/public/accommodations/verified-room-types/photos-20261031'));
    if(!$real || !$base || !str_starts_with(strtolower($real),strtolower($base).DIRECTORY_SEPARATOR) || !hash_equals($r['sha256'],hash_file('sha256',$real)) || !($size=@getimagesize($real)) || $size[0]<400 || $size[1]<200)throw new RuntimeException('Image hash/decoder/path validation failed.');
    foreach(['source_url','page_url'] as $field)if(!filter_var($r[$field],FILTER_VALIDATE_URL) || parse_url($r[$field],PHP_URL_SCHEME)!=='https')throw new RuntimeException('Invalid public source URL.');
}
function photoState($db):array{
    $out=[];
    foreach($db->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $t){
        if($t->name==='personal_access_tokens')continue; // Admin polling updates only last-used metadata independently.
        $hashes=[];foreach($db->table($t->name)->get() as $r)$hashes[]=hash('sha256',json_encode((array)$r,JSON_THROW_ON_ERROR));sort($hashes,SORT_STRING);
        $out[$t->name]=['count'=>count($hashes),'sha256'=>hash('sha256',implode("\n",$hashes))];
    }return $out;
}
function photoBookingReads($db):array{
    $result=[];$controller=app(App\Http\Controllers\HotelController::class);
    foreach($db->table('hotel')->where('status','active')->orderBy('id')->pluck('id') as $id){
        $req=Illuminate\Http\Request::create('/api/hotels/'.$id.'/rooms','GET',['check_in'=>'2026-11-15','check_out'=>'2026-11-17']);
        $rooms=$controller->rooms($req,$id)->getData(true);$stable=[];
        foreach($rooms as $r)$stable[]=array_intersect_key($r,array_flip(['id','hotel_id','room_type_id','room_number','price','capacity','status','bookable','available_rooms','total_rooms','booked_rooms']));
        usort($stable,fn($a,$b)=>$a['id']<=>$b['id']);$quotes=[];
        foreach(array_unique(array_column($stable,'room_type_id')) as $type)$quotes[$type]=app(App\Services\RoomPricingService::class)->quote($id,$type,'2026-11-15','2026-11-17');
        $result[$id]=hash('sha256',json_encode([$stable,$quotes],JSON_THROW_ON_ERROR));
    }return $result;
}
function addPhotos($db,array $entries):array{
    $result=['images_added'=>0,'evidence_added'=>0,'galleries_preserved'=>0];
    foreach($entries as $r){
        $owner=$db->table('hotel_room_type')->where('id',$r['id'])->lockForUpdate()->first();
        $type=$owner?$db->table('room_type')->where('id',$owner->room_type_id)->first():null;
        if(!$owner || !$type || $owner->hotel_id!==$r['hotel_id'] || $owner->room_type_id!==$r['room_type_id'] || ($owner->name??$type->name)!==$r['name'] || $owner->status==='archived')throw new RuntimeException('Room category identity mismatch.');
        $gallery=$db->table('hotel_room_type_image')->where('hotel_room_type_id',$r['id'])->get();
        if($gallery->isNotEmpty()){
            if(!$gallery->contains(fn($i)=>$i->image_url===$r['local_path'])){$result['galleries_preserved']++;continue;}
        }else{
            $db->table('hotel_room_type_image')->insert(['hotel_room_type_id'=>$r['id'],'image_url'=>$r['local_path'],'is_primary'=>true,'sort_order'=>0]);
            $db->table('hotel_room_type')->where('id',$r['id'])->update(['images_configured'=>true]);$result['images_added']++;
        }
        $sourceKey='photo-completion-20261031';
        $existing=$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$r['id'])->where('source_key',$sourceKey)->first();
        if(!$existing){
            $image=['source_url'=>$r['source_url'],'local_path'=>$r['local_path'],'sha256'=>$r['sha256'],'dimensions'=>$r['dimensions'],'checked_on'=>$r['checked_on'],'evidence_note'=>$r['evidence_note']];
            $db->table('hotel_room_type_evidence')->insert(['hotel_room_type_id'=>$r['id'],'source_key'=>$sourceKey,'source_url'=>$r['page_url'],'checked_on'=>$r['checked_on'],
                'verified_fields'=>json_encode(['image'=>$r['local_path'],'catalog_source_key'=>$r['source_key']],JSON_THROW_ON_ERROR),'image_sources'=>json_encode([$image],JSON_THROW_ON_ERROR),
                'issues'=>json_encode(['Ảnh công khai phục vụ dự án trình diễn theo yêu cầu người dùng; nguồn chưa xác nhận quyền tái sử dụng.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$result['evidence_added']++;
        }elseif($existing->source_url!==$r['page_url'] || json_decode($existing->image_sources,true)[0]['sha256']!==$r['sha256'])throw new RuntimeException('Existing provenance conflicts with the manifest.');
    }return $result;
}
$before=photoState($db);$reportPath=$work.'/'.$mode.'-report.json';
if($mode==='apply'){
    $rehearsal=json_decode(file_get_contents($work.'/rehearsal-report.json'),true,512,JSON_THROW_ON_ERROR);
    if($rehearsal['manifest_sha256']!==$manifestHash || $rehearsal['backup_sha256']!==$proof['dump_sha256'] || !$rehearsal['checks']['all_passed'])throw new RuntimeException('Matching successful rehearsal required.');
    if(file_exists($reportPath)){
        $previous=json_decode(file_get_contents($reportPath),true,512,JSON_THROW_ON_ERROR);
        if($previous['manifest_sha256']===$manifestHash && $previous['after']===$before){echo json_encode(['mode'=>'apply','already_applied'=>true,'images_added'=>0,'evidence_added'=>0]),PHP_EOL;exit;}
    }
    if($before!==$rehearsal['before'])throw new RuntimeException('Business data changed since the restore-tested backup; take a fresh backup and rehearse again.');
}
$catalog=$db->table('hotel_room_type')->get()->all();$images=$db->table('hotel_room_type_image')->get()->all();$evidence=$db->table('hotel_room_type_evidence')->get()->all();
$bookingBefore=photoBookingReads($db);$db->beginTransaction();
try{
    $result=addPhotos($db,$entries);$afterOnce=photoState($db);$repeat=addPhotos($db,$entries);
    if($repeat['images_added'] || $repeat['evidence_added'] || $afterOnce!==photoState($db))throw new RuntimeException('Repeat import changed data.');
    $after=photoState($db);
    foreach($before as $table=>$hash)if(!in_array($table,['hotel_room_type','hotel_room_type_image','hotel_room_type_evidence'],true) && $hash!==$after[$table])throw new RuntimeException('Unrelated data changed: '.$table);
    foreach($catalog as $old){$now=(array)$db->table('hotel_room_type')->where('id',$old->id)->first();$expected=(array)$old;
        if(isset($seen[$old->id])){$expected['images_configured']=$now['images_configured'];}if($expected!==$now)throw new RuntimeException('Category metadata/pricing/status changed.');}
    foreach(['hotel_room_type_image'=>$images,'hotel_room_type_evidence'=>$evidence] as $table=>$rows)foreach($rows as $old)if((array)$db->table($table)->where('id',$old->id)->first()!==(array)$old)throw new RuntimeException('Existing gallery/source changed.');
    if($bookingBefore!==photoBookingReads($db))throw new RuntimeException('Customer availability/pricing reads changed.');
    foreach($entries as $r)if(!$db->table('hotel_room_type_image')->where('hotel_room_type_id',$r['id'])->where('image_url',$r['local_path'])->exists() && !$db->table('hotel_room_type_image')->where('hotel_room_type_id',$r['id'])->exists())throw new RuntimeException('Photo not attached.');
    $db->commit();
}catch(Throwable $e){$db->rollBack();throw $e;}
$report=['mode'=>$mode,'checked_on'=>'2026-10-31','manifest_sha256'=>$manifestHash,'backup_sha256'=>$proof['dump_sha256'],'result'=>$result,'repeat'=>$repeat,
    'checks'=>['all_passed'=>true,'all_other_business_tables_unchanged'=>true,'old_galleries_and_provenance_unchanged'=>true,'category_prices_status_and_inventory_unchanged'=>true,'customer_booking_reads_unchanged'=>true,'repeat_zero_writes'=>true],
    'before'=>$before,'after'=>$after];
file_put_contents($reportPath,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['mode'=>$mode,'result'=>$result,'checks'=>$report['checks']]),PHP_EOL;
