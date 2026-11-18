<?php
// Fill NULL project base prices only, using individually matched, archived genuine quotes.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,'Reference price update stopped: '.(get_class($e)===RuntimeException::class?$e->getMessage():get_class($e)).PHP_EOL);exit(1);});
$options=getopt('',['apply:']);$proofFile=realpath($options['apply']??'');$private=realpath(storage_path('app/private/hotel-roomtype-deployment'));
if(!$proofFile||!str_starts_with($proofFile,$private.DIRECTORY_SEPARATOR)||basename($proofFile)!=='verified-backup.json')throw new RuntimeException('Verified private backup required.');
$proof=json_decode(file_get_contents($proofFile),true,512,JSON_THROW_ON_ERROR);
if(!($proof['restored_matching']??false)||!hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($proofFile).'/database.sql')))throw new RuntimeException('Backup verification mismatch.');
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='hotel_booking'||(int)$db->selectOne('SELECT @@port p')->p!==3306||config('app.env')!=='local'||config('roomtype-v2.production_manifest')!==null||config('roomtype-v2.prepared_routes'))throw new RuntimeException('Unexpected target or mode.');
$file=base_path('database/data/reference-base-prices-2026-11-04.json');$hash=hash_file('sha256',$file);$manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if($manifest['source_kind']!=='verified_reference_quotes_as_user_authorized_project_base_prices')throw new RuntimeException('Unapproved pricing source.');
$work=storage_path('app/private/room-admin-update-20261102');$privateRoot=realpath(storage_path('app/private'));
foreach($manifest['records'] as $plan){
    $source=realpath(base_path($plan['evidence_path']));
    if(!$source||!str_starts_with($source,$privateRoot.DIRECTORY_SEPARATOR)||!hash_equals($plan['evidence_sha256'],hash_file('sha256',$source)))throw new RuntimeException('Source evidence hash/path mismatch.');
    if($plan['base_price_before']!==null||$plan['currency']!=='VND'||!preg_match('/^\d{1,10}\.\d{2}$/D',$plan['base_price_vnd'])||(float)$plan['base_price_vnd']<=0
        ||!in_array($plan['nights'],[2,4],true)||abs((float)$plan['base_price_vnd']*$plan['nights']-(float)$plan['stay_total_vnd'])>0.02)throw new RuntimeException('Invalid source quote or allocation.');
}
function referencePriceState($db):array{
    $out=[];foreach($db->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $t){
        if($t->name==='personal_access_tokens')continue;$rows=[];foreach($db->table($t->name)->get() as $row)$rows[]=hash('sha256',json_encode((array)$row,JSON_THROW_ON_ERROR));sort($rows,SORT_STRING);$out[$t->name]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
    }return $out;
}
$db->beginTransaction();
try{
    $db->table('hotel')->orderBy('id')->lockForUpdate()->get(['id']);
    $before=referencePriceState($db);$old=[];foreach(['room','room_type','hotel_room_type','hotel_room_type_evidence'] as $table)$old[$table]=$db->table($table)->orderBy('id')->get()->keyBy('id');
    $result=['base_prices_filled'=>0,'global_prices_filled'=>0,'new_room_prices_filled'=>0,'new_rooms_ready'=>0,'evidence_added'=>0,'existing_prices_preserved'=>0,'already_applied'=>0];
    $allowed=['room'=>[],'room_type'=>[],'hotel_room_type'=>[]];$details=[];$seen=[];
    foreach($manifest['records'] as $plan){
        $id=$plan['hotel_room_type_id'];if(isset($seen[$id]))throw new RuntimeException('Duplicate category in price plan.');$seen[$id]=true;
        $record=$db->table('hotel_room_type')->where('id',$id)->lockForUpdate()->first();
        if(!$record||$record->hotel_id!==$plan['hotel_id']||$record->room_type_id!==$plan['room_type_id']||$record->name!==$plan['name']||$record->status==='archived')throw new RuntimeException('Hotel/category identity mismatch.');
        $existing=$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$id)->where('source_key','reference-base-20261104')->first();
        if($existing){if((json_decode($existing->verified_fields,true)['manifest_sha256']??null)!==$hash)throw new RuntimeException('Existing reference evidence differs.');$result['already_applied']++;continue;}
        if($record->base_price!==null){$result['existing_prices_preserved']++;continue;}
        $inventory=$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$id)->where('source_key','admin-stock-20261102')->first();
        if(!$inventory)throw new RuntimeException('Missing authorized stock provenance.');$roomIds=json_decode($inventory->verified_fields,true)['room_ids']??[];
        if(count($roomIds)!==3)throw new RuntimeException('Unexpected authorized room count.');
        foreach($roomIds as $roomId){
            $room=$db->table('room')->where('id',$roomId)->lockForUpdate()->first();
            if(!$room||$room->hotel_id!==$record->hotel_id||$room->room_type_id!==$record->room_type_id)throw new RuntimeException('Physical room identity mismatch.');
            if($room->price!==null)continue;
            if($room->status!=='maintenance'||$room->catalog_retired||$db->table('booking_room')->where('room_id',$roomId)->exists())throw new RuntimeException('New room state changed; review before pricing.');
            $db->table('room')->where('id',$roomId)->update(['price'=>$plan['base_price_vnd'],'status'=>'available']);$allowed['room'][$roomId]=true;$result['new_room_prices_filled']++;$result['new_rooms_ready']++;
        }
        $db->table('hotel_room_type')->where('id',$id)->update(['base_price'=>$plan['base_price_vnd']]);$allowed['hotel_room_type'][$id]=true;$result['base_prices_filled']++;
        $legacy=$db->table('room_type')->where('id',$record->room_type_id)->first();
        if($legacy->base_price===null&&$db->table('hotel_room_type')->where('room_type_id',$record->room_type_id)->count()===1){$db->table('room_type')->where('id',$record->room_type_id)->update(['base_price'=>$plan['base_price_vnd']]);$allowed['room_type'][$record->room_type_id]=true;$result['global_prices_filled']++;}
        $db->table('hotel_room_type_evidence')->insert(['hotel_room_type_id'=>$id,'source_key'=>'reference-base-20261104','source_url'=>$plan['source_url'],'checked_on'=>$plan['checked_on'],
            'verified_fields'=>json_encode(['source_kind'=>$manifest['source_kind'],'manifest_sha256'=>$hash,'project_base_price_vnd'=>$plan['base_price_vnd'],'reference'=>$plan],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'image_sources'=>'[]','issues'=>json_encode(['Người dùng cho phép dùng giá thật của kỳ tham khảo làm giá cơ bản dự án. Không khẳng định nguồn bán cùng giá ở mọi ngày.','Phân bổ tổng giá kỳ cho số đêm để lưu giá tham khảo; giữ nguyên giá và điều kiện gốc trong bằng chứng.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$result['evidence_added']++;
        $details[]=['hotel_room_type_id'=>$id,'room_type_id'=>$record->room_type_id,'hotel_id'=>$record->hotel_id,'base_price'=>$plan['base_price_vnd']];
    }
    $after=referencePriceState($db);
    foreach($before as $table=>$state)if(!array_key_exists($table,$old)&&$state!==$after[$table])throw new RuntimeException('Unrelated business table changed: '.$table);
    foreach($old as $table=>$rows)foreach($rows as $id=>$row){
        $current=$db->table($table)->where('id',$id)->first();if(!$current)throw new RuntimeException('Existing record removed.');$a=(array)$row;$b=(array)$current;
        if(isset($allowed[$table][$id]))foreach($table==='room'?['price','status']:['base_price','updated_at'] as $field){unset($a[$field],$b[$field]);}
        if($a!==$b)throw new RuntimeException('Unexpected existing field change: '.$table.' #'.$id);
    }
    foreach(['room','room_type','hotel_room_type'] as $table)if($before[$table]['count']!==$after[$table]['count'])throw new RuntimeException('Price update created or removed inventory.');
    if($after['hotel_room_type_evidence']['count']!==$before['hotel_room_type_evidence']['count']+$result['evidence_added'])throw new RuntimeException('Evidence count mismatch.');
    $db->commit();$report=['applied_on'=>'2026-11-04','manifest_sha256'=>$hash,'backup_sha256'=>$proof['dump_sha256'],'result'=>$result,'updated'=>$details,'before'=>$before,'after'=>$after,'old_rooms_bookings_images_rates_and_other_fields_preserved'=>true];
    $reportFile=$work.($result['base_prices_filled']?'/base-prices-apply-report.json':'/base-prices-repeat-report.json');file_put_contents($reportFile,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($result),PHP_EOL;
}catch(Throwable $e){$db->rollBack();throw $e;}
