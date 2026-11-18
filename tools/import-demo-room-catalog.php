<?php
// Authorized data-only update; preserves photos, existing prices/rates, inventory and all booking history.
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);$work=$root.'/storage/app/private/room-catalog-update-20261031';
$options=getopt('', ['rehearse:','apply:','settings:']);$mode=isset($options['rehearse'])?'rehearsal':'apply';
$proofPath=realpath($options['rehearse']??$options['apply']??'');$backupRoot=realpath($root.'/storage/app/private/hotel-roomtype-deployment');
if(!$proofPath || !str_starts_with($proofPath,$backupRoot.DIRECTORY_SEPARATOR) || basename($proofPath)!=='verified-backup.json')throw new RuntimeException('Restore-tested private backup required.');
$proof=json_decode(file_get_contents($proofPath),true,512,JSON_THROW_ON_ERROR);
if(($proof['restored_matching']??false)!==true || !hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($proofPath).'/database.sql')))throw new RuntimeException('Backup verification failed.');
if($mode==='rehearsal'){
    if(empty($options['settings']))throw new RuntimeException('Owned isolated settings required.');
    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($options['settings']));putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$proof['restore_database']);
    require $root.'/tests/Support/roomtype_v2_test_bootstrap.php';
}
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,'Catalog update stopped: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).PHP_EOL);exit(1);});
$db=$mode==='rehearsal'?App\Support\RoomTypeV2TestDatabase::connection('mysql'):Illuminate\Support\Facades\DB::connection('mysql');
$db->statement("SET time_zone='+00:00'");$port=(int)$db->selectOne('SELECT @@port p')->p;
if($mode==='rehearsal'){
    if($port===3306 || $port!==$proof['restore_port'] || $db->getDatabaseName()!==$proof['restore_database'])throw new RuntimeException('Rehearsal isolation mismatch.');
}elseif($port!==3306 || $db->getDatabaseName()!=='hotel_booking' || config('app.env')!=='local' || !in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost'],true)
    || config('roomtype-v2.production_manifest')!==null || config('roomtype-v2.prepared_routes'))throw new RuntimeException('Unexpected application target or mode.');
