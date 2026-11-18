<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Customer;
use App\Services\Itinerary\PlacePhoto;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ItineraryFixture;

final class TravelAssistantTest extends TestCase
{
    private array $decisions=[];
    private array $seenPlans=[];
    public function createApplication(){return ItineraryFixture::application();}
    protected function setUp():void
    {
        parent::setUp();ItineraryFixture::schema();
        foreach(['chat_session','chat_message'] as $table)if(!Schema::hasTable($table))(require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
        if(!Schema::hasColumn('attraction','slug'))(require base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'))->up();
        if(!Schema::hasColumn('hotel','address'))Schema::table('hotel',fn($t)=>$t->string('address')->nullable());
        DB::beginTransaction();Cache::flush();Http::preventStrayRequests();Sanctum::actingAs(Customer::findOrFail(41));
        config(['services.groq.api_key'=>'owned-fake','services.geoapify.api_key'=>'owned-map','services.geoapify.external_photos'=>false]);
        DB::table('location')->where('id',901)->update(['code'=>'ho-chi-minh']);
    }
    protected function tearDown():void {\Illuminate\Support\Carbon::setTestNow();\Carbon\CarbonImmutable::setTestNow();while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown();}
    private function providers(array $decisions,array $options=[]):void
    {
        $this->decisions=$decisions;$this->seenPlans=[];
        Http::fake(function($request)use($options){
            $url=$request->url();
            if(str_contains($url,'/geocode/'))return Http::response(['features'=>[['properties'=>['place_id'=>'owned-region','name'=>'Hồ Chí Minh','country_code'=>'vn','result_type'=>'city','category'=>'administrative','lon'=>106.7,'lat'=>10.77]]]]);
            if(str_contains($url,'/place-details'))return Http::response(['features'=>[['properties'=>['place_id'=>'owned-region'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]]]]]]]);
            if(str_contains($url,'/v2/places') && str_contains($request['categories'] ?? '','entertainment.theme_park')) {
                $features=[];
                foreach(range(0,3) as $i)$features[]=['properties'=>['place_id'=>'amusement-'.$i,'name'=>'Owned amusement '.$i,'country_code'=>'vn',
                    'website'=>'https://example.invalid/park-'.$i,'categories'=>[$i===3?'catering.cafe':'entertainment.theme_park']],
                    'geometry'=>['coordinates'=>[$i===2?108:106.72+$i/1000,10.77]]];
                if($options['one_amusement'] ?? false)$features=array_slice($features,0,1);
                if($options['empty_amusement'] ?? false)$features=[];
                return Http::response(['features'=>$features],$options['amusement_status'] ?? 200);
            }
            if(str_contains($url,'/v2/places') && ($options['food_lookup'] ?? false) && ($request['categories'] ?? '')==='catering.restaurant,catering.cafe')return Http::response(['features'=>array_map(fn($i)=>['properties'=>['place_id'=>'owned-food-'.$i,'name'=>'Owned food '.$i,'country_code'=>'vn','categories'=>[$i===2?'tourism.sights':'catering.restaurant']],'geometry'=>['coordinates'=>[$i===3?108:106.7+$i/1000,10.77]]],range(0,3))],$options['food_status'] ?? 200);
            if(str_contains($url,'/v2/places'))return Http::response(['features'=>array_map(fn($i)=>['properties'=>['place_id'=>'owned-place-'.$i,'name'=>($options['paid_park'] ?? false)&&$i===0?'Công viên Nước Đầm Sen':'Owned POI '.$i,'country_code'=>'vn','categories'=>['tourism.sights']],
                'geometry'=>['coordinates'=>($options['paid_park'] ?? false)&&$i===0?[106.63622,10.76798]:[$i===99?108:106.7+$i/10000,10.77]]],array_merge(range(0,59),[99]))]);
            if(str_contains($url,'/routematrix')) {$n=count($request['sources']);return Http::response(['sources_to_targets'=>array_fill(0,$n,array_fill(0,$n,['time'=>120,'distance'=>300]))]);}
            if(str_contains($url,'/v1/routing'))return Http::response(['features'=>[['geometry'=>['type'=>'LineString','coordinates'=>[[106.7,10.77],[106.705,10.775],[106.71,10.78]]],'properties'=>['distance'=>1500,'time'=>320,'apiKey'=>'MUST_NOT_LEAK']]]]);
            if(str_contains($url,'api.groq.com')) {
                if(isset($options['groq_status']))return Http::response(['error'=>['message'=>'secret-provider-error']],$options['groq_status']);
                $system=$request['messages'][0]['content'];$payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);
                if(str_contains($system,'AUREL:UNDERSTAND')) {
                    $decision=array_shift($this->decisions);if(!$decision)throw new \RuntimeException('Unexpected understanding turn');
                    return $this->providerJson($decision);
                }
                if(str_contains($system,'AUREL:ADVISE')) {
                    if(isset($options['on_advice']))($options['on_advice'])();
                    return $this->providerJson(['message'=>$options['advice_message'] ?? (($options['deny_data'] ?? false)?'Chưa có dữ liệu về khu vui chơi ở Sài Gòn.':'Mình có một vài gợi ý từ dữ liệu đã có.'),'question'=>$options['advice_question'] ?? null,
                    'place_ids'=>($options['deny_data'] ?? false)?[]:array_slice(array_column($payload['facts']['places'],'id'),0,3),'hotel_ids'=>array_slice(array_column($payload['facts']['hotels'],'id'),0,2),
                    'location_ids'=>[],'quick_replies'=>['Tôi muốn lên lịch']]);
                }
                $this->seenPlans[]=$payload;$next=0;$days=[];
                foreach($payload['dates'] as $date){$items=[];for($i=0;$i<$payload['stops_per_day']['min'];$i++)$items[]=['candidate_id'=>$next++,'duration_minutes'=>60,'description'=>'Tham quan theo nhịp độ của bạn.'];$days[]=['date'=>$date,'items'=>$items];}
                if($options['bad_candidate'] ?? false)$days[0]['items'][0]['candidate_id']=999;
                return $this->providerJson(['is_valid'=>true,'title'=>'Chuyến đi từ nguồn đã kiểm chứng','days'=>$days]);
            }
            throw new \RuntimeException('Unexpected provider in owned test');
        });
    }
    private function providerJson(array $value){return Http::response(['choices'=>[['message'=>['content'=>json_encode($value,JSON_UNESCAPED_UNICODE)]]]]);}
    private function decision(string $intent,array $patch=[],?string $quote=null,array $extra=[]):array{return [...['intent'=>$intent,'patch'=>$patch,'action_quote'=>$quote,'question'=>null],...$extra];}
    private function start():int {return $this->postJson('/api/chat/start')->assertCreated()->json('session_id');}
    private function turn(int $id,string $message,string $key='owned-turn-1'){return $this->postJson('/api/chat/'.$id.'/message',['message'=>$message],['Idempotency-Key'=>$key]);}
    private function planPatch():array{return ['destination_name'=>'Sai Gon','duration_days'=>3,'adults'=>2,'budget'=>5000000];}
    public static function durationRanges():array{return [['3 đến 4',4],['3–4',4],['3 toi 4',4],['3 -> 4',4],['3 → 4',4],['2-3',3]];}
    public function test_public_customer_text_hides_internal_ids_without_changing_grounded_component_ids():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG'])],['advice_message'=>'AUREL gợi ý Owned POI 0 (geo:owned-place-0) và địa điểm (aurel:51).']);
        $r=$this->turn($this->start(),'Sài Gòn có gì chơi?')->assertOk();$this->assertStringNotContainsString('AUREL',$r->json('message'));
        $this->assertStringNotContainsString('aurel:',$r->json('message'));$this->assertStringNotContainsString('geo:',$r->json('message'));
        $this->assertStringContainsString('Owned POI 0',$r->json('message'));
        $places=collect($r->json('assistant.components'))->firstWhere('type','places')['items'];$this->assertMatchesRegularExpression('/^geo:[a-f0-9]{20}$/',$places[0]['id']);
    }
    public function test_local_photo_source_note_is_not_mislabeled_as_the_photographer():void
    {
        $id=DB::table('attraction')->insertGetId(['location_id'=>901,'name'=>'Owned credited place','slug'=>'owned-credited-place','item_type'=>'attraction','is_active'=>true,
            'experience_category'=>'tham-quan','experience_location_code'=>'ho-chi-minh','experience_verified_at'=>now(),'experience_published_at'=>now(),'lat'=>10.77,'lng'=>106.72]);
        $note='Fixture photographer | CC BY 4.0 https://creativecommons.org/licenses/by/4.0/ | Đổi kích thước tối đa 1280px, bỏ EXIF.';
        DB::table('attraction_image')->insert(['attraction_id'=>$id,'image_url'=>'/image/placeholders/accommodation.svg','is_primary'=>true,'source_note'=>$note]);
        $place=collect(app(\App\Services\Itinerary\TravelCatalog::class)->approved(901))->firstWhere('attraction_id',$id);
        $this->assertNotNull($place['image']);$this->assertSame($note,$place['image']['attribution_note']);$this->assertArrayNotHasKey('author',$place['image']);
        $this->assertStringNotContainsString('AUREL',$place['image']['source']);$this->assertSame('aurel:'.$id,$place['id']);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('durationRanges')]
    public function test_duration_range_uses_largest_day_count_and_plans_immediately(string $range,int $days):void
    {
        $this->providers([$this->decision('preferences',['destination_name'=>'SG','budget'=>5000000],null,['question'=>'Bạn muốn 3 ngày hay 4 ngày?'])]);
        $before=DB::table('itinerary')->count();$r=$this->turn($this->start(),'Tài chính tôi có 5tr sắp xếp lịch ở Sài Gòn '.$range.' ngày xem')->assertOk()
            ->assertJsonPath('assistant.intent','plan')->assertJsonPath('assistant.state.duration_days',$days)->assertJsonPath('assistant.state.budget',5000000)->assertJsonPath('assistant.question',null);
        $timeline=collect($r->json('assistant.components'))->firstWhere('type','timeline');$this->assertCount($days,$timeline['days']);$this->assertTrue($timeline['provisional']);
        $this->assertStringContainsString('Mình chọn '.$days.' ngày',$r->json('message'));$this->assertStringContainsString('Tạm xếp cho 1 người lớn',$r->json('message'));
        $this->assertSame($before,DB::table('itinerary')->count());
    }
    public function test_duration_range_does_not_turn_an_explicit_exploration_request_into_a_plan():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG'])]);
        $r=$this->turn($this->start(),'Tôi chưa cần lên lịch Sài Gòn 3-4 ngày, chỉ muốn xem có gì chơi')->assertOk();
        $this->assertNull(collect($r->json('assistant.components'))->firstWhere('type','timeline'));$this->assertCount(0,$this->seenPlans);
        $this->assertStringContainsString('ăn uống',$r->json('assistant.question'));
        $memory=app(\App\Services\Itinerary\ConversationState::class)->merge($r->json('assistant.state'),['duration_days'=>2],[['id'=>901,'name'=>'Hồ Chí Minh']]);
        $this->assertArrayNotHasKey('duration_range',$memory);
    }
    public function test_place_advice_follows_up_with_food_or_stay_and_food_reply_queries_restaurants_only():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG']),$this->decision('explore')],['food_lookup'=>true]);$id=$this->start();
        $r=$this->turn($id,'Sài Gòn có gì chơi?')->assertOk();$this->assertStringContainsString('ăn uống hoặc nơi nghỉ',$r->json('assistant.question'));
        $this->assertSame(['Gợi ý chỗ ăn uống','Gợi ý nơi nghỉ','Xếp các điểm thành lịch trình'],$r->json('assistant.quick_replies'));
        $r=$this->turn($id,'Gợi ý chỗ ăn uống','owned-food-followup')->assertOk()->assertJsonPath('assistant.state.place_focus','food');
        $places=collect($r->json('assistant.components'))->firstWhere('type','places')['items'];$this->assertCount(2,$places);$this->assertSame(['Owned food 0','Owned food 1'],array_column($places,'name'));
        foreach($places as $place){$this->assertSame('restaurant',$place['item_type']);$this->assertSame('unknown',$place['price_status']);}
        Http::assertSent(fn($request)=>str_contains($request->url(),'/v2/places')&&($request['categories'] ?? '')==='catering.restaurant,catering.cafe');
    }
    public function test_advice_does_not_offer_lodging_when_customer_already_has_accommodation():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG','stay_mode'=>'own_accommodation','duration_days'=>4])],['advice_question'=>'Bạn muốn 3 ngày hay 4 ngày?']);
        $r=$this->turn($this->start(),'Đã có chỗ ở, Sài Gòn có gì chơi?')->assertOk();
        $this->assertStringContainsString('ăn uống',$r->json('assistant.question'));$this->assertStringNotContainsString('nơi nghỉ',$r->json('assistant.question'));
        $this->assertSame(['Gợi ý chỗ ăn uống','Xếp các điểm thành lịch trình'],$r->json('assistant.quick_replies'));
    }
    public function test_suoi_tien_metadata_matches_exact_city_and_prices_preserve_child_conditions():void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-29 09:00:00');$visits=app(\App\Services\Itinerary\VisitInformation::class);
        $place=['name'=>'Khu du lịch Suối Tiên','coordinates'=>[106.802173,10.863885]];$data=$visits->enrich($place,'Hồ Chí Minh');
        $this->assertStringContainsString('công viên khô',$data['description']);$this->assertSame('Syced',$data['image']['author']);$this->assertSame('CC0 1.0',$data['image']['license']);
        $this->assertSame('2026-10-29',$data['ticket']['checked_at']);$this->assertSame(180000,$data['ticket']['adult_reference']);
        $quote=$visits->estimate($data['ticket'],['adults'=>1,'children'=>[['height_cm'=>120],['height_cm'=>80],['height_cm'=>70]]]);
        $this->assertSame(280000,$quote['amount']);$this->assertSame(1,$quote['unknown_children']);
        $this->assertArrayNotHasKey('image',$visits->enrich($place,'Khánh Hòa'));
        $this->assertArrayNotHasKey('ticket',$visits->enrich([...$place,'coordinates'=>[109.2,12.2]],'Hồ Chí Minh'));
        foreach(['suoi-tien.jpg','dam-sen-water-park.jpg'] as $name){$path=public_path('image/ai-places/'.$name);$this->assertFileExists($path);$this->assertLessThan(500*1024,filesize($path));$this->assertSame(IMAGETYPE_JPEG,getimagesize($path)[2]);}
        \Illuminate\Support\Carbon::setTestNow('2026-11-13 09:00:00');$expired=$visits->enrich($place,'Hồ Chí Minh');$this->assertArrayNotHasKey('ticket',$expired);$this->assertSame($data['image'],$expired['image']);
    }
    private function stream(int $id,string $message,string $key='owned-stream-1',string $requestId='owned-attempt-1'):array
    {
        $response=$this->postJson('/api/chat/'.$id.'/message',['message'=>$message],['Accept'=>'text/event-stream','Idempotency-Key'=>$key,'X-Aurel-Request-Id'=>$requestId])
            ->assertOk()->assertHeader('Content-Type','text/event-stream; charset=UTF-8')->assertHeader('X-Accel-Buffering','no');
        $events=[];
        foreach(explode("\n\n",trim($response->streamedContent())) as $frame) {
            if(!preg_match('/^event: (\w+)\ndata: (.+)$/s',$frame,$match))continue;
            $payload=json_decode($match[2],true,512,JSON_THROW_ON_ERROR);$this->assertSame($requestId,$payload['request_id']);
            $events[]=['event'=>$match[1],...$payload];
        }
        $this->assertSame(range(1,count($events)),array_column($events,'sequence'));
        return $events;
    }

    public function test_streamed_simple_answer_has_only_real_processing_context_and_model_stages():void
    {
        $this->providers([$this->decision('out_of_scope')]);$id=$this->start();$events=$this->stream($id,'Giải bài toán giúp tôi');
        $codes=array_column(array_filter($events,fn($e)=>$e['event']==='progress'),'code');
        $this->assertSame(['processing_request','reading_context','considering'],$codes);$this->assertSame('result',$events[array_key_last($events)]['event']);
        Http::assertSentCount(1);
        $this->assertCount(2,ChatSession::find($id)->messages);
        foreach(ChatSession::find($id)->messages as $entry){$this->assertArrayNotHasKey('progress',$entry->entities_json ?? []);$this->assertStringNotContainsString('reading_context',$entry->content);}
    }
    public function test_streamed_place_and_hotel_search_do_not_claim_room_prices_or_itinerary_generation():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG']),$this->decision('hotels')]);$id=$this->start();
        $places=$this->stream($id,'Sài Gòn có gì chơi?');$codes=array_column($places,'code');
        $this->assertContains('searching_places',$codes);$this->assertNotContains('creating_itinerary',$codes);$this->assertNotContains('checking_rooms',$codes);
        $hotels=$this->stream($id,'Gợi ý khách sạn','owned-stream-hotel','owned-attempt-hotel');$codes=array_column($hotels,'code');
        $this->assertContains('searching_hotels',$codes);$this->assertNotContains('checking_rooms',$codes);$this->assertNotContains('calculating_route',$codes);
    }
    public function test_streamed_plan_and_day_revision_emit_actual_generator_validation_and_route_stages():void
    {
        $this->providers([$this->decision('plan',$this->planPatch(),'Lên lịch'),$this->decision('revise_day',[],'Ngày 2 nhẹ hơn',['target_day'=>2,'reduce_stops'=>true])]);$id=$this->start();
        $events=$this->stream($id,'Lên lịch');$codes=array_column($events,'code');
        foreach(['creating_itinerary','searching_places','calculating_route','checking_itinerary'] as $code)$this->assertContains($code,$codes);
        $before=count(Http::recorded());$this->stream($id,'Lên lịch','owned-stream-1','owned-attempt-replayed');$this->assertCount($before,Http::recorded());
        $events=$this->stream($id,'Ngày 2 nhẹ hơn','owned-stream-day','owned-attempt-day');$edits=array_values(array_filter($events,fn($e)=>($e['code'] ?? null)==='revising_day'));
        $this->assertNotEmpty($edits);foreach($edits as $edit)$this->assertSame(2,$edit['day']);
        $this->assertNotContains('creating_itinerary',array_column($events,'code'));
    }
    public function test_duration_question_has_relevant_suggestions_and_completes_without_planning():void
    {
        $this->providers([$this->decision('plan',['destination_name'=>'SG'],'lên lịch')]);$id=$this->start();
        $events=$this->stream($id,'Tôi mới tới Sài Gòn, bạn hãy lên lịch cho tôi xem');$last=$events[array_key_last($events)];
        $this->assertSame('result',$last['event']);$this->assertSame('Bạn muốn dành mấy ngày cho chuyến đi này?',$last['result']['message']);
        $this->assertSame(['Đi 1 ngày','Đi 2 ngày','Đi 3 ngày'],$last['result']['assistant']['quick_replies']);
        $this->assertNotContains('creating_itinerary',array_column($events,'code'));Http::assertSentCount(1);
    }
    public function test_preferences_do_not_fetch_places_or_hotels_just_because_an_old_stay_mode_requests_aurel():void
    {
        $this->providers([$this->decision('preferences',['adults'=>2]),$this->decision('explore')]);$id=$this->start();
        ChatSession::findOrFail($id)->update(['context_json'=>['state'=>['destination_name'=>'Hồ Chí Minh','location_id'=>901,'supported'=>true,'stay_mode'=>'aurel_hotel','place_focus'=>'entertainment']]]);
        $events=$this->stream($id,'2 người lớn');$codes=array_column($events,'code');
        $this->assertNotContains('searching_places',$codes);$this->assertNotContains('searching_hotels',$codes);$this->assertNotContains('creating_itinerary',$codes);
        $this->assertStringNotContainsString('đã tra cứu',$events[array_key_last($events)]['result']['message']);
        $events=$this->stream($id,'Khu vui chơi nào?','owned-preference-next','owned-preference-attempt');
        $this->assertContains('searching_places',array_column($events,'code'));$this->assertNotContains('searching_hotels',array_column($events,'code'));
    }
    public function test_streamed_provider_error_has_one_terminal_event_and_never_exposes_raw_error():void
    {
        $this->providers([],['groq_status'=>429]);$id=$this->start();$events=$this->stream($id,'Xin tư vấn');
        $last=$events[array_key_last($events)];$this->assertSame('error',$last['event']);$this->assertSame('groq_quota',$last['code']);
        $this->assertStringNotContainsString('secret-provider-error',json_encode($events));$this->assertCount(0,ChatSession::find($id)->messages);
        $this->assertCount(1,array_filter($events,fn($e)=>in_array($e['event'],['result','error'],true)));
    }
    public function test_deadline_and_cancellation_prevent_committing_a_late_answer():void
    {
        $clock=0.0;$cancelled=false;$events=[];
        $progress=new \App\Services\Itinerary\TravelProgress('owned-clock-attempt',function($type,$data)use(&$events){$events[]=[$type,$data];},1000,
            function()use(&$cancelled){return $cancelled;},function()use(&$clock){return $clock;});
        $this->providers([$this->decision('preferences',['adults'=>2])],['on_advice'=>function()use(&$clock){$clock=1001;}]);$id=$this->start();
        try{app(\App\Services\Itinerary\TravelAssistantService::class)->turn(Customer::findOrFail(41),$id,'2 người','owned-deadline-key',$progress);$this->fail('Late answer committed');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('request_timeout',json_decode($error->getResponse()->getContent(),true)['code']);}
        $this->assertCount(0,ChatSession::find($id)->messages);
        $clock=0;$cancelled=true;
        try{$progress->emit('searching_places');$this->fail('Cancelled request accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('request_cancelled',json_decode($error->getResponse()->getContent(),true)['code']);}
        $cancelled=false;Http::swap(new \Illuminate\Http\Client\Factory);
        $this->providers([$this->decision('preferences',['adults'=>2])],['on_advice'=>function()use(&$cancelled){$cancelled=true;}]);
        try{app(\App\Services\Itinerary\TravelAssistantService::class)->turn(Customer::findOrFail(41),$id,'2 người','owned-cancelled-key',$progress);$this->fail('Cancelled answer committed');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('request_cancelled',json_decode($error->getResponse()->getContent(),true)['code']);}
        $this->assertCount(0,ChatSession::find($id)->messages);
    }
    public function test_progress_allowlist_strips_arbitrary_fields_and_stops_after_terminal_event():void
    {
        $events=[];$progress=new \App\Services\Itinerary\TravelProgress('owned-sanitized-attempt',function($type,$data)use(&$events){$events[]=['event'=>$type,...$data];},1000,fn()=>false);
        $progress->emit('unknown_code',['prompt'=>'never-display','day'=>'secret']);$progress->emit('revising_day',['day'=>2,'api_key'=>'never-display']);
        $progress->result(['message'=>'Done']);$progress->error('late','late');
        $this->assertCount(3,$events);$this->assertSame('considering',$events[0]['code']);$this->assertSame(2,$events[1]['day']);
        $this->assertStringNotContainsString('never-display',json_encode($events));
    }

    public function test_explore_then_change_facts_then_plan_keeps_context_and_does_not_force_a_form():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'Sai Gon']),$this->decision('preferences',['adults'=>2,'budget'=>5000000,'duration_days'=>3]),$this->decision('plan',[],'Lên lịch luôn')]);
        $id=$this->start();$before=DB::table('itinerary')->count();
        $this->turn($id,'Sai Gon co gi choi?')->assertOk()->assertJsonPath('assistant.intent','explore')->assertJsonPath('assistant.state.location_id',901);
        $this->assertSame($before,DB::table('itinerary')->count());
        $this->turn($id,'2 người 5 triệu 3 ngày','owned-turn-2')->assertOk()->assertJsonPath('assistant.state.budget',5000000);
        $result=$this->turn($id,'Lên lịch luôn','owned-turn-3')->assertOk()->json();
        $plan=collect($result['assistant']['components'])->firstWhere('type','timeline');$this->assertCount(3,$plan['days']);
        $this->assertTrue($plan['provisional']);$this->assertNull($plan['days'][0]['date']);$this->assertNull($plan['trip_id']);
        $this->assertSame(5000000,$this->seenPlans[0]['budget_vnd']);$this->assertSame(2,$this->seenPlans[0]['party']['adults']);
        $this->assertSame($before,DB::table('itinerary')->count());$this->assertNotEmpty($plan['assumptions']);
        $this->assertCount(6,ChatSession::find($id)->messages);
    }
    public function test_amusement_followup_searches_online_despite_many_unrelated_local_places_and_repairs_false_empty_advice():void
    {
        for($i=0;$i<14;$i++)DB::table('attraction')->insert(['location_id'=>901,'name'=>'Owned historical site '.$i,'item_type'=>'attraction','slug'=>'owned-historical-'.$i,
            'is_active'=>true,'experience_category'=>'tham-quan','experience_location_code'=>'ho-chi-minh','experience_verified_at'=>now(),'experience_published_at'=>now(),'lat'=>10.77,'lng'=>106.72]);
        $this->providers([$this->decision('explore')],['deny_data'=>true]);$id=$this->start();
        ChatSession::findOrFail($id)->update(['context_json'=>['state'=>['destination_name'=>'Hồ Chí Minh','location_id'=>901,'supported'=>true]]]);
        $before=DB::table('attraction')->count();$trips=DB::table('itinerary')->count();
        $r=$this->turn($id,'Có khu vui chơi nào ko')->assertOk()->assertJsonPath('assistant.state.location_id',901)->assertJsonPath('assistant.state.place_focus','entertainment');
        $places=collect($r->json('assistant.components'))->firstWhere('type','places')['items'];
        $this->assertCount(2,$places);$this->assertSame(['Owned amusement 0','Owned amusement 1'],array_column($places,'name'));
        $this->assertStringNotContainsString('Chưa có dữ liệu',$r->json('message'));
        $this->assertSame($before,DB::table('attraction')->count());$this->assertSame($trips,DB::table('itinerary')->count());
        Http::assertSent(fn($request)=>str_contains($request->url(),'/v2/places') && str_contains($request['categories'] ?? '','entertainment.water_park') && $request['filter']==='place:owned-region');
    }
    public function test_amusement_lookup_has_a_separate_cache_and_accepts_a_single_sourced_place():void
    {
        $this->providers([],['one_amusement'=>true]);$catalog=app(\App\Services\Itinerary\TravelCatalog::class);
        $state=['location_id'=>901,'place_focus'=>'entertainment'];$catalog->pool(901);
        $facts=$catalog->facts($state,'explore');$again=$catalog->facts($state,'explore');
        $this->assertCount(1,$facts['places']);$this->assertSame('complete',$facts['lookup']['status']);$this->assertSame($facts['places'],$again['places']);
        $this->assertCount(1,Http::recorded(fn($request)=>str_contains($request->url(),'/v2/places') && str_contains($request['categories'] ?? '','entertainment.theme_park')));
    }
    public function test_online_lookup_outage_is_not_reported_as_no_amusement_places():void
    {
        $this->providers([$this->decision('explore',['destination_name'=>'SG'])],['amusement_status'=>429]);
        $r=$this->turn($this->start(),'Có khu vui chơi nào ko')->assertOk();
        $this->assertStringContainsString('chưa tra cứu được nguồn trực tuyến',$r->json('message'));
        $this->assertStringContainsString('không có nghĩa',$r->json('message'));
        $this->assertEmpty(array_filter($r->json('assistant.components'),fn($c)=>$c['type']==='places'));
        $this->assertCount(1,Http::recorded(fn($request)=>str_contains($request->url(),'api.groq.com')));
    }
    public function test_empty_online_results_and_missing_destination_are_distinct_from_an_outage():void
    {
        $this->providers([$this->decision('explore'),$this->decision('explore',['destination_name'=>'SG'])],['empty_amusement'=>true]);$id=$this->start();
        $r=$this->turn($id,'Có khu vui chơi nào ko')->assertOk();$this->assertStringContainsString('tỉnh/thành nào',$r->json('message'));
        Http::assertSentCount(1);
        $r=$this->turn($id,'Ở Sài Gòn','owned-destination-answer')->assertOk();$this->assertStringContainsString('đã tra cứu nhưng chưa tìm thấy',$r->json('message'));
        $this->assertStringNotContainsString('chưa tra cứu được',$r->json('message'));
    }
    public function test_missing_essential_question_is_asked_once_then_preliminary_plan_is_created():void
    {
        $this->providers([$this->decision('plan',[],'Lên lịch'),$this->decision('preferences',['destination_name'=>'SG','duration_days'=>2]),$this->decision('preferences',['budget'=>5000000,'adults'=>2])]);$id=$this->start();
        $this->turn($id,'Lên lịch')->assertOk()->assertJsonPath('assistant.question','Bạn muốn lên lịch tại địa phương nào?');
        $this->turn($id,'SG 2 ngày','owned-answer')->assertOk()->assertJsonPath('assistant.question','Bạn dự tính ngân sách khoảng bao nhiêu cho cả chuyến đi?')
            ->assertJsonPath('assistant.quick_replies',['Khoảng 2 triệu','Khoảng 5 triệu','Chưa chốt, cứ lên lịch trước']);
        $this->assertCount(0,$this->seenPlans);
        $r=$this->turn($id,'2 người, 5 triệu','owned-budget-answer')->assertOk()->json('assistant.components');
        $this->assertCount(2,collect($r)->firstWhere('type','timeline')['days']);
    }
    public function test_budget_can_be_deferred_and_party_is_asked_once_before_a_plan():void
    {
        $this->providers([$this->decision('plan',['destination_name'=>'SG','duration_days'=>2,'budget_flexible'=>true,'allow_assumptions'=>true],'Lên lịch'),
            $this->decision('preferences',['budget_flexible'=>true]),$this->decision('preferences',['adults'=>2])]);$id=$this->start();
        $this->turn($id,'Lên lịch')->assertOk()->assertJsonPath('assistant.question','Bạn dự tính ngân sách khoảng bao nhiêu cho cả chuyến đi?');
        $this->turn($id,'Chưa chốt, cứ lên lịch trước','defer-budget')->assertOk()->assertJsonPath('assistant.question','Chuyến này có bao nhiêu người lớn đi cùng bạn?');
        $r=$this->turn($id,'2 người lớn','party-answer')->assertOk();$timeline=collect($r->json('assistant.components'))->firstWhere('type','timeline');
        $this->assertCount(2,$timeline['days']);$this->assertTrue($timeline['budget']['flexible']);$this->assertNull($timeline['budget']['limit']);
        $this->assertFalse(ChatSession::find($id)->context_json['pending_plan']);
    }
    public function test_model_repeating_an_old_action_quote_does_not_block_a_pending_budget_answer():void
    {
        $this->providers([$this->decision('plan',['destination_name'=>'SG','duration_days'=>1,'adults'=>2],'Lên lịch'),
            $this->decision('plan',['budget'=>2000000],'Lên lịch')]);$id=$this->start();
        $this->turn($id,'Lên lịch')->assertOk()->assertJsonPath('assistant.question','Bạn dự tính ngân sách khoảng bao nhiêu cho cả chuyến đi?');
        $r=$this->turn($id,'Khoảng 2 triệu','budget-old-quote')->assertOk();$this->assertSame(1,count(collect($r->json('assistant.components'))->firstWhere('type','timeline')['days']));
        $this->assertSame(2000000,$r->json('assistant.state.budget'));
    }
    public function test_answering_budget_for_an_old_plan_keeps_the_requested_revision_day():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'budget'=>null,'budget_flexible'=>true],'Lên lịch'),
            $this->decision('revise_day',['budget_flexible'=>false],'Ngày 2 nhẹ hơn',['target_day'=>2,'reduce_stops'=>true]),
            $this->decision('preferences',['budget'=>3000000])]);$id=$this->start();
        $first=$this->turn($id,'Lên lịch trước, ngân sách tính sau')->assertOk()->json('assistant.components.0');
        $this->turn($id,'Ngày 2 nhẹ hơn','budget-before-edit')->assertOk()->assertJsonPath('assistant.question','Bạn dự tính ngân sách khoảng bao nhiêu cho cả chuyến đi?');
        $edited=$this->turn($id,'Khoảng 3 triệu','budget-for-edit')->assertOk()->assertJsonPath('assistant.intent','revise_day')->json('assistant.components.0');
        $this->assertEqualsWithDelta($first['days'][0],$edited['days'][0],1e-10);$this->assertEqualsWithDelta($first['days'][2],$edited['days'][2],1e-10);$this->assertSame(3000000,$edited['budget']['limit']);
        $this->assertCount(1,array_filter($edited['days'][1]['items'],fn($item)=>$item['kind']==='place'));
    }
    public function test_visit_prices_require_matching_place_location_coordinates_and_fresh_review():void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-28 09:00:00');$visits=app(\App\Services\Itinerary\VisitInformation::class);
        $park=['name'=>'Creative Park','coordinates'=>[106.71253,10.78005]];
        $ticket=$visits->enrich($park,'Hồ Chí Minh')['ticket'];$this->assertSame('free',$ticket['status']);$this->assertStringContainsString('moc.gov.vn',$ticket['source_url']);
        $this->assertArrayNotHasKey('ticket',$visits->enrich($park,'Hà Nội'));
        $this->assertArrayNotHasKey('ticket',$visits->enrich([...$park,'coordinates'=>[106.1,10.1]],'Hồ Chí Minh'));
        $this->assertArrayNotHasKey('ticket',$visits->enrich([...$park,'name'=>'Creative Park Cafe'],'Hồ Chí Minh'));
        $this->assertSame(0,$visits->estimate($ticket,['adults'=>2])['amount']);
        $this->assertNull($visits->estimate(null,['adults'=>2]));
        \Illuminate\Support\Carbon::setTestNow('2026-11-12 09:00:00');
        $this->assertArrayNotHasKey('ticket',$visits->enrich($park,'Hồ Chí Minh'));$this->assertNull($visits->estimate($ticket,['adults'=>2]));
    }
    public function test_child_ticket_uses_explicit_height_and_keeps_unknown_height_unpriced():void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-28 09:00:00');$visits=app(\App\Services\Itinerary\VisitInformation::class);
        $ticket=$visits->enrich(['name'=>'Dam Sen Water Park','coordinates'=>[106.63622,10.76798]],'Hồ Chí Minh')['ticket'];
        $q=$visits->estimate($ticket,['adults'=>2,'children'=>[['age'=>5,'height_cm'=>120],['age'=>4]],'infants'=>[['height_cm'=>70]]]);
        $this->assertSame(620000,$q['amount']);$this->assertSame(1,$q['unknown_children']);$this->assertSame('estimated',$q['status']);
        $this->assertSame('adults_and_known_heights',$q['scope']);$this->assertStringContainsString('damsenwaterpark.com.vn',$q['source_url']);
        $memory=app(\App\Services\Itinerary\ConversationState::class)->merge([],['children'=>[['age'=>5,'unit'=>'years','height_cm'=>120]]],[['id'=>901,'name'=>'Hồ Chí Minh']]);
        $this->assertSame(120,$memory['children'][0]['height_cm']);
    }
    public function test_ticket_prices_persist_with_sources_and_known_hours_adjust_the_schedule():void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-28 09:00:00');\Carbon\CarbonImmutable::setTestNow('2026-10-28 09:00:00');
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2026-10-29','duration_days'=>1,'children'=>[['age'=>5,'unit'=>'years']]],'Lên lịch')],['paid_park'=>true]);
        $r=$this->turn($this->start(),'Lên lịch')->assertOk();$timeline=collect($r->json('assistant.components'))->firstWhere('type','timeline');
        $this->assertNotNull($timeline);$park=collect($timeline['days'][0]['items'])->firstWhere('title','Công viên Nước Đầm Sen');
        $this->assertSame('09:00',$park['start_time']);$this->assertSame(440000,$park['ticket_estimate']['amount']);$this->assertSame(1,$timeline['budget']['children_needing_height']);
        $trip=$timeline['trip_id'];$cost=DB::table('itinerary_cost_line')->where('itinerary_id',$trip)->whereNotNull('unit_amount')->first();
        $this->assertSame(440000,(int)$cost->unit_amount);$this->assertSame('estimated',$cost->price_status);$this->assertStringContainsString('damsenwaterpark.com.vn',$cost->source_reference);
        $this->assertGreaterThanOrEqual(2,DB::table('itinerary_cost_line')->where('itinerary_id',$trip)->where('price_status','unknown')->count());
        $snapshot=json_decode(DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip)->where('provider_place_id','owned-place-0')->value('snapshot'),true);
        $this->assertSame('2026-10-28',$snapshot['ticket']['checked_at']);$this->assertSame(1,$snapshot['ticket_estimate']['unknown_children']);
    }
    public function test_regular_closed_day_is_avoided_before_the_model_selects_places():void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-28 09:00:00');\Carbon\CarbonImmutable::setTestNow('2026-10-28 09:00:00');
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2026-11-03','duration_days'=>1],'Lên lịch')],['paid_park'=>true]);
        $response=$this->turn($this->start(),'Lên lịch')->assertOk();
        $this->assertNotContains('Công viên Nước Đầm Sen',array_column($this->seenPlans[0]['candidates'],'name'));
        $timeline=collect($response->json('assistant.components'))->firstWhere('type','timeline');
        $this->assertNotContains('Công viên Nước Đầm Sen',array_column($timeline['days'][0]['items'],'title'));
    }
    public function test_day_route_is_owned_read_only_cached_and_sanitized():void
    {
        $this->providers([$this->decision('plan',$this->planPatch(),'Lên lịch')]);$id=$this->start();$r=$this->turn($id,'Lên lịch')->assertOk();$mid=$r->json('message_id');
        $this->assertIsInt($mid);$counts=[DB::table('itinerary')->count(),DB::table('chat_message')->count()];$path="/api/chat/$id/messages/$mid/route/2";
        $route=$this->getJson($path)->assertOk()->assertJsonPath('geometry.type','LineString')->assertJsonPath('mode','drive')->assertJsonPath('time_seconds',320);
        $this->assertStringNotContainsString('MUST_NOT_LEAK',$route->getContent());$this->assertStringNotContainsString('apiKey',$route->getContent());
        $before=count(Http::recorded());$this->getJson($path)->assertOk();$this->assertCount($before,Http::recorded());
        $this->getJson("/api/chat/$id/messages/$mid/route/99")->assertNotFound();
        Sanctum::actingAs(Customer::findOrFail(42));$this->getJson($path)->assertNotFound();Sanctum::actingAs(Customer::findOrFail(41));
        $message=\App\Models\ChatMessage::find($mid);$entities=$message->entities_json;$entities['assistant']['components'][0]['days'][1]['items'][0]['coordinates']=[108,12];$message->update(['entities_json'=>$entities]);
        $this->getJson($path)->assertStatus(422);$this->assertCount($before,Http::recorded());
        $this->assertSame($counts,[DB::table('itinerary')->count(),DB::table('chat_message')->count()]);
    }
    public function test_route_failure_never_returns_a_fabricated_straight_line():void
    {
        Http::fake(fn()=>Http::response(['features'=>[['geometry'=>['type'=>'LineString','coordinates'=>[[106.7,10.77],[999,10.78]]]]]]));
        try{app(\App\Services\Itinerary\GeoapifyClient::class)->route([[106.7,10.77],[106.71,10.78]],'drive');$this->fail('Invalid shape accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $e){$body=json_decode($e->getResponse()->getContent(),true);$this->assertSame('invalid_route_shape',$body['code']);$this->assertArrayNotHasKey('geometry',$body);}
    }
    public function test_dated_plan_persists_once_and_day_revision_preserves_other_days_and_prices_remain_unknown():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2030-01-01'],'Lên lịch'),$this->decision('revise_day',[],'Ngày 2 nhiều điểm quá',['target_day'=>2,'reduce_stops'=>true])]);
        $id=$this->start();$r=$this->turn($id,'Lên lịch')->assertOk()->json();$trip=collect($r['assistant']['components'])->firstWhere('type','timeline')['trip_id'];$this->assertNotNull($trip);
        $first=DB::table('itinerary_item')->where('itinerary_id',$trip)->whereIn('day_number',[1,3])->orderBy('id')->get()->toJson();
        $count=count(Http::recorded());$this->turn($id,'Lên lịch')->assertOk()->assertJsonPath('assistant.components.0.trip_id',$trip)->assertJsonPath('assistant.context_revision',1);$this->assertCount($count,Http::recorded());
        $r=$this->turn($id,'Ngày 2 nhiều điểm quá','owned-revise')->assertOk()->json();
        $this->assertSame($first,DB::table('itinerary_item')->where('itinerary_id',$trip)->whereIn('day_number',[1,3])->orderBy('id')->get()->toJson());
        $this->assertSame(1,DB::table('itinerary_item')->where('itinerary_id',$trip)->where('day_number',2)->whereNotNull('place_snapshot_id')->count());
        $this->assertSame(0,DB::table('itinerary_cost_line')->where('itinerary_id',$trip)->whereNotNull('unit_amount')->count());
        $this->assertSame(2,collect($r['assistant']['components'])->firstWhere('type','timeline')['revision']);
        $this->assertSame(2,ChatSession::find($id)->messages()->where('role','user')->count());
    }
    public function test_unsupported_destination_never_uses_previous_destination_and_out_of_scope_does_not_mutate_state():void
    {
        $this->providers([$this->decision('preferences',$this->planPatch()),$this->decision('plan',['destination_name'=>'Nghệ An'],'Lên lịch Nghệ An'),$this->decision('out_of_scope',['destination_name'=>'Hà Nội'])]);$id=$this->start();
        $this->turn($id,'Tôi đi Sài Gòn')->assertOk();$before=DB::table('itinerary')->count();
        $this->turn($id,'Lên lịch Nghệ An','owned-unsupported')->assertOk()->assertJsonPath('assistant.state.location_id',null)->assertJsonPath('assistant.state.supported',false);
        $this->turn($id,'Giải bài toán','owned-offtopic')->assertOk()->assertJsonPath('assistant.intent','out_of_scope')->assertJsonPath('assistant.state.destination_name','Nghệ An');
        $this->assertSame($before,DB::table('itinerary')->count());$this->assertCount(0,$this->seenPlans);
    }
    public function test_aurel_approved_catalog_is_used_but_drafts_wrong_mapping_and_outside_region_are_excluded():void
    {
        $base=['location_id'=>901,'item_type'=>'attraction','name'=>'Owned approved','slug'=>'owned-approved','is_active'=>true,'experience_category'=>'tham-quan','experience_location_code'=>'ho-chi-minh','experience_verified_at'=>now(),'experience_published_at'=>now(),'lat'=>10.77,'lng'=>106.72];
        DB::table('attraction')->insert($base);
        foreach([['slug'=>'owned-draft','experience_published_at'=>null],['slug'=>'owned-wrong','experience_location_code'=>'ha-noi'],['slug'=>'owned-out','lng'=>108],['slug'=>'owned-hidden','is_active'=>false],['slug'=>'owned-unverified','experience_verified_at'=>null]] as $extra)DB::table('attraction')->insert([...$base,...$extra]);
        $this->providers([$this->decision('plan',$this->planPatch(),'Lên lịch')]);$this->turn($this->start(),'Lên lịch')->assertOk();
        $names=array_column($this->seenPlans[0]['candidates'],'name');$this->assertSame('Owned approved',$names[0]);$this->assertSame(1,count(array_filter($names,fn($n)=>$n==='Owned approved')));
        $this->assertNotContains('Owned POI 99',$names);
    }
    public function test_six_day_plan_uses_batched_real_routing_without_repeating_places():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'duration_days'=>6],'Lên lịch')]);$r=$this->turn($this->start(),'Lên lịch')->assertOk()->json('assistant.components');
        $days=collect($r)->firstWhere('type','timeline')['days'];$this->assertCount(6,$days);$this->assertCount(2,$this->seenPlans);
        $names=[];foreach($days as $day)foreach($day['items'] as $item)if($item['kind']==='place')$names[]=$item['title'];$this->assertSame(count($names),count(array_unique($names)));
    }
    public function test_day_trip_and_family_include_real_travel_and_rest_without_inventing_child_age():void
    {
        $this->providers([$this->decision('plan',['destination_name'=>'SG','stay_mode'=>'day_trip','adults'=>2,'children_count'=>1,'infants_count'=>1,'arrival_time'=>'10:00','departure_time'=>'17:00','budget_flexible'=>true],'Lên lịch')]);
        $result=$this->turn($this->start(),'Lên lịch trước, ngân sách tính sau')->assertOk()->json('assistant.components');$plan=collect($result)->firstWhere('type','timeline');
        $this->assertCount(1,$plan['days']);$this->assertSame('10:00',$plan['days'][0]['items'][0]['start_time']);
        $this->assertNull($this->seenPlans[0]['party']['children'][0]['age']);$this->assertNull($plan['budget']['limit']);
        $this->assertContains('break',array_column($plan['days'][0]['items'],'kind'));
    }
    public function test_invalid_plan_rolls_back_chat_context_and_all_itinerary_writes():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2030-01-01'],'Lên lịch')],['bad_candidate'=>true]);$id=$this->start();$before=DB::table('itinerary')->count();
        $r=$this->turn($id,'Lên lịch')->assertOk();$this->assertNotNull($r->json('code'));$this->assertSame($before,DB::table('itinerary')->count());$this->assertCount(0,ChatSession::find($id)->messages);
    }
    public function test_owner_idempotency_conflicts_and_closed_session_are_enforced():void
    {
        $this->providers([$this->decision('preferences',['adults'=>2])]);$id=$this->start();$this->turn($id,'2 người')->assertOk();
        $this->turn($id,'3 người')->assertConflict();Sanctum::actingAs(Customer::findOrFail(42));$this->turn($id,'2 người')->assertNotFound();
        Sanctum::actingAs(Customer::findOrFail(41));$this->putJson('/api/chat/'.$id.'/close')->assertOk();$this->turn($id,'3 người','owned-closed')->assertConflict();
    }
    public function test_deleting_removes_chat_messages_memory_and_replay_copies_without_deleting_saved_trips():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2030-01-01'],'Lên lịch')]);
        $id=$this->postJson('/api/chat/start',[],['Idempotency-Key'=>'delete-owned-start'])->assertCreated()->json('session_id');$other=$this->start();
        $r=$this->turn($id,'Lên lịch')->assertOk();$trip=$r->json('assistant.components.0.trip_id');$this->assertNotNull($trip);
        $this->getJson("/api/chat/$id")->assertOk()->assertJsonCount(2,'messages')->assertJsonPath('context_json.state.adults',2);
        $calls=count(Http::recorded());$snapshots=DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip)->count();
        $this->deleteJson("/api/chat/$id")->assertOk()->assertJsonPath('message','Hội thoại đã xóa');
        $this->assertNull(ChatSession::find($id));$this->assertSame(0,DB::table('chat_message')->where('session_id',$id)->count());
        $this->assertSame(0,DB::table('itinerary_write_request')->where('customer_id',41)->where(function($q)use($id){$q->where('operation','chat:'.$id)->orWhere(fn($s)=>$s->where('operation','chat:start')->where('response_body->session_id',$id));})->count());
        $this->assertNotNull(ChatSession::find($other));$this->assertSame($other,$this->getJson('/api/chat')->assertOk()->json('0.id'));
        $this->getJson("/api/chat/$id")->assertNotFound();$this->turn($id,'Lên lịch')->assertNotFound();
        $this->assertSame(1,DB::table('itinerary')->where('id',$trip)->count());$this->assertSame($snapshots,DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip)->count());
        $this->assertCount($calls,Http::recorded());
    }
    public function test_deleting_enforces_ownership_and_rejects_an_in_flight_reply():void
    {
        $id=$this->start();$lock=Cache::lock('aurel:chat:'.$id,600);$this->assertTrue($lock->get());
        try{$this->deleteJson("/api/chat/$id")->assertConflict();$this->assertSame('active',ChatSession::find($id)->status);}finally{$lock->release();}
        Sanctum::actingAs(Customer::findOrFail(42));$this->deleteJson("/api/chat/$id")->assertNotFound();
        $this->assertSame('active',ChatSession::find($id)->status);Http::assertNothingSent();
    }
    public function test_failed_delete_rolls_back_messages_memory_and_replay_rows():void
    {
        $this->providers([$this->decision('preferences',['adults'=>2])]);$id=$this->start();$this->turn($id,'2 người')->assertOk();
        $context=ChatSession::find($id)->context_json;$replays=DB::table('itinerary_write_request')->where('operation','chat:'.$id)->count();$fail=true;
        DB::listen(function($query)use(&$fail){if($fail&&str_starts_with($query->sql,'delete from `chat_session`')){$fail=false;throw new \RuntimeException('Owned deletion rollback fixture');}});
        $this->deleteJson("/api/chat/$id")->assertStatus(500);
        $this->assertEquals($context,ChatSession::find($id)->context_json);$this->assertSame(2,DB::table('chat_message')->where('session_id',$id)->count());
        $this->assertSame($replays,DB::table('itinerary_write_request')->where('operation','chat:'.$id)->count());
        $this->deleteJson("/api/chat/$id")->assertOk();$this->assertNull(ChatSession::find($id));
    }
    public function test_action_without_a_quote_never_creates_a_plan():void
    {
        $this->providers([$this->decision('plan',$this->planPatch())]);$this->turn($this->start(),'Tôi muốn đi chơi')->assertStatus(422)->assertJsonPath('code','action_needs_clarification');$this->assertCount(0,$this->seenPlans);
    }
    public function test_cached_proposal_is_not_replayed_after_one_of_its_places_is_recorded_closed():void
    {
        $this->providers([$this->decision('plan',$this->planPatch(),'Lên lịch')]);$session=$this->start();
        $reply=$this->turn($session,'Lên lịch','owned-proposal-before-closure')->assertOk()->json();
        $timeline=collect($reply['assistant']['components'])->firstWhere('type','timeline');$place=collect($timeline['days'][0]['items'])->firstWhere('kind','place');
        app(\App\Services\Itinerary\PlaceAvailability::class)->observe([['name'=>$place['title'],'coordinates'=>$place['coordinates'],
            'operating_status'=>'closed_permanently','address'=>$place['address']]],'Hồ Chí Minh');
        $calls=count(Http::recorded());
        $this->turn($session,'Lên lịch','owned-proposal-before-closure')->assertUnprocessable()->assertJsonPath('code','stale_closed_place');
        $this->assertCount($calls,Http::recorded());
    }
    public function test_negated_day_edit_is_not_promoted_to_an_action():void
    {
        $this->providers([$this->decision('preferences',[])]);
        $result=app(\App\Services\Itinerary\TravelDialogue::class)->understand('Ngày 1 tôi chưa muốn thêm lịch buổi chiều',
            ['has_plan'=>true,'plan_days'=>2],[],[['id'=>901,'name'=>'Hồ Chí Minh']]);
        $this->assertSame('preferences',$result['intent']);$this->assertArrayNotHasKey('revision_period',$result);
    }
    public function test_concurrent_turn_and_stale_trip_revision_are_rejected_without_overwriting_data():void
    {
        $this->providers([$this->decision('plan',[...$this->planPatch(),'start_date'=>'2030-01-01'],'Lên lịch'),$this->decision('revise_day',[],'Sửa ngày 2',['target_day'=>2])]);
        $id=$this->start();$lock=Cache::lock('aurel:chat:'.$id,600);$this->assertTrue($lock->get());
        $this->turn($id,'Lên lịch')->assertConflict();Http::assertNothingSent();$lock->release();
        $trip=$this->turn($id,'Lên lịch')->assertOk()->json('assistant.components.0.trip_id');
        DB::table('itinerary')->where('id',$trip)->update(['revision'=>2]);$before=DB::table('itinerary_item')->where('itinerary_id',$trip)->orderBy('id')->get()->toJson();
        $this->turn($id,'Sửa ngày 2','owned-stale')->assertConflict();$this->assertSame($before,DB::table('itinerary_item')->where('itinerary_id',$trip)->orderBy('id')->get()->toJson());
        $this->assertCount(2,ChatSession::find($id)->messages);
    }
    public function test_unverified_price_and_unknown_reference_in_advice_are_rejected():void
    {
        $dialogue=app(\App\Services\Itinerary\TravelDialogue::class);
        Http::fake(fn()=> $this->providerJson(['message'=>'Giá vé 200000 VND.','question'=>null,'place_ids'=>[]]));
        try{$dialogue->advise('Giá vé?',[],['places'=>[]],[],null);$this->fail('Unsafe price accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $e){$this->assertSame('unverified_claim',json_decode($e->getResponse()->getContent(),true)['code']);}
        Http::swap(new \Illuminate\Http\Client\Factory);Http::fake(fn()=> $this->providerJson(['message'=>'Điểm gợi ý.','place_ids'=>['made-up-id']]));
        try{$dialogue->advise('Gợi ý',[],['places'=>[]],[],null);$this->fail('Invented reference accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $e){$this->assertSame('ungrounded_advice',json_decode($e->getResponse()->getContent(),true)['code']);}
    }
    public function test_model_cannot_invent_room_checks_routes_or_online_lookup_progress():void
    {
        foreach(['Mình đã kiểm tra phòng trống.','Mình đã tính quãng đường cho bạn.','Mình đã tìm kiếm trên mạng.'] as $message) {
            Http::swap(new \Illuminate\Http\Client\Factory);Http::fake(fn()=> $this->providerJson(['message'=>$message,'place_ids'=>[]]));
            try{app(\App\Services\Itinerary\TravelDialogue::class)->advise('Tư vấn',[],['places'=>[]],[],null);$this->fail('Unsupported action claimed');}
            catch(\Illuminate\Http\Exceptions\HttpResponseException $error){$this->assertSame('unverified_action',json_decode($error->getResponse()->getContent(),true)['code']);}
        }
    }
    public function test_boundary_normalization_requires_same_region_name_country_osm_relation_and_containing_center():void
    {
        $geometry=['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]],[[106.1,10.1],[106.2,10.1],[106.2,10.2],[106.1,10.2],[106.1,10.1]]]];
        Http::fake(fn()=>Http::response(['features'=>[['geometry'=>$geometry,'properties'=>['place_id'=>'reencoded','name'=>'Hồ Chí Minh','country_code'=>'vn','datasource'=>['raw'=>['osm_id'=>1973756,'osm_type'=>'r']]]]]]));
        $geo=app(\App\Services\Itinerary\GeoapifyClient::class);$region=['place_id'=>'geocode-original','name'=>'Hồ Chí Minh','lon'=>106.7,'lat'=>10.77];
        $this->assertSame($geometry,$geo->boundary($region));$this->assertFalse($geo->contains($geometry,[106.15,10.15]));$this->assertFalse($geo->contains($geometry,[108,12]));
        Cache::flush();
        try{$geo->boundary([...$region,'name'=>'Hà Nội']);$this->fail('Wrong administrative name accepted');}
        catch(\Illuminate\Http\Exceptions\HttpResponseException $e){$this->assertSame(422,$e->getResponse()->getStatusCode());}
    }
    public function test_real_dates_and_age_units_are_validated_and_supported_transport_clears_old_request():void
    {
        $memory=app(\App\Services\Itinerary\ConversationState::class);$locations=[['id'=>901,'name'=>'Hồ Chí Minh']];
        $state=$memory->merge(['requested_transport'=>'máy bay'],['destination_name'=>'SG','start_date'=>'2030-01-01','duration_days'=>10,'transport'=>'drive','children'=>[['age'=>36,'unit'=>'months']]],$locations);
        $this->assertSame('2030-01-10',$state['end_date']);$this->assertNull($state['requested_transport']);$this->assertSame(1,$state['children_count']);
        foreach([['infants'=>[['age'=>5,'unit'=>'years']]],['start_date'=>'2020-01-01'],['adults'=>8,'children_count'=>1]] as $patch) {
            try{$memory->merge($state,$patch,$locations);$this->fail('Invalid trip fact accepted');}
            catch(\Illuminate\Http\Exceptions\HttpResponseException $e){$this->assertSame(422,$e->getResponse()->getStatusCode());}
        }
    }
    public function test_external_photo_requires_matching_entity_coordinates_and_license_and_fails_without_blocking_chat():void
    {
        config(['services.geoapify.external_photos'=>true]);
        $photo=['name'=>'Owned landmark','coordinates'=>[106.7,10.77],'wikidata_id'=>'Q123'];
        $entity=['id'=>'Q123','labels'=>['en'=>['value'=>'Owned landmark']],'claims'=>['P18'=>[['mainsnak'=>['datavalue'=>['value'=>'Owned.jpg']]]],
            'P625'=>[['mainsnak'=>['datavalue'=>['value'=>['latitude'=>10.77,'longitude'=>106.7]]]]]]];
        Http::fake(['*wikidata.org*'=>Http::response(['entities'=>['Q123'=>$entity]]),'*commons.wikimedia.org*'=>Http::response(['query'=>['pages'=>[['imageinfo'=>[['mime'=>'image/jpeg','thumburl'=>'https://upload.wikimedia.org/owned.jpg','descriptionurl'=>'https://commons.wikimedia.org/wiki/File:Owned.jpg',
            'extmetadata'=>['Artist'=>['value'=>'<a>Author</a>'],'LicenseShortName'=>['value'=>'CC BY-SA 4.0'],'LicenseUrl'=>['value'=>'https://creativecommons.org/licenses/by-sa/4.0/']]]]]]]])]);
        $photoEvents=[];$photoProgress=new \App\Services\Itinerary\TravelProgress('owned-photo-attempt',function($type,$data)use(&$photoEvents){$photoEvents[]=$data['code'] ?? '';},1000,fn()=>false);
        $r=app(PlacePhoto::class)->forPlace($photo,$photoProgress);$this->assertSame('Author',$r['author']);$this->assertSame('CC BY-SA 4.0',$r['license']);
        $before=count(Http::recorded());$this->assertSame($r,app(PlacePhoto::class)->forPlace($photo,$photoProgress));$this->assertCount($before,Http::recorded());
        $this->assertSame(['searching_images'],$photoEvents);
        $this->assertNull(app(PlacePhoto::class)->forPlace([...$photo,'coordinates'=>[108,12]]));
        Cache::flush();Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*wikidata.org*'=>Http::response(['entities'=>['Q123'=>$entity]]),'*commons.wikimedia.org*'=>Http::response(['query'=>['pages'=>[['imageinfo'=>[['mime'=>'image/jpeg','thumburl'=>'https://thumb.wikimedia.org/owned.jpg','descriptionurl'=>'https://commons.wikimedia.org/wiki/File:Owned.jpg','extmetadata'=>['Artist'=>['value'=>'Author']]]]]]]])]);
        $this->assertNull(app(PlacePhoto::class)->forPlace($photo));
        Cache::flush();Http::swap(new \Illuminate\Http\Client\Factory);Http::fake(['*'=>Http::response([],429)]);$this->assertNull(app(PlacePhoto::class)->forPlace($photo));
    }
}
