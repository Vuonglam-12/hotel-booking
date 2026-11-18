<?php
// Explicitly authorized managed stock. No price invention, existing-room edits, or booking writes.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,'Inventory update stopped: '.($e instanceof RuntimeException?$e->getMessage():get_class($e)).PHP_EOL);exit(1);});
$options=getopt('',['apply:']);$proofFile=realpath($options['apply']??'');$private=realpath(storage_path('app/private/hotel-roomtype-deployment'));
if(!$proofFile||!str_starts_with($proofFile,$private.DIRECTORY_SEPARATOR)||basename($proofFile)!=='verified-backup.json')throw new RuntimeException('Verified private backup required.');
$proof=json_decode(file_get_contents($proofFile),true,512,JSON_THROW_ON_ERROR);
if(!($proof['restored_matching']??false)||!hash_equals($proof['dump_sha256'],hash_file('sha256',dirname($proofFile).'/database.sql')))throw new RuntimeException('Backup verification mismatch.');
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='hotel_booking'||(int)$db->selectOne('SELECT @@port p')->p!==3306||config('app.env')!=='local'||config('roomtype-v2.production_manifest')!==null||config('roomtype-v2.prepared_routes'))throw new RuntimeException('Unexpected target or mode.');
$column=$db->selectOne("SHOW COLUMNS FROM room WHERE Field='price'");if($column->Null!=='YES'||$column->Type!=='decimal(12,2)')throw new RuntimeException('Apply only the pending-price migration first.');
$file=base_path('database/data/room-inventory-configuration-2026-11-02.json');$hash=hash_file('sha256',$file);$manifest=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if($manifest['source_kind']!=='user_authorized_project_inventory'||$manifest['rooms_per_empty_type']!==3)throw new RuntimeException('Unapproved inventory plan.');
$work=storage_path('app/private/room-admin-update-20261102');
function inventoryHashes($db):array{
    $out=[];foreach($db->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $t){
        if($t->name==='personal_access_tokens')continue;$rows=[];foreach($db->table($t->name)->get() as $row)$rows[]=hash('sha256',json_encode((array)$row,JSON_THROW_ON_ERROR));sort($rows,SORT_STRING);$out[$t->name]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
    }return $out;
}
$db->beginTransaction();
try{
    foreach(array_unique(array_column($manifest['records'],'hotel_id')) as $hotel)$db->table('hotel')->where('id',$hotel)->lockForUpdate()->first();
    $before=inventoryHashes($db);$oldRooms=$db->table('room')->orderBy('id')->get()->keyBy('id');$oldEvidence=$db->table('hotel_room_type_evidence')->orderBy('id')->get()->keyBy('id');
    $created=[];$sources=0;$already=0;$seen=[];
    foreach($manifest['records'] as $plan){
        if(isset($seen[$plan['id']])||$plan['configured_total_rooms']!==3)throw new RuntimeException('Duplicate or unapproved quantity.');$seen[$plan['id']]=true;
        $record=$db->table('hotel_room_type')->where('id',$plan['id'])->lockForUpdate()->first();
        if(!$record||$record->hotel_id!==$plan['hotel_id']||$record->room_type_id!==$plan['room_type_id']||$record->name!==$plan['name']||$record->status==='archived')throw new RuntimeException('Hotel/category identity mismatch.');
        $key='admin-stock-20261102';$evidence=$db->table('hotel_room_type_evidence')->where('hotel_room_type_id',$record->id)->where('source_key',$key)->first();
        if($evidence){if((json_decode($evidence->verified_fields,true)['manifest_sha256']??null)!==$hash)throw new RuntimeException('Existing inventory plan differs.');$already++;continue;}
        if($record->capacity!==$plan['capacity']||$record->base_price!==$plan['base_price']||$db->table('room')->where('hotel_id',$record->hotel_id)->where('room_type_id',$record->room_type_id)->exists())throw new RuntimeException('Catalog or stock changed after inventory audit.');
        $used=array_fill_keys($db->table('room')->where('hotel_id',$record->hotel_id)->pluck('room_number')->all(),true);$candidate=1;$ids=[];
        for($i=0;$i<3;$i++){
            do{$floor=1+intdiv($candidate-1,10);$number=$floor.str_pad((string)$candidate,2,'0',STR_PAD_LEFT);$candidate++;}while(isset($used[$number]));
            if($floor>99)throw new RuntimeException('No valid room number remaining.');
            $id=$db->table('room')->insertGetId(['hotel_id'=>$record->hotel_id,'room_type_id'=>$record->room_type_id,'room_number'=>$number,'floor'=>$floor,'price'=>$record->base_price,'capacity'=>$record->capacity,'status'=>$record->base_price===null?'maintenance':'available','catalog_retired'=>false]);
            $created[]=$id;$ids[]=$id;$used[$number]=true;
        }
        $db->table('hotel_room_type_evidence')->insert(['hotel_room_type_id'=>$record->id,'source_key'=>$key,'source_url'=>$manifest['source_uri'],'checked_on'=>'2026-11-02',
            'verified_fields'=>json_encode(['source_kind'=>$manifest['source_kind'],'configured_total_rooms'=>3,'room_ids'=>$ids,'manifest_sha256'=>$hash],JSON_THROW_ON_ERROR),
            'image_sources'=>'[]','issues'=>json_encode(['Số phòng quản lý do người dùng cho phép cấu hình, không phải số phòng thực tế do khách sạn xác nhận.','Phòng chưa có giá giữ NULL và trạng thái chưa sẵn sàng; không tự đặt giá 0 hoặc mở bán.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$sources++;
    }
    $after=inventoryHashes($db);
    foreach($before as $table=>$state)if(!in_array($table,['room','hotel_room_type_evidence'],true)&&$state!==$after[$table])throw new RuntimeException('Unrelated business table changed: '.$table);
    foreach($oldRooms as $id=>$row)if((array)$row!==(array)$db->table('room')->where('id',$id)->first())throw new RuntimeException('Existing physical room changed.');
    foreach($oldEvidence as $id=>$row)if((array)$row!==(array)$db->table('hotel_room_type_evidence')->where('id',$id)->first())throw new RuntimeException('Existing evidence changed.');
    if($after['room']['count']!==$before['room']['count']+count($created)||$after['hotel_room_type_evidence']['count']!==$before['hotel_room_type_evidence']['count']+$sources)throw new RuntimeException('Unexpected row counts.');
    $db->commit();
    $report=['applied_on'=>'2026-11-02','manifest_sha256'=>$hash,'backup_sha256'=>$proof['dump_sha256'],'types_updated'=>$sources,'rooms_added'=>count($created),'already_configured'=>$already,'created_room_ids'=>$created,'before'=>$before,'after'=>$after,'existing_rooms_and_evidence_preserved'=>true,'unrelated_business_tables_preserved'=>true];
    $reportFile=$work.(count($created)?'/inventory-apply-report.json':'/inventory-repeat-report.json');file_put_contents($reportFile,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(array_intersect_key($report,array_flip(['types_updated','rooms_added','already_configured','existing_rooms_and_evidence_preserved','unrelated_business_tables_preserved']))),PHP_EOL;
}catch(Throwable $e){$db->rollBack();throw $e;}
