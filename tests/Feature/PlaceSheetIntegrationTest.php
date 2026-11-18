<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Places\{SheetsReader,SheetPlaceMapper,SheetPlaceSync,SheetPlaceCatalog,MapsUrl};
use App\Services\Itinerary\{TravelCatalog,TravelDialogue,PlacePhoto};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Cache,DB,Http,Schema};
use Laravel\Sanctum\Sanctum;
use Tests\Support\ItineraryFixture;

final class PlaceSheetIntegrationTest extends TestCase
{
    private string $credentialFile;
    private array $sheet;
    private bool $failData=false;
    private bool $hoursTabExists=false;
    private bool $failHours=false;
    private array $modelFacts=[];
    private ?array $advice=null;
    public function createApplication(){return ItineraryFixture::application();}
    protected function setUp():void
    {
        parent::setUp();ItineraryFixture::schema();
        if(!Schema::hasTable('travel_source_place'))(require base_path('database/migrations/2026_10_31_000001_create_travel_source_places.php'))->up();
        if(!Schema::hasColumn('travel_source_place','opening_schedule'))(require base_path('database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php'))->up();
        foreach(['chat_session','chat_message'] as $table)if(!Schema::hasTable($table))(require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
        if(!Schema::hasColumn('attraction','slug'))(require base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'))->up();
        DB::beginTransaction();Cache::flush();Http::preventStrayRequests();Sanctum::actingAs(Customer::findOrFail(41));
        $options=['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA];$opensslConfig=dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if(is_file($opensslConfig))$options['config']=$opensslConfig;
        $key=openssl_pkey_new($options);openssl_pkey_export($key,$pem,null,$options);
        $this->credentialFile=tempnam(sys_get_temp_dir(),'angel-sheet-test-');
        file_put_contents($this->credentialFile,json_encode(['type'=>'service_account','client_email'=>'owned@example.invalid','private_key'=>$pem]));
        config(['services.place_sheet'=>['spreadsheet_id'=>'owned-sheet','data_tab'=>'Dia_diem','guide_tab'=>'Huong_dan','range'=>'A1:ZZ','location_name'=>'Hồ Chí Minh','credentials'=>$this->credentialFile,'candidate_limit'=>12],
            'services.groq.api_key'=>'owned-groq','services.geoapify.external_photos'=>false]);
        $this->sheet=['guide'=>array_map(fn($label)=>[$label,'Giới hạn nguồn công khai'],['Giới hạn xác minh','Link Google Maps','Tọa độ và ảnh','Giá','Điểm đến trong DB']),
            'data'=>[['ID','Tên địa điểm / chi nhánh','Nhóm trải nghiệm','Khu vực','Số điện thoại','Giá thấp (VND)','Giá cao (VND)','Google Maps URL','Trạng thái theo nguồn','Thẻ sở thích'],
            ['DL001','Owned café','Cà phê – check-in','Trung tâm','0123 456 789','','','https://www.google.com/maps/search/?api=1&query=Owned%20cafe','Có thông tin hoạt động trên nguồn công khai','yên tĩnh; làm việc'],
            ['DL002','Owned park','Vui chơi – thiên nhiên','Ngoại ô','','0','80.000','https://goo.gl/maps/ownedPark','Có thông tin hoạt động trên nguồn công khai','gia đình']]];
        Http::fake(function($request){
            if($request->url()==='https://oauth2.googleapis.com/token')return Http::response(['access_token'=>'owned-read-only','expires_in'=>3600]);
            if(str_contains($request->url(),'sheets.googleapis.com')){
                $url=urldecode($request->url());
                if(!str_contains($url,'/values/'))return Http::response(['sheets'=>array_map(fn($title)=>['properties'=>['title'=>$title]],$this->hoursTabExists?['Dia_diem','Huong_dan','Gio_hoat_dong']:['Dia_diem','Huong_dan'])]);
                if(str_contains($url,'Gio_hoat_dong'))return Http::response(['values'=>$this->sheet['hours'] ?? []],$this->failHours?503:200);
                return str_contains($url,'Huong_dan')?Http::response(['values'=>$this->sheet['guide']]):Http::response($this->failData?[]:['values'=>$this->sheet['data']],$this->failData?503:200);
            }
            if(str_contains($request->url(),'api.groq.com')){
                $system=$request['messages'][0]['content'];
                if(str_contains($system,'AUREL:UNDERSTAND'))$body=['intent'=>'explore','patch'=>['destination_name'=>'Hồ Chí Minh','place_focus'=>'food'],'question'=>null];
                else {$payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);$this->modelFacts=$payload['facts'];$body=$this->advice ?? ['message'=>'Bạn có thể tham khảo quán này.','place_ids'=>[$payload['facts']['places'][0]['id']],'hotel_ids'=>[],'location_ids'=>[],'question'=>null,'quick_replies'=>[]];}
                return Http::response(['choices'=>[['message'=>['content'=>json_encode($body)]]]]);
            }
            throw new \RuntimeException('Unexpected provider in owned Sheet test.');
        });
    }
    protected function tearDown():void {if(isset($this->credentialFile))unlink($this->credentialFile);while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown();}
    private function sync(bool $dry=false):array{return app(SheetPlaceSync::class)->run($dry);}

    public function test_reader_uses_read_only_scope_and_reads_guide_before_data():void
    {
        $this->assertCount(3,app(SheetsReader::class)->read()['data']);
        $requests=Http::recorded()->map(fn($pair)=>$pair[0])->all();
        $parts=explode('.',$requests[0]['assertion']);$claims=json_decode(base64_decode(strtr($parts[1],'-_','+/')),true);
        $this->assertSame(SheetsReader::SCOPE,$claims['scope']);$this->assertSame('https://oauth2.googleapis.com/token',$claims['aud']);
        $this->assertStringContainsString('Huong_dan',urldecode($requests[1]->url()));$this->assertStringContainsString('Dia_diem',urldecode($requests[2]->url()));
        $this->assertSame('GET',$requests[1]->method());$this->assertSame('GET',$requests[2]->method());$this->assertSame('FORMATTED_VALUE',$requests[2]['valueRenderOption']);
    }
    public function test_header_mapping_preserves_phone_nulls_and_zero_prices_after_reordering():void
    {
        $this->sheet['data']=array_map('array_reverse',$this->sheet['data']);$mapped=app(SheetPlaceMapper::class)->map($this->sheet)['valid'];
        $this->assertSame('0123456789',$mapped[0]['phone']);$this->assertNull($mapped[0]['price_min']);$this->assertNull($mapped[0]['latitude']);$this->assertNull($mapped[0]['licensed_image_url']);
        $this->assertSame(0,$mapped[1]['price_min']);$this->assertSame(80000,$mapped[1]['price_max']);
    }
    public function test_dry_run_writes_nothing_and_second_sync_is_idempotent():void
    {
        $old=DB::table('attraction')->get()->toJson();$this->assertSame(2,$this->sync(true)['inserted']);$this->assertSame(0,DB::table('travel_source_place')->count());
        $this->assertSame(2,$this->sync()['inserted']);$before=DB::table('travel_source_place')->get()->toJson();$again=$this->sync();
        $this->assertSame(0,$again['inserted']);$this->assertSame(2,$again['unchanged']);$this->assertSame($before,DB::table('travel_source_place')->get()->toJson());$this->assertSame($old,DB::table('attraction')->get()->toJson());
    }
    public function test_failed_read_keeps_last_successful_snapshot():void
    {
        $this->sync();$before=DB::table('travel_source_place')->get()->toJson();$this->failData=true;
        try{$this->sync();$this->fail('Failed read accepted');}catch(\RuntimeException $error){$this->assertStringContainsString('HTTP 503',$error->getMessage());}
        $this->assertSame($before,DB::table('travel_source_place')->get()->toJson());
    }
    public function test_closed_invalid_and_removed_rows_are_disabled_only_in_their_source():void
    {
        $this->sync();$other=(array)DB::table('travel_source_place')->first();unset($other['id']);$other['source']='other:owned';DB::table('travel_source_place')->insert($other);
        $this->sheet['data'][1][8]='Đã đóng cửa';$this->sheet['data'][2][7]='https://evil.invalid/maps';$result=$this->sync();
        $this->assertSame(1,$result['skipped']);$this->assertSame(2,$result['disabled']);
        $this->assertSame(0,DB::table('travel_source_place')->where('source',SheetPlaceSync::source())->where('is_enabled',true)->count());
        $this->assertTrue((bool)DB::table('travel_source_place')->where('source','other:owned')->value('is_enabled'));
    }
    public function test_duplicate_ids_abort_before_updates():void
    {
        $this->sync();$before=DB::table('travel_source_place')->get()->toJson();$this->sheet['data'][2][0]='DL001';
        try{$this->sync();$this->fail('Duplicate source IDs accepted');}catch(\RuntimeException $error){$this->assertStringContainsString('trùng ID',$error->getMessage());}
        $this->assertSame($before,DB::table('travel_source_place')->get()->toJson());
    }
    public function test_catalog_filters_location_need_disabled_and_keeps_missing_data_missing():void
    {
        $this->sync();$catalog=app(SheetPlaceCatalog::class);$cafes=$catalog->candidates(901,'food',[],'quán cà phê yên tĩnh');
        $this->assertCount(1,$cafes);$this->assertSame('Owned café',$cafes[0]['name']);$this->assertNull($cafes[0]['price_reference']['min']);$this->assertNull($cafes[0]['image']);$this->assertNull($cafes[0]['coordinates']);
        $this->assertSame([],$catalog->candidates(72,'food'));DB::table('travel_source_place')->where('external_id','DL001')->update(['is_enabled'=>false]);$this->assertSame([],$catalog->candidates(901,'food',[],'cà phê'));
        $this->assertNull(app(PlacePhoto::class)->forPlace(['provider'=>'google_sheet','coordinates'=>[106.7,10.7]]));
    }
    public function test_chat_groq_ids_resolve_to_original_record_without_sending_sensitive_place_fields():void
    {
        $this->sync();$session=$this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $response=$this->postJson('/api/chat/'.$session.'/message',['message'=>'Gợi ý quán cà phê tại Hồ Chí Minh'],['Idempotency-Key'=>'sheet-test-message'])->assertOk();
        $place=collect($response->json('assistant.components'))->firstWhere('type','places')['items'][0];
        $this->assertSame($this->sheet['data'][1][7],$place['maps_url']);$this->assertSame('0123456789',$place['phone']);$this->assertSame('google_sheet',$place['provider']);
        $this->assertNull($place['coordinates']);$this->assertNull($place['image']);$this->assertCount(1,$this->modelFacts['places']);
        foreach(['maps_url','phone','coordinates','price_reference'] as $field)$this->assertArrayNotHasKey($field,$this->modelFacts['places'][0]);
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'geoapify') || str_contains($request->url(),'wikimedia'));
    }
    public function test_unknown_groq_id_is_rejected_and_generated_links_are_not_shown():void
    {
        $this->sync();$facts=app(TravelCatalog::class)->facts(['location_id'=>901,'destination_name'=>'Hồ Chí Minh','place_focus'=>'food'],'explore',null,'cà phê');
        $this->advice=['message'=>'Xem https://evil.invalid/map hoặc gọi 0999999999.','place_ids'=>['sheet:invented'],'question'=>null,'quick_replies'=>[]];
        try{app(TravelDialogue::class)->advise('Gợi ý',['destination_name'=>'Hồ Chí Minh'],$facts,[],null);$this->fail('Invented ID accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('ungrounded_advice',json_decode($error->getResponse()->getContent(),true)['code']);}
        $this->advice['place_ids']=[$facts['places'][0]['id']];$result=app(TravelDialogue::class)->advise('Gợi ý',['destination_name'=>'Hồ Chí Minh'],$facts,[],null);
        $this->assertStringNotContainsString('evil.invalid',$result['message']);$this->assertStringNotContainsString('0999999999',$result['message']);
        foreach(['Quán ở 11.2345, 108.9876.','Quán miễn phí.','Đồ uống 50k.','Quán phục vụ từ 8h đến 17h.'] as $fabrication){$this->advice['message']=$fabrication;
            $safe=app(TravelDialogue::class)->advise('Gợi ý',['destination_name'=>'Hồ Chí Minh'],$facts,[],null);$this->assertNotSame($fabrication,$safe['message']);}
    }
    public function test_maps_urls_are_checked_by_protocol_host_and_maps_path():void
    {
        foreach(['https://www.google.com/maps/search/?api=1&query=Owned','https://maps.app.goo.gl/owned','https://goo.gl/maps/owned'] as $url)$this->assertSame($url,MapsUrl::validate($url));
        foreach(['http://www.google.com/maps/','https://www.google.com.evil.invalid/maps','https://google.com@evil.invalid/maps','https://www.google.com/url?q=evil','javascript:alert(1)','https://goo.gl/evil'] as $url)$this->assertNull(MapsUrl::validate($url));
    }

    public function test_replay_and_history_resolve_current_maps_and_hide_disabled_sheet_places_without_deleting_history():void
    {
        $this->sync();$session=$this->postJson('/api/chat/start')->assertCreated()->json('session_id');$payload=['message'=>'Gợi ý cà phê tại Hồ Chí Minh'];$headers=['Idempotency-Key'=>'sheet-current-record'];
        $reply=$this->postJson('/api/chat/'.$session.'/message',$payload,$headers)->assertOk();$messageId=$reply->json('message_id');
        $newMap='https://maps.app.goo.gl/updatedOwnedBranch';DB::table('travel_source_place')->where('external_id','DL001')->update(['maps_url'=>$newMap]);
        $replay=$this->postJson('/api/chat/'.$session.'/message',$payload,$headers)->assertOk();
        $this->assertSame($newMap,collect($replay->json('assistant.components'))->firstWhere('type','places')['items'][0]['maps_url']);
        $original=DB::table('chat_message')->where('id',$messageId)->value('content');DB::table('travel_source_place')->where('external_id','DL001')->update(['is_enabled'=>false]);
        $history=$this->getJson('/api/chat/'.$session)->assertOk();$bot=collect($history->json('messages'))->firstWhere('id',$messageId);
        $this->assertSame([],collect($bot['entities_json']['assistant']['components'])->firstWhere('type','places')['items']);$this->assertStringContainsString('ngừng gợi ý',$bot['content']);
        $this->assertSame($original,DB::table('chat_message')->where('id',$messageId)->value('content'));
        $this->postJson('/api/chat/'.$session.'/message',$payload,$headers)->assertStatus(422)->assertJsonPath('code','stale_closed_place');
    }

    private function withHours():void
    {
        config(['services.place_sheet.hours_tab'=>'Gio_hoat_dong']);$this->hoursTabExists=true;
        $this->sheet['hours']=[['place_id','rule_type','weekday','date','state','opens_at','closes_at','end_day_offset','timezone','data_quality'],
            ['DL001','weekly','1','','open','08:00','12:00','0','Asia/Ho_Chi_Minh','reference'],['DL001','weekly','1','','open','14:00','18:00','0','Asia/Ho_Chi_Minh','reference'],
            ['DL001','weekly','7','','closed','','','','Asia/Ho_Chi_Minh','reference']];
    }
    public function test_full_tab_reads_a_51st_place_and_updates_it_without_duplicates_or_old_catalog_changes():void
    {
        for($i=3;$i<=51;$i++){$row=$this->sheet['data'][1];$row[0]='DL'.str_pad((string)$i,3,'0',STR_PAD_LEFT);$row[1]='Owned extra '.$i;$this->sheet['data'][]=$row;}
        $old=DB::table('attraction')->get()->toJson();$report=$this->sync();$this->assertSame(51,$report['rows_read']);$this->assertSame(51,$report['inserted']);
        $id=DB::table('travel_source_place')->where('external_id','DL051')->value('id');$this->sheet['data'][51][1]='Owned updated 51st place';$updated=$this->sync();
        $this->assertSame(1,$updated['updated']);$this->assertSame(0,$updated['inserted']);$this->assertSame($id,DB::table('travel_source_place')->where('external_id','DL051')->value('id'));
        $this->assertSame(51,$this->sync()['unchanged']);$this->assertSame($old,DB::table('attraction')->get()->toJson());
        Http::assertSent(fn($request)=>str_contains(urldecode($request->url()),"/values/'Dia_diem'?") && !str_contains(urldecode($request->url()),'A1'));
    }
    public function test_hours_sync_changes_only_hours_and_preserves_last_hours_if_optional_tab_is_missing_or_api_fails():void
    {
        $this->withHours();$this->sync();$before=DB::table('travel_source_place')->where('external_id','DL001')->first();$this->assertNotEmpty($before->opening_schedule);
        $this->sheet['hours'][1][5]='09:00';$this->assertSame(1,$this->sync()['updated']);$after=DB::table('travel_source_place')->where('external_id','DL001')->first();
        $this->assertSame($before->id,$after->id);$this->assertSame($before->phone,$after->phone);$this->assertSame($before->hours_text,$after->hours_text);$this->assertNull($after->price_min);$this->assertSame(2,$this->sync()['unchanged']);
        $this->hoursTabExists=false;$this->assertSame('missing',$this->sync()['hours_tab_state']);$this->assertSame($after->opening_schedule,DB::table('travel_source_place')->where('id',$after->id)->value('opening_schedule'));
        $this->hoursTabExists=true;$this->failHours=true;$state=DB::table('travel_source_place')->get()->toJson();
        try{$this->sync();$this->fail('Hours API failure accepted');}catch(\RuntimeException $error){$this->assertStringContainsString('HTTP 503',$error->getMessage());}
        $this->assertSame($state,DB::table('travel_source_place')->get()->toJson());
    }
    public function test_sync_mutex_prevents_overlap_before_any_api_call():void
    {
        $lock=Cache::lock('places.sheet.sync.'.hash('sha256',SheetPlaceSync::source()),300);$this->assertTrue($lock->get());
        try{$this->sync();$this->fail('Concurrent sync accepted');}catch(\RuntimeException $error){$this->assertStringContainsString('lượt đồng bộ',$error->getMessage());}finally{$lock->release();}
        Http::assertNothingSent();
    }
    public function test_demo_or_orphan_hours_rows_do_not_erase_existing_hours():void
    {
        $this->withHours();$this->sync();$before=DB::table('travel_source_place')->get()->toJson();
        foreach($this->sheet['hours'] as $index=>&$row)if($index>0)$row[0]='DEMO_PLACE_001';unset($row);
        try{$this->sync();$this->fail('Demo hours accepted for real IDs');}catch(\RuntimeException $error){$this->assertStringContainsString('không có ID hợp lệ',$error->getMessage());}
        $this->assertSame($before,DB::table('travel_source_place')->get()->toJson());
    }
    public function test_manual_dates_time_changes_and_reordering_use_current_backend_hours_without_changing_the_guests_times():void
    {
        $this->withHours();$this->sync();$id=DB::table('travel_source_place')->where('external_id','DL001')->value('id');
        $item=['item_type'=>'restaurant','title'=>'Owned café','place_name'=>null,'description'=>null,'source_place_id'=>$id,'start_time'=>'11:00','end_time'=>'15:00','estimated_cost'=>null];
        $data=['location_id'=>901,'duration_days'=>2,'people'=>2,'stay_mode'=>'own_accommodation','estimated_budget'=>null,'visit_start_date'=>'2026-11-08','days'=>[['items'=>[$item]],['items'=>[]]]];
        $this->postJson('/api/v2/itineraries/planner/check-hours',$data)->assertOk()->assertJsonPath('days.0.items.0.opening_check.status','closed');
        $saved=$this->postJson('/api/v2/itineraries/planner',$data,['Idempotency-Key'=>'hours-save-initial'])->assertCreated()->assertJsonPath('data.provisional',false)->assertJsonPath('data.days.0.items.0.opening_check.status','closed')->json('data');
        $data['days']=[['items'=>[]],['items'=>[array_merge($item,['id'=>$saved['days'][0]['items'][0]['id']])]]];$data['expected_revision']=1;
        $changed=$this->putJson('/api/v2/itineraries/'.$saved['id'].'/planner',$data,['Idempotency-Key'=>'hours-save-monday'])->assertOk()->assertJsonPath('data.days.1.items.0.opening_check.status','outside_hours')->assertJsonPath('data.days.1.items.0.start_time','11:00')->json('data');
        $data['days'][1]['items'][0]['start_time']='14:00';$data['expected_revision']=2;
        $this->putJson('/api/v2/itineraries/'.$saved['id'].'/planner',$data,['Idempotency-Key'=>'hours-save-fixed'])->assertOk()->assertJsonPath('data.days.1.items.0.opening_check.status','within_hours');
        $data['visit_start_date']='2026-11-09';$data['expected_revision']=3;
        $this->putJson('/api/v2/itineraries/'.$saved['id'].'/planner',$data,['Idempotency-Key'=>'hours-save-shift-date'])->assertOk()->assertJsonPath('data.start_date','2026-11-09')->assertJsonPath('data.days.1.date','2026-11-10')->assertJsonPath('data.days.1.items.0.opening_check.status','unknown');
        $this->assertSame(1,DB::table('itinerary_item')->where('itinerary_id',$saved['id'])->count());
    }
    public function test_groq_400_is_logged_with_safe_metadata_and_is_not_automatically_retried():void
    {
        \Illuminate\Support\Facades\Log::spy();
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
        Http::fake(fn()=>Http::response(['error'=>['code'=>'json_validate_failed','message'=>'private-provider-body','failed_generation'=>'private-generated-content']],400,['x-request-id'=>'req_owned_test']));
        try{app(TravelDialogue::class)->advise('Gợi ý',[],['places'=>[]],[],null);$this->fail('400 accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('groq_unavailable',json_decode($error->getResponse()->getContent(),true)['code']);}
        Http::assertSentCount(1);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with('Groq chat request failed',\Mockery::on(fn($context)=>$context['status']===400 && $context['provider_error_code']==='json_validate_failed' && $context['request_id']==='req_owned_test' && !str_contains(json_encode($context),'private-') && !str_contains(json_encode($context),'owned-groq')));
    }
    public function test_scheduler_declares_fifteen_minutes_with_overlap_protection_without_running_jobs():void
    {
        $schedule=app(\Illuminate\Console\Scheduling\Schedule::class);$event=collect($schedule->events())->first(fn($event)=>str_contains($event->command ?? '','places:sync-sheet'));
        $this->assertNotNull($event);$this->assertSame('*/15 * * * *',$event->expression);$this->assertTrue($event->withoutOverlapping);$this->assertSame(10,$event->expiresAt);
    }
}
