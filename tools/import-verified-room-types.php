<?php
// Exact-scope data import with a restore-tested backup and an isolated rehearsal.
require dirname(__DIR__).'/vendor/autoload.php';
$options=getopt('', ['rehearse:', 'apply:', 'settings:', 'verify:']);
$root=dirname(__DIR__);
$work=$root.'/storage/app/private/room-type-enrichment-20261031';
$manifest=json_decode(file_get_contents($root.'/database/data/verified-room-types-2026-10-31.json'),true,512,JSON_THROW_ON_ERROR);
$proofPath=$options['rehearse']??$options['apply']??$options['verify']??null;
$proofFile=$proofPath?realpath($proofPath):false;
$backupRoot=realpath($root.'/storage/app/private/hotel-roomtype-deployment');
if(!$proofFile || !str_starts_with($proofFile,$backupRoot.DIRECTORY_SEPARATOR) || basename($proofFile)!=='verified-backup.json') throw new RuntimeException('Verified private backup proof required.');
$proof=json_decode(file_get_contents($proofFile),true,512,JSON_THROW_ON_ERROR);
if(($proof['restored_matching']??false)!==true || !hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($proofFile).'/database.sql'))) throw new RuntimeException('Backup verification failed.');
if(isset($options['rehearse'])) {
    if(empty($options['settings']))throw new RuntimeException('Owned isolated settings required.');
    putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.realpath($options['settings']));
    putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE='.$proof['restore_database']);
    require $root.'/tests/Support/roomtype_v2_test_bootstrap.php';
}
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $error){
    fwrite(STDERR,'Room import stopped: '.(get_class($error)===RuntimeException::class?$error->getMessage():get_class($error)).PHP_EOL);
    exit(1);
});
$db=isset($options['rehearse']) ? App\Support\RoomTypeV2TestDatabase::connection('mysql') : Illuminate\Support\Facades\DB::connection('mysql');
$db->statement("SET time_zone='+00:00'");
$port=(int)$db->selectOne('SELECT @@port p')->p;
if(isset($options['rehearse'])) {
    if($port!==$proof['restore_port'] || $db->getDatabaseName()!==$proof['restore_database'] || $port===3306)throw new RuntimeException('Rehearsal isolation mismatch.');
} else {
    if($port!==3306 || $db->getDatabaseName()!=='hotel_booking' || !in_array(config('database.connections.mysql.host'),['127.0.0.1','localhost'],true)
        || config('app.env')!=='local' || config('roomtype-v2.production_manifest')!==null || config('roomtype-v2.prepared_routes'))throw new RuntimeException('Unexpected main target or application mode.');
}