$manifestPath=base_path('database/data/demo-room-catalog-2026-10-31.json');$ratePath=base_path('database/data/verified-room-rate-references-2026-11-01.json');
$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);$rates=json_decode(file_get_contents($ratePath),true,512,JSON_THROW_ON_ERROR)['records'];
$manifestHash=hash('sha256',hash_file('sha256',$manifestPath).hash_file('sha256',$ratePath));
if($manifest['data_kind']!=='user_authorized_demo_capacity_amenities_and_activation' || !empty($manifest['verified_rates']))throw new RuntimeException('Unexpected configuration source.');
$seen=[];
foreach($manifest['records'] as $r){
    if(isset($seen[$r['id']]))throw new RuntimeException('Duplicate room category.');$seen[$r['id']]=true;
    if($r['after']['status']!=='active' || $r['after']['base_price']!==$r['before']['base_price'] || ($r['before']['capacity']!==null && $r['before']['capacity']!==$r['after']['capacity']))throw new RuntimeException('Unapproved change to an existing price/capacity.');
    if($r['before']['capacity']===null && (!is_int($r['after']['capacity']) || $r['after']['capacity']<1 || $r['after']['capacity']>10))throw new RuntimeException('Invalid new capacity.');
    if(array_diff($r['before']['amenities'],$r['after']['amenities']) || count($r['after']['amenities'])>max(50,count($r['before']['amenities'])))throw new RuntimeException('Existing amenities removed or list too long.');
    foreach($r['after']['amenities'] as $a)if(!is_string($a) || mb_strlen($a)>100 || trim($a)==='')throw new RuntimeException('Invalid amenity.');
}
foreach($rates as $r){
    $path=realpath(base_path($r['evidence_path']));$private=realpath(storage_path('app/private/room-catalog-update-20261031'));
    if(!$path || !str_starts_with($path,$private.DIRECTORY_SEPARATOR) || !hash_equals($r['evidence_sha256'],hash_file('sha256',$path)))throw new RuntimeException('Rate source hash/path mismatch.');
    if($r['currency']!=='VND' || $r['nights']!==2 || $r['adults']!==2 || $r['children']!==0 || $r['rooms']!==1 || $r['check_in']!=='2026-11-15' || $r['check_out']!=='2026-11-17'
        || $r['demo_allocation']['start_date']!=='2026-11-15' || $r['demo_allocation']['end_date']!=='2026-11-16' || $r['demo_allocation']['kind']!=='season'
        || !$r['taxes_and_fees_included'] || (float)$r['nightly_average_vnd']*2!==(float)$r['stay_total_vnd'] || !preg_match('/^\d{1,10}\.\d{2}$/D',$r['nightly_average_vnd']) || (float)$r['nightly_average_vnd']<=0)throw new RuntimeException('Invalid dated stay quote/allocation.');
}
function catalogState($db):array{
    $out=[];foreach($db->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $t){
        if($t->name==='personal_access_tokens')continue;
        $hashes=[];foreach($db->table($t->name)->get() as $row)$hashes[]=hash('sha256',json_encode((array)$row,JSON_THROW_ON_ERROR));sort($hashes,SORT_STRING);
        $out[$t->name]=['count'=>count($hashes),'sha256'=>hash('sha256',implode("\n",$hashes))];
    }return $out;
}
function availabilityState($db):array{
    $out=[];foreach($db->table('hotel')->where('status','active')->orderBy('id')->pluck('id') as $id){
        $req=Illuminate\Http\Request::create('/api/hotels/'.$id.'/rooms','GET',['check_in'=>'2026-11-15','check_out'=>'2026-11-17']);
        $rows=app(App\Http\Controllers\HotelController::class)->rooms($req,$id)->getData(true);$stable=[];
        foreach($rows as $r)$stable[]=array_intersect_key($r,array_flip(['id','hotel_id','room_type_id','room_number','capacity','status','bookable','available_rooms','total_rooms','booked_rooms']));
        usort($stable,fn($a,$b)=>$a['id']<=>$b['id']);$out[$id]=hash('sha256',json_encode($stable,JSON_THROW_ON_ERROR));
    }return $out;
}
function addCatalogEvidence($db,int $owner,string $key,string $url,string $date,array $fields,array $issues):int{
    $existing=$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$owner)->where('source_key',$key)->first();
    $data=['hotel_room_type_id'=>$owner,'source_key'=>$key,'source_url'=>$url,'checked_on'=>$date,'verified_fields'=>json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'image_sources'=>'[]','issues'=>json_encode($issues,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    if($existing){foreach($data as $field=>$value){if(in_array($field,['verified_fields','image_sources','issues'],true)){if(json_decode($existing->$field,true)!=json_decode($value,true))throw new RuntimeException('Existing evidence conflict.');}elseif($existing->$field!==$value)throw new RuntimeException('Existing source conflict.');}return 0;}
    $db->table('hotel_room_type_evidence')->insert($data);return 1;
}
function applyCatalog($db,array $manifest,array $rates):array{
    $result=['capacities_filled'=>0,'verified_capacities_filled'=>0,'demo_capacities_filled'=>0,'types_activated'=>0,'room_amenities_added'=>0,'hotel_amenities_added'=>0,'global_capacities_filled'=>0,'source_rows_added'=>0,'reference_rates_added'=>0,'existing_rate_conflicts_preserved'=>0];
    $first=[];foreach($manifest['records'] as $r)$first[$r['hotel_id']]??=$r['id'];
    foreach($manifest['records'] as $r){
        App\Models\Hotel::whereKey($r['hotel_id'])->lockForUpdate()->firstOrFail();
        $owner=App\Models\HotelRoomType::whereKey($r['id'])->lockForUpdate()->first();
        if(!$owner || $owner->hotel_id!==$r['hotel_id'] || $owner->room_type_id!==$r['room_type_id'] || $owner->name!==$r['name'] || $owner->status==='archived')throw new RuntimeException('Hotel/category identity mismatch.');
        $current=['capacity'=>$owner->capacity,'status'=>$owner->status,'base_price'=>$owner->base_price,'amenities'=>$owner->amenities??[]];
        if($current!==$r['after']){
            if($current!==$r['before'])throw new RuntimeException('Category changed since the read-only baseline.');
            if($owner->capacity===null){
                if($db->table('room')->where('hotel_id',$owner->hotel_id)->where('room_type_id',$owner->room_type_id)->exists())throw new RuntimeException('Review committed capacity before changing a populated category.');
                $result['capacities_filled']++;$result[$r['demo_capacity']?'demo_capacities_filled':'verified_capacities_filled']++;
                $legacy=$db->table('room_type')->where('id',$owner->room_type_id)->first();
                if($legacy->capacity===null && $db->table('hotel_room_type')->where('room_type_id',$owner->room_type_id)->count()===1 && !$db->table('room')->where('room_type_id',$owner->room_type_id)->exists()){
                    $db->table('room_type')->where('id',$owner->room_type_id)->update(['capacity'=>$r['after']['capacity']]);$result['global_capacities_filled']++;
                }
            }
            if($owner->status!=='active')$result['types_activated']++;
            $result['room_amenities_added']+=count($r['amenities_added']);
            $owner->capacity=$r['after']['capacity'];$owner->amenities=$r['after']['amenities'];$owner->status='active';$owner->save();
        }
        $fields=['data_kind'=>'user_authorized_demo_configuration','room_amenities_added'=>$r['amenities_added'],'amenity_tier'=>$r['amenity_tier'],'tier_basis'=>$r['amenity_tier_basis'],'configured_status'=>'active','capacity_basis'=>$r['capacity_basis']];
        if($r['demo_capacity'])$fields['demo_capacity']=$r['after']['capacity'];
        if($first[$r['hotel_id']]===$r['id'])$fields['hotel_amenities_added']=collect($manifest['hotels'])->firstWhere('id',$r['hotel_id'])['amenities_added'];
        $result['source_rows_added']+=addCatalogEvidence($db,$r['id'],'demo-catalog-20261101',$manifest['source_uri'],'2026-11-01',$fields,['Tiện ích và sức chứa đánh dấu DEMO là cấu hình theo yêu cầu người dùng, không xác nhận như thực tế khách sạn. Không tạo tồn kho hay giá giả.']);
        if($r['capacity_source'])$result['source_rows_added']+=addCatalogEvidence($db,$r['id'],'cloudbeds-capacity-20261031',$r['capacity_source']['source_url'],$r['capacity_source']['checked_on'],['capacity'=>$r['after']['capacity'],'source_sha256'=>$r['capacity_source']['sha256'],'source_field'=>'HotelRoom.occupancy.maxValue'],[]);
    }
    foreach($manifest['hotels'] as $h){
        foreach($h['amenities_added'] as $name){
            $option=App\Support\HotelAmenityCatalog::remember($name);
            $assigned=App\Models\HotelAmenity::firstOrCreate(['hotel_id'=>$h['id'],'amenity'=>$option->amenity],['icon'=>$option->icon,'catalog_identity'=>null]);
            if($assigned->wasRecentlyCreated)$result['hotel_amenities_added']++;
        }
    }
    foreach($rates as $r){
        $owner=$db->table('hotel_room_type')->where('id',$r['hotel_room_type_id'])->first();
        if(!$owner || $owner->hotel_id!==$r['hotel_id'] || $owner->room_type_id!==$r['room_type_id'] || $owner->name!==$r['name'])throw new RuntimeException('Rate/category identity mismatch.');
        $a=$r['demo_allocation'];$overlap=$db->table('hotel_room_rate')->where('hotel_id',$r['hotel_id'])->where('room_type_id',$r['room_type_id'])->where('kind','season')->where('start_date','<=',$a['end_date'])->where('end_date','>=',$a['start_date'])->lockForUpdate()->get();
        $matching=$overlap->first(fn($p)=>$p->start_date===$a['start_date'] && $p->end_date===$a['end_date'] && $p->price===$r['nightly_average_vnd'] && $p->label===$a['label']);
        if(!$matching && $overlap->isEmpty()){
            $db->table('hotel_room_rate')->insert(['hotel_id'=>$r['hotel_id'],'room_type_id'=>$r['room_type_id'],'kind'=>'season','start_date'=>$a['start_date'],'end_date'=>$a['end_date'],'price'=>$r['nightly_average_vnd'],'label'=>$a['label'],'created_at'=>now(),'updated_at'=>now()]);$result['reference_rates_added']++;
        }elseif(!$matching)$result['existing_rate_conflicts_preserved']++;
        $result['source_rows_added']+=addCatalogEvidence($db,$r['hotel_room_type_id'],'expedia-stay-20261115-20261117',$r['source_url'],$r['checked_on'],['data_kind'=>'verified_stay_total_and_demo_average_allocation','quote'=>$r],['Giá bình quân được chia từ tổng kỳ 2 đêm đã xác minh; không phải bảng giá riêng từng đêm của OTA. Chỉ dùng làm tham khảo đúng kỳ/người/gói này. Không suy ra tồn phòng.']);
        if($db->table('room')->where('hotel_id',$r['hotel_id'])->where('room_type_id',$r['room_type_id'])->where('catalog_retired',false)->exists() && !$result['existing_rate_conflicts_preserved']){
            $quote=app(App\Services\RoomPricingService::class)->quote($r['hotel_id'],$r['room_type_id'],$r['check_in'],$r['check_out']);
            if((float)$quote['total_price']!==(float)$r['stay_total_vnd'])throw new RuntimeException('Reference stay total differs from the verified source.');
        }
    }return $result;
}
$before=catalogState($db);$reportPath=$work.'/'.$mode.'-report.json';
if($mode==='apply'){
    $rehearsal=json_decode(file_get_contents($work.'/rehearsal-report.json'),true,512,JSON_THROW_ON_ERROR);
    if($rehearsal['manifest_sha256']!==$manifestHash || $rehearsal['backup_sha256']!==$proof['dump_sha256'] || !$rehearsal['checks']['all_passed'])throw new RuntimeException('Matching successful rehearsal required.');
    if(file_exists($reportPath)){$prior=json_decode(file_get_contents($reportPath),true,512,JSON_THROW_ON_ERROR);if($prior['manifest_sha256']===$manifestHash && $prior['after']===$before){echo json_encode(['mode'=>'apply','already_applied'=>true,'writes'=>0]),PHP_EOL;exit;}}
    if($before!==$rehearsal['before'])throw new RuntimeException('Business state changed; take a fresh backup and rehearse before applying.');
}
$old=[];foreach(['hotel_room_type','room_type','hotel_amenity','hotel_room_rate','hotel_room_type_evidence'] as $table)$old[$table]=$db->table($table)->get()->all();
$availability=availabilityState($db);$db->beginTransaction();
try{
    $result=applyCatalog($db,$manifest,$rates);$after=catalogState($db);$repeat=applyCatalog($db,$manifest,$rates);
    if($after!==catalogState($db) || array_sum($repeat)!==0)throw new RuntimeException('Repeat import was not a no-op.');
    foreach($before as $table=>$hash)if(!in_array($table,array_keys($old),true) && $hash!==$after[$table])throw new RuntimeException('Unrelated data changed: '.$table);
    foreach($old as $table=>$rows)foreach($rows as $previous){$now=(array)$db->table($table)->where('id',$previous->id)->first();$expected=(array)$previous;
        if($table==='hotel_room_type' && isset($seen[$previous->id])){$config=collect($manifest['records'])->firstWhere('id',$previous->id);$expected['status']='active';$expected['capacity']=$config['after']['capacity'];$expected['amenities']=$now['amenities'];if(json_decode($now['amenities'],true)!==$config['after']['amenities'])throw new RuntimeException('Wrong amenity configuration.');}
        if($table==='room_type' && $previous->capacity===null){$config=collect($manifest['records'])->firstWhere('room_type_id',$previous->id);if($config && $config['before']['capacity']===null && $db->table('hotel_room_type')->where('room_type_id',$previous->id)->count()===1 && !$db->table('room')->where('room_type_id',$previous->id)->exists())$expected['capacity']=$config['after']['capacity'];}
        if($expected!==$now)throw new RuntimeException('Existing unrelated fields/source/rate changed in '.$table);
    }
    if($availability!==availabilityState($db))throw new RuntimeException('Availability/physical capacity changed.');
    if($db->table('hotel_room_type')->where('status','!=','archived')->where(fn($q)=>$q->where('status','!=','active')->orWhereNull('capacity'))->exists())throw new RuntimeException('Catalog activation/capacity update incomplete.');
    $checks=['all_passed'=>true,'photos_and_physical_inventory_unchanged'=>true,'booking_payment_and_snapshots_unchanged'=>true,'old_prices_rates_and_nonempty_capacities_preserved'=>true,'old_hotel_amenities_and_evidence_preserved'=>true,'reference_totals_equal_source'=>true,'availability_unchanged'=>true,'repeat_zero_writes'=>true];
    if($mode==='rehearsal')$db->rollBack();else $db->commit();
}catch(Throwable $e){$db->rollBack();throw $e;}
$report=['mode'=>$mode,'applied_on'=>'2026-11-01','manifest_sha256'=>$manifestHash,'backup_sha256'=>$proof['dump_sha256'],'result'=>$result,'repeat'=>$repeat,'checks'=>$checks,'before'=>$before,'after'=>$after];
file_put_contents($reportPath,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode(['mode'=>$mode,'result'=>$result,'checks'=>$checks]),PHP_EOL;