function rowsHash($db,string $table): array {
    $hashes=[];
    foreach($db->table($table)->get() as $row)$hashes[]=hash('sha256',json_encode((array)$row,JSON_THROW_ON_ERROR));
    sort($hashes,SORT_STRING);
    return ['count'=>count($hashes),'sha256'=>hash('sha256',implode("\n",$hashes))];
}
function state($db): array {
    $out=[];
    foreach($db->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $row)$out[$row->name]=rowsHash($db,$row->name);
    return $out;
}
function businessState(array $state): array {
    // Authenticated Admin polling updates token last_used_at independently of this data import.
    // Ignore this operational table in equality gates; never output its row contents.
    unset($state['personal_access_tokens']);
    return $state;
}
function customerReads($db): array {
    $out=[];$controller=app(App\Http\Controllers\HotelController::class);
    foreach($db->table('hotel')->where('status','active')->orderBy('id')->pluck('id') as $id) {
        $request=Illuminate\Http\Request::create('/api/hotels/'.$id.'/rooms','GET',['check_in'=>'2026-11-15','check_out'=>'2026-11-17']);
        $rows=$controller->rooms($request,$id)->getData(true);
        $stable=[];
        foreach($rows as $room) $stable[]=[...array_intersect_key($room,array_flip(['id','hotel_id','room_type_id','room_number','price','capacity','status','bookable','available_rooms','total_rooms','booked_rooms'])),
            'type'=>array_intersect_key($room['room_type'],array_flip(['id','name','capacity','base_price']))];
        usort($stable,fn($a,$b)=>$a['id']<=>$b['id']);
        $quotes=[];
        foreach(array_unique(array_column($stable,'room_type_id')) as $type)$quotes[$type]=app(App\Services\RoomPricingService::class)->quote($id,$type,'2026-11-15','2026-11-17');
        $out[$id]=['physical_room_rows'=>count($stable),'availability_and_booking_fields_sha256'=>hash('sha256',json_encode($stable,JSON_THROW_ON_ERROR)),
            'price_quotes_sha256'=>hash('sha256',json_encode($quotes,JSON_THROW_ON_ERROR))];
    }
    return $out;
}
function verifyPreserved($db,array $before,array $legacy,array $catalog,array $gallery,array $oldColumns): array {
    $after=state($db);
    $allowed=['room_type','hotel_room_type','hotel_room_type_image','hotel_room_type_evidence','migrations','personal_access_tokens'];
    foreach($before as $table=>$hash)if(!in_array($table,$allowed,true) && $hash!==($after[$table]??null))throw new RuntimeException('Unrelated table changed: '.$table);
    foreach($legacy as $row)if((array)$db->table('room_type')->where('id',$row->id)->first()!==(array)$row)throw new RuntimeException('Legacy RoomType changed.');
    foreach($gallery as $row)if((array)$db->table('hotel_room_type_image')->where('id',$row->id)->first()!==(array)$row)throw new RuntimeException('Existing image/link changed.');
    foreach($catalog as $old) {
        $new=$db->table('hotel_room_type')->where('id',$old->id)->first();
        foreach($oldColumns as $field) {
            if($field==='images_configured')continue;
            if(in_array($field,['description','capacity','bed_type','area_min_m2','area_max_m2'],true) && ($old->$field===null || $old->$field===''))continue;
            if($field==='amenities' && empty(json_decode($old->$field,true)))continue;
            if($old->$field!==$new->$field)throw new RuntimeException('Existing nonempty catalog field changed: '.$field);
        }
    }
    $newIds=$db->table('hotel_room_type')->whereNotIn('id',array_column($catalog,'id'))->pluck('room_type_id');
    if($db->table('room')->whereIn('room_type_id',$newIds)->exists() || $db->table('hotel_room_rate')->whereIn('room_type_id',$newIds)->exists()
        || $db->table('hotel_room_type')->whereIn('room_type_id',$newIds)->where(fn($q)=>$q->where('status','!=','inactive')->orWhereNotNull('base_price'))->exists())throw new RuntimeException('New unpriced category is offered or has fabricated inventory.');
    if($db->table('hotel_room_type as c')->leftJoin('room_type as t','t.id','=','c.room_type_id')->whereNull('t.id')->exists())throw new RuntimeException('Orphan catalog type.');
    return ['unrelated_tables_unchanged'=>true,'legacy_type_rows_unchanged'=>true,'existing_galleries_unchanged'=>true,
        'existing_nonempty_metadata_unchanged'=>true,'new_types_inactive_without_prices_rooms_or_rates'=>true,'new_type_count'=>$newIds->count(),'state'=>$after];
}

$mode=isset($options['rehearse'])?'rehearsal':(isset($options['apply'])?'apply':'verify');
if($mode==='verify') {
    $report=json_decode(file_get_contents($work.'/apply-report.json'),true,512,JSON_THROW_ON_ERROR);
    $current=state($db);$changed=[];
    foreach($current as $table=>$hash)if($hash!==($report['verification']['state'][$table]??null))$changed[]=$table;
    $businessChanges=array_values(array_diff($changed,['personal_access_tokens']));
    if($businessChanges)throw new RuntimeException('Database changed after import in: '.implode(', ',$businessChanges).'; compare the report before proceeding.');
    echo json_encode(['mode'=>'verify','matches_applied_business_state'=>true,'operational_tables_changed'=>$changed]),PHP_EOL;exit;
}
if($mode==='apply') {
    if(is_file($work.'/apply-report.json')) {
        $applied=json_decode(file_get_contents($work.'/apply-report.json'),true,512,JSON_THROW_ON_ERROR);
        if(hash_equals($applied['manifest_sha256'],hash_file('sha256',$root.'/database/data/verified-room-types-2026-10-31.json')) && businessState(state($db))===businessState($applied['verification']['state'])) {
            echo json_encode(['mode'=>'apply','already_applied'=>true,'created'=>0,'enriched'=>0,'images_added'=>0,'idempotent'=>true]),PHP_EOL;
            exit;
        }
    }
    $rehearsal=json_decode(file_get_contents($work.'/rehearsal-report.json'),true,512,JSON_THROW_ON_ERROR);
    if(!hash_equals($rehearsal['manifest_sha256'],hash_file('sha256',$root.'/database/data/verified-room-types-2026-10-31.json'))
        || !hash_equals($rehearsal['backup_sha256'],$proof['dump_sha256']) || !$rehearsal['idempotent'] || !$rehearsal['negative_cases_passed'])throw new RuntimeException('Matching successful rehearsal required.');
    $current=businessState(state($db));$expected=businessState($rehearsal['before']);
    if($current!==$expected) {
        $changed=[];foreach($current as $table=>$hash)if($hash!==($expected[$table]??null))$changed[]=$table;
        throw new RuntimeException('Primary business data changed since backup/rehearsal in '.implode(', ',$changed).'; repeat backup and rehearsal.');
    }
}
$before=state($db);
$customerBefore=customerReads($db);
$legacy=$db->table('room_type')->orderBy('id')->get()->all();
$catalog=$db->table('hotel_room_type')->orderBy('id')->get()->all();
$gallery=$db->table('hotel_room_type_image')->orderBy('id')->get()->all();
$oldColumns=array_column($db->select('SHOW COLUMNS FROM hotel_room_type'),'Field');
$migration='2026_10_31_000004_allow_verified_room_catalog_without_rates';
// Run only this data-schema migration; never run the pending V2 migrations or seeders.
if(!$db->table('migrations')->where('migration',$migration)->exists()) {
    (require $root.'/database/migrations/'.$migration.'.php')->up();
    $db->table('migrations')->insert(['migration'=>$migration,'batch'=>(int)$db->table('migrations')->max('batch')+1]);
}
$importer=new App\Services\VerifiedRoomCatalogImporter($db);
$negativePassed=true;
if($mode==='rehearsal') {
    foreach(['identity','duplicate','price','inventory','foreign-alias'] as $case) {
        $invalid=$manifest;
        if($case==='identity')$invalid['records'][0]['hotel_name']='Wrong branch';
        if($case==='duplicate')$invalid['records'][]=$invalid['records'][0];
        if($case==='price')$invalid['records'][0]['price_reference']=['amount'=>100];
        if($case==='inventory')$invalid['records'][0]['physical_inventory']=10;
        if($case==='foreign-alias')$invalid['records'][0]['existing_room_type_ids']=[999999];
        $stateBefore=state($db);$rejected=false;
        try{$importer->run($invalid,false);}catch(RuntimeException $e){$rejected=true;}
        if(!$rejected || state($db)!==$stateBefore)throw new RuntimeException('Import rejection/atomicity failed: '.$case);
    }
}
$first=$importer->run($manifest,false);
$verified=verifyPreserved($db,$before,$legacy,$catalog,$gallery,$oldColumns);
if(customerReads($db)!==$customerBefore)throw new RuntimeException('Existing customer availability/quote response changed.');
$adminCount=0;
foreach(array_keys($first['hotels']) as $hotelId)$adminCount+=count(app(App\Services\HotelRoomTypeService::class)->index($hotelId));
if($adminCount!==$db->table('hotel_room_type')->where('status','!=','archived')->count())throw new RuntimeException('Admin catalog reader omitted a category.');
$stateBeforeRepeat=state($db);
$repeat=$importer->run($manifest,false);
if($repeat['created']!==0 || $repeat['enriched']!==0 || $repeat['images_added']!==0 || $repeat['evidence_added']!==0 || businessState(state($db))!==businessState($stateBeforeRepeat))throw new RuntimeException('Repeated import changed data.');
$newType=$db->table('hotel_room_type')->where('status','inactive')->whereNull('base_price')->first();
if($newType) {
    $rejected=false;
    try{app(App\Services\RoomPricingService::class)->quote($newType->hotel_id,$newType->room_type_id,'2026-11-15','2026-11-16');}
    catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$rejected=$e->getStatusCode()===404;}
    if(!$rejected)throw new RuntimeException('Uninventoried category can receive a quote.');
}
$report=['mode'=>$mode,'manifest_sha256'=>hash_file('sha256',$root.'/database/data/verified-room-types-2026-10-31.json'),
    'backup_sha256'=>$proof['dump_sha256'],'before'=>$before,'first'=>$first,'repeat'=>$repeat,
    'verification'=>$verified,'idempotent'=>true,'negative_cases_passed'=>$negativePassed,'unpriced_quote_rejected'=>true,
    'customer_reads_unchanged'=>$customerBefore,'admin_catalog_rows_read'=>$adminCount];
file_put_contents($work.'/'.$mode.'-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode(['mode'=>$mode,'created'=>$first['created'],'enriched'=>$first['enriched'],'images_added'=>$first['images_added'],
    'evidence_added'=>$first['evidence_added'],'idempotent'=>true,'preserved'=>true,'hotel_count'=>count($first['hotels'])]),PHP_EOL;
