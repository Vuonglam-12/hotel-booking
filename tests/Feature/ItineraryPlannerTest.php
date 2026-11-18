<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ItineraryFixture;

final class ItineraryPlannerTest extends TestCase
{
    private static int $sequence = 0;
    public function createApplication() { return ItineraryFixture::application(); }
    protected function setUp(): void
    {
        parent::setUp(); ItineraryFixture::schema();
        if (!Schema::hasColumn('itinerary', 'is_provisional')) (require base_path('database/migrations/2026_10_29_000001_add_provisional_itinerary_flag.php'))->up();
        foreach (['chat_session', 'chat_message'] as $table) if (!Schema::hasTable($table)) (require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
        DB::beginTransaction(); Http::preventStrayRequests(); Sanctum::actingAs(Customer::findOrFail(41));
    }
    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) DB::rollBack(); parent::tearDown();
    }
    private function payload(array $changes = []): array
    {
        return array_replace(['location_id'=>901, 'duration_days'=>2, 'people'=>2, 'estimated_budget'=>null,
            'stay_mode'=>'own_accommodation', 'days'=>[['items'=>[$this->item()]], ['items'=>[]]]], $changes);
    }
    private function item(array $changes = []): array
    {
        return array_replace(['item_type'=>'activity', 'title'=>'Dạo phố', 'place_name'=>'Đường sách', 'description'=>'Ghé thăm và nghỉ ngơi',
            'start_time'=>'09:00', 'end_time'=>'10:00', 'estimated_cost'=>null], $changes);
    }
    private function send(string $method, string $path, array $data, ?string $key = null)
    {
        return $this->json($method, '/api/v2/itineraries'.$path, $data, ['Idempotency-Key'=>$key ?? 'planner-test-'.(++self::$sequence)]);
    }
    public function test_compact_trip_accepts_empty_budget_and_keeps_legacy_and_sourced_destination(): void
    {
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $this->assertTrue($trip['provisional']); $this->assertNull($trip['budget']['limit']);
        $this->assertSame('Đường sách',$trip['days'][0]['items'][0]['place_name']);
        $this->assertSame('saved',$trip['status']); $this->assertSame('manually_edited',$trip['readiness']['validation_status']);
        $this->assertSame('Legacy item preserved',DB::table('itinerary_item')->where('id',1)->value('title'));
        Http::assertNothingSent();
    }
    public function test_add_edit_move_reorder_delete_and_reload_activities(): void
    {
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $item = $this->item(['id'=>$trip['days'][0]['items'][0]['id'], 'title'=>'Đọc sách', 'start_time'=>'10:00', 'end_time'=>'11:00', 'estimated_cost'=>120000]);
        $payload = $this->payload(['expected_revision'=>1, 'days'=>[['items'=>[]], ['items'=>[$this->item(['title'=>'Ăn sáng']),$item]]]]);
        $updated = $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.revision',2)->json('data');
        $this->assertSame(['Ăn sáng','Đọc sách'],array_column($updated['days'][1]['items'],'title'));
        $this->assertSame(120000,$updated['days'][1]['items'][1]['estimated_cost']);
        $this->assertSame(120000,$updated['budget']['estimated_total']);
        $payload['expected_revision'] = 2; $payload['days'][1]['items'] = [];
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk();
        $this->assertSame(0,DB::table('itinerary_cost_line')->where('itinerary_id',$trip['id'])->count());
        $this->assertSame(0,DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->count());
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonCount(0,'data.days.1.items');
    }
    public function test_duration_changes_retain_existing_activity_id_and_validate_day_trip(): void
    {
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $payload = $this->payload(['duration_days'=>1, 'stay_mode'=>'day_trip', 'days'=>[['items'=>[$this->item(['id'=>$trip['days'][0]['items'][0]['id']])]]], 'expected_revision'=>1]);
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.total_days',1)->assertJsonPath('data.total_nights',0)->assertJsonPath('data.stay_mode','day_trip');
        $this->send('POST','/planner',$this->payload(['stay_mode'=>'day_trip']))->assertUnprocessable()->assertJsonValidationErrors('duration_days');
    }
    public function test_writes_are_idempotent_revision_checked_and_owned(): void
    {
        $trip = $this->send('POST','/planner',$this->payload(),'same-create')->assertCreated()->json('data');
        $this->send('POST','/planner',$this->payload(),'same-create')->assertCreated()->assertJsonPath('data.id',$trip['id']);
        $payload = $this->payload(['expected_revision'=>1]);
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk();
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertConflict();
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertNotFound();
        $this->getJson('/api/v2/itineraries/'.$trip['id'].'/pdf')->assertNotFound();
    }
    public function test_foreign_activity_is_rejected_and_entire_write_rolled_back(): void
    {
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $this->send('PUT','/'.$trip['id'].'/planner',$this->payload(['people'=>3,'expected_revision'=>1,
            'days'=>[['items'=>[$this->item(['title'=>'Must roll back']),$this->item(['id'=>1])]],['items'=>[]]]]))->assertUnprocessable();
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.revision',1)->assertJsonPath('data.party.total',2)->assertJsonPath('data.days.0.items.0.title','Dạo phố');
    }
    public function test_invalid_hours_counts_duplicate_ids_and_budget_are_rejected(): void
    {
        foreach ([['estimated_budget'=>0], ['people'=>9], ['duration_days'=>31], ['days'=>[]],
            ['days'=>[['items'=>[$this->item(['end_time'=>'08:00'])]],['items'=>[]]]],
            ['days'=>[['items'=>[$this->item(['id'=>1]),$this->item(['id'=>1])]],['items'=>[]]]]] as $changes) {
            $this->send('POST','/planner',$this->payload($changes))->assertUnprocessable();
        }
        $this->assertSame(1,DB::table('itinerary')->count());
    }
    public function test_missing_ai_configuration_never_fabricates_an_itinerary(): void
    {
        config(['services.groq.api_key'=>null]);
        $data = $this->payload(); unset($data['days']);
        $this->send('POST','/planner/generate',$data)->assertStatus(503);
        $this->assertSame(1,DB::table('itinerary')->count()); Http::assertNothingSent();
    }
    private function planningProviders(bool $revision = false, array $patch = [], ?string $quote = null): void
    {
        config(['services.groq.api_key'=>'fixture-ai', 'services.geoapify.api_key'=>'fixture-map', 'services.geoapify.external_photos'=>false]);
        Http::fake(function ($request) use ($revision, $patch, $quote) {
            $url = $request->url();
            if (str_contains($url,'/geocode/')) return Http::response(['features'=>[['properties'=>['place_id'=>'planner-region','name'=>'Hồ Chí Minh','country_code'=>'vn','result_type'=>'city','category'=>'administrative','lon'=>106.7,'lat'=>10.77]]]]);
            if (str_contains($url,'/place-details')) return Http::response(['features'=>[['properties'=>['place_id'=>'planner-region'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]]]]]]]);
            if (str_contains($url,'/v2/places')) return Http::response(['features'=>array_map(fn ($i)=>['properties'=>['place_id'=>'planner-place-'.$i,'name'=>'Sourced test place '.$i,'country_code'=>'vn','categories'=>['tourism.sights']],'geometry'=>['coordinates'=>[106.7+$i/1000,10.77]]],range(0,9))]);
            if (str_contains($url,'/routematrix')) return Http::response(['sources_to_targets'=>array_fill(0,count($request['sources']),array_fill(0,count($request['targets']),['time'=>60,'distance'=>200]))]);
            if (str_contains($url,'api.groq.com')) {
                if (str_contains($request['messages'][0]['content'],'AUREL:UNDERSTAND')) $content = ['intent'=>$revision ? 'revise_day' : 'plan','patch'=>$patch,
                    'action_quote'=>$quote ?? ($revision ? 'Chỉnh ngày 2' : 'Lên lịch'), 'target_day'=>$revision ? 2 : null, 'reduce_stops'=>false];
                else {
                    $payload = json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);
                    $index = 0; $days = [];
                    foreach ($payload['dates'] as $date){$items=[];for($i=0;$i<$payload['stops_per_day']['min'];$i++)$items[]=['candidate_id'=>$index++,'duration_minutes'=>60,'description'=>'Tham quan có nguồn'];$days[]=['date'=>$date,'items'=>$items];}
                    $content = ['is_valid'=>true,'title'=>'Kế hoạch có nguồn','days'=>$days];
                }
                return Http::response(['choices'=>[['message'=>['content'=>json_encode($content)]]]]);
            }
            throw new \RuntimeException('Unexpected provider request');
        });
    }
    public function test_compact_generation_reuses_grounded_generator_with_optional_budget(): void
    {
        $this->planningProviders();
        $payload = $this->payload(); unset($payload['days']);
        $trip = $this->send('POST','/planner/generate',$payload)->assertCreated()->assertJsonPath('data.provisional',true)->assertJsonPath('data.budget.limit',null)->json('data');
        $this->assertNotEmpty($trip['days'][0]['items']);
        $this->assertGreaterThan(0,DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->count());
    }
    public function test_editor_keeps_a_departure_date_from_the_existing_conversation_flow_as_a_proposal(): void
    {
        $this->planningProviders(false,['start_date'=>'2030-01-07'],'Lên lịch ngày 07/01/2030');
        $payload=$this->payload(['duration_days'=>1,'days'=>[['items'=>[$this->item()]]]]);
        $trip=$this->send('POST','/planner',$payload)->assertCreated()->json('data');
        $session=$this->postJson('/api/chat/start')->assertCreated()->json('session_id');$before=DB::table('itinerary')->count();
        $response=$this->postJson('/api/chat/'.$session.'/message',['message'=>'Lên lịch ngày 07/01/2030','planner_context'=>$payload],['Idempotency-Key'=>'existing-departure-date'])->assertOk();
        $timeline=collect($response->json('assistant.components'))->firstWhere('type','timeline');
        $this->assertSame('2030-01-07',$timeline['visit_start_date']);$this->assertNull($timeline['trip_id']);
        $this->assertSame($before,DB::table('itinerary')->count());
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.provisional',true)->assertJsonPath('data.revision',1);
    }

    public function test_hours_presentation_can_read_the_owned_snapshot_without_exposing_another_customers_snapshot(): void
    {
        $trip=$this->send('POST','/planner',$this->payload())->assertCreated()->json('data');$id=$trip['days'][0]['items'][0]['id'];
        $snapshot=DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->first();$meta=json_decode($snapshot->snapshot,true);
        $meta['name']='Dạo phố';$meta['place_changed']=false;$meta['opening_schedule']=['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>[1=>['state'=>'open','intervals'=>[['start'=>'08:00','end'=>'22:00','end_day_offset'=>0]]]],'exceptions'=>[]];
        DB::table('itinerary_place_snapshot')->where('id',$snapshot->id)->update(['snapshot'=>json_encode($meta)]);
        $payload=$this->payload(['days'=>[['items'=>[$this->item(['id'=>$id])]],['items'=>[]]]]);
        $this->postJson('/api/v2/itineraries/planner/check-hours',$payload)->assertOk()->assertJsonPath('days.0.items.0.opening_check.display.has_data',true);
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->postJson('/api/v2/itineraries/planner/check-hours',$payload)->assertOk()->assertJsonPath('days.0.items.0.opening_check.display.has_data',false);
    }
    public function test_ai_day_revision_is_only_a_proposal_and_preserves_the_manual_baseline(): void
    {
        $this->planningProviders(true);
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $session = $this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $result = $this->postJson('/api/chat/'.$session.'/message',['message'=>'Chỉnh ngày 2','planner_context'=>$this->payload()],['Idempotency-Key'=>'planner-revise-proposal'])->assertOk()->json();
        $timeline = collect($result['assistant']['components'])->firstWhere('type','timeline');
        $this->assertSame(2,$timeline['target_day']); $this->assertNull($timeline['trip_id']);
        $this->assertSame('Dạo phố',$timeline['days'][0]['items'][0]['title']);
        $this->assertNotEmpty($timeline['days'][1]['items']);
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertJsonPath('data.revision',1)->assertJsonCount(0,'data.days.1.items');
        $this->assertSame(2,DB::table('itinerary')->count());
    }
    public function test_natural_afternoon_request_proposes_only_day_one_and_preserves_morning_and_other_days(): void
    {
        // Reproduce a model that paraphrases the action quote and guesses the wrong day/window.
        $this->planningProviders(true,['activity_window_start'=>'13:00'],'Thêm hoạt động cho buổi chiều ngày một');
        $payload=$this->payload(['days'=>[['items'=>[$this->item(),$this->item(['title'=>'Cà phê sáng','start_time'=>'10:00','end_time'=>'11:00'])]],['items'=>[$this->item(['title'=>'Hoạt động ngày 2'])]]]]);
        $trip=$this->send('POST','/planner',$payload)->assertCreated()->json('data');
        $session=$this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $reply=$this->postJson('/api/chat/'.$session.'/message',['message'=>'Ngày 1 cho tôi thêm lịch buổi chiều đi','planner_context'=>$payload],
            ['Idempotency-Key'=>'planner-afternoon-natural'])->assertOk()->assertJsonPath('assistant.intent','revise_day')->assertJsonPath('assistant.state.activity_window_start','08:30')->json();
        $timeline=collect($reply['assistant']['components'])->firstWhere('type','timeline');
        $this->assertSame(1,$timeline['target_day']);$this->assertSame(['start'=>'13:00','end'=>'17:30'],$timeline['target_window']);
        $this->assertSame('Dạo phố',$timeline['days'][0]['items'][0]['title']);$this->assertSame('09:00',$timeline['days'][0]['items'][0]['start_time']);
        $this->assertSame('Cà phê sáng',$timeline['days'][0]['items'][1]['title']);
        $this->assertSame('Hoạt động ngày 2',$timeline['days'][1]['items'][0]['title']);
        $places=array_filter($timeline['days'][0]['items'],fn($item)=>$item['kind']==='place');$this->assertNotEmpty($places);
        foreach($places as $item){$this->assertGreaterThanOrEqual('13:00',$item['start_time']);$this->assertLessThanOrEqual('17:30',$item['end_time']);}
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.revision',1)->assertJsonCount(2,'data.days.0.items')->assertJsonPath('data.days.1.items.0.title','Hoạt động ngày 2');
    }

    public function test_explicit_day_edit_rejects_a_day_outside_the_current_trip(): void
    {
        $this->planningProviders(true,[],null);$session=$this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $this->postJson('/api/chat/'.$session.'/message',['message'=>'Ngày 3 cho tôi thêm lịch buổi chiều đi','planner_context'=>$this->payload()],
            ['Idempotency-Key'=>'planner-afternoon-out-of-range'])->assertUnprocessable()->assertJsonPath('code','revision_needs_day');
        $this->assertSame(1,DB::table('itinerary')->count());
    }
    public function test_chat_can_propose_four_days_from_one_and_save_or_undo_the_expansion(): void
    {
        $message = 'Có thêm được 3 -> 4 ngày không ý là tôi muốn chia ra nhiều ngày có thể chơi ấy cụ thể';
        $this->planningProviders(false, [], $message);
        $baseline = $this->payload(['duration_days'=>1, 'days'=>[['items'=>[$this->item()]]]]);
        $trip = $this->send('POST','/planner',$baseline)->assertCreated()->json('data');
        $baseline['days'][0]['items'][0]['id'] = $trip['days'][0]['items'][0]['id'];
        $session = $this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $response = $this->postJson('/api/chat/'.$session.'/message', ['message'=>$message,'planner_context'=>$baseline],
            ['Idempotency-Key'=>'planner-expand-proposal'])->assertOk()->assertJsonPath('assistant.state.duration_days',4)->json();
        $timeline = collect($response['assistant']['components'])->firstWhere('type','timeline');
        $this->assertNull($timeline['target_day']); $this->assertCount(4,$timeline['days']);
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.total_days',1)->assertJsonPath('data.revision',1);
        $expanded = array_replace($baseline, ['duration_days'=>4,'expected_revision'=>1,'days'=>array_map(fn ($day) => ['items'=>array_map(fn ($item) => [
            'item_type'=>$item['kind']==='place' ? 'attraction' : 'activity','title'=>$item['title'],'place_name'=>$item['address'] ?? null,
            'description'=>$item['description'] ?? null,'start_time'=>$item['start_time'],'end_time'=>$item['end_time'],
            'estimated_cost'=>$item['ticket_estimate']['amount'] ?? null],$day['items'])],$timeline['days'])]);
        $this->send('PUT','/'.$trip['id'].'/planner',$expanded)->assertOk()->assertJsonPath('data.id',$trip['id'])
            ->assertJsonPath('data.total_days',4)->assertJsonPath('data.revision',2)->assertJsonCount(4,'data.days');
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonCount(4,'data.days');
        unset($baseline['days'][0]['items'][0]['id']);
        $baseline['expected_revision'] = 2;
        $this->send('PUT','/'.$trip['id'].'/planner',$baseline)->assertOk()->assertJsonPath('data.total_days',1)
            ->assertJsonPath('data.revision',3)->assertJsonCount(1,'data.days')->assertJsonPath('data.days.0.items.0.title','Dạo phố');
    }
    public function test_saving_unchanged_generated_trip_preserves_sources_and_manual_place_changes_clear_stale_facts(): void
    {
        $this->planningProviders(); $payload = $this->payload(); unset($payload['days']);
        $trip = $this->send('POST','/planner/generate',$payload)->assertCreated()->json('data');
        $payload['expected_revision'] = 1;
        $payload['days'] = array_map(fn ($day) => ['items'=>array_map(fn ($item) => array_intersect_key($item,array_flip([
            'id','item_type','title','place_name','description','start_time','end_time','estimated_cost'])),$day['items'])],$trip['days']);
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.readiness.validation_status','route_checked');
        $payload['expected_revision'] = 2; $payload['days'][0]['items'][0]['place_name'] = 'Điểm do khách chọn';
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.days.0.items.0.coordinates',null)
            ->assertJsonPath('data.days.0.items.0.source_url',null)->assertJsonPath('data.days.0.items.0.source',null)->assertJsonPath('data.readiness.validation_status','manually_edited');
        $payload['expected_revision'] = 3; $payload['days'][0]['items'][0]['place_name'] = null;
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.days.0.items.0.place_name',null);
    }
    public function test_pdf_download_is_real_pdf_with_owner_access_and_escaped_view(): void
    {
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $response = $this->getJson('/api/v2/itineraries/'.$trip['id'].'/pdf')->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$response->getContent());
        $trip['title'] = '<script>alert(1)</script>';
        $this->assertStringContainsString('&lt;script&gt;',view('pdf.itinerary',['trip'=>$trip])->render());
        Http::assertNothingSent();
    }
    public function test_chat_editor_context_produces_proposal_without_mutating_saved_trip(): void
    {
        config(['services.groq.api_key'=>'fixture-only','services.geoapify.external_photos'=>false]);
        Http::fake(function ($request) {
            if (str_contains($request['messages'][0]['content'],'AUREL:UNDERSTAND')) $content = ['intent'=>'out_of_scope','patch'=>[],'action_quote'=>null];
            else throw new \RuntimeException('Unexpected provider call');
            return Http::response(['choices'=>[['message'=>['content'=>json_encode($content)]]]]);
        });
        $trip = $this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $session = $this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $this->postJson('/api/chat/'.$session.'/message',['message'=>'Giải toán','planner_context'=>$this->payload()],['Idempotency-Key'=>'planner-chat-test'])->assertOk();
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertJsonPath('data.revision',1)->assertJsonPath('data.days.0.items.0.title','Dạo phố');
        $this->assertSame(2,DB::table('itinerary')->count());
    }
    public function test_planner_view_compiles_and_exports_browser_fixture(): void
    {
        $html = view('user.itineraries')->render();
        $this->assertStringContainsString('id="tripForm"',$html);
        $this->assertStringNotContainsString('id="itineraryCreate"',$html);
        $this->assertStringContainsString('id="aiComposer"',$html);
        $this->assertStringContainsString('id="sidebar"',$html);
        $this->assertStringContainsString('Không gian của bạn',$html);
        $this->assertStringNotContainsString('nav class="hv-header',$html);
        $directory = storage_path('app/private/itinerary-planner-ui');
        if (!is_dir($directory)) mkdir($directory,0777,true);
        file_put_contents($directory.'/itineraries.html',$html);
    }
    public function test_saved_schedule_keeps_its_items_but_flags_a_reported_closed_place():void
    {
        DB::table('location')->where('id',901)->update(['name'=>'Đà Nẵng']);
        $trip=$this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['title'=>'Thành cổ Điện Hải'])]],['items'=>[]]]]))
            ->assertCreated()->assertJsonPath('data.days.0.items.0.excluded_from_ai',true)->json('data');
        $this->assertSame('Thành cổ Điện Hải',DB::table('itinerary_item')->where('id',$trip['days'][0]['items'][0]['id'])->value('title'));
        $this->getJson('/api/v2/itineraries/destinations')->assertOk()->assertJsonStructure(['data','place_restrictions']);
        Http::assertNothingSent();
    }

    private function photoProposal(array $changes=[]): array
    {
        $session = $this->postJson('/api/chat/start')->assertCreated()->json('session_id');
        $image = ['url'=>'/image/ai-places/suoi-tien.jpg','alt'=>'Ảnh địa điểm kiểm thử','author'=>'Fixture photographer',
            'license'=>'CC0 1.0','source_url'=>'https://commons.wikimedia.org/wiki/File:Fixture.jpg'];
        $id = DB::table('chat_message')->insertGetId(['session_id'=>$session,'role'=>'bot','content'=>'Đề xuất có ảnh',
            'entities_json'=>json_encode(['assistant'=>['state'=>['location_id'=>901],'components'=>[['type'=>'timeline','days'=>[
                ['day_number'=>1,'items'=>[array_replace(['title'=>'Dạo phố','address'=>'Đường sách','image'=>$image],$changes)]]]]]]],JSON_THROW_ON_ERROR)]);
        return [['message_id'=>$id,'day_number'=>1,'item_index'=>0],$image];
    }

    public function test_photo_from_owned_proposal_survives_save_reload_and_undo_of_a_place_change(): void
    {
        [$reference,$image] = $this->photoProposal();
        $payload = $this->payload(['days'=>[['items'=>[$this->item(['photo_reference'=>$reference])]],['items'=>[]]]]);
        $trip = $this->send('POST','/planner',$payload)->assertCreated()->assertJsonPath('data.days.0.items.0.image',fn ($actual) => $actual == $image)->json('data');
        unset($payload['days'][0]['items'][0]['photo_reference']);
        $payload['days'][0]['items'][0]['id'] = $trip['days'][0]['items'][0]['id']; $payload['expected_revision'] = 1;
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.days.0.items.0.image',fn ($actual) => $actual == $image);
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.days.0.items.0.image',fn ($actual) => $actual == $image);
        $payload['expected_revision'] = 2; $payload['days'][0]['items'][0]['place_name'] = 'Nơi khách tự đổi';
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.days.0.items.0.image',null);
        $payload['expected_revision'] = 3; $payload['days'][0]['items'][0]['place_name'] = 'Đường sách';
        $this->send('PUT','/'.$trip['id'].'/planner',$payload)->assertOk()->assertJsonPath('data.days.0.items.0.image',fn ($actual) => $actual == $image);
        Http::assertNothingSent();
    }

    public function test_photo_reference_rejects_other_customers_changed_places_and_client_image_metadata(): void
    {
        Sanctum::actingAs(Customer::findOrFail(42)); [$foreign] = $this->photoProposal(); Sanctum::actingAs(Customer::findOrFail(41));
        $this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['photo_reference'=>$foreign])]],['items'=>[]]]]))->assertUnprocessable();
        [$reference] = $this->photoProposal();
        $this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['photo_reference'=>$reference,'place_name'=>'Điểm khác'])]],['items'=>[]]]]))->assertUnprocessable();
        $this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['image'=>['url'=>'https://example.com/unverified.jpg']])]],['items'=>[]]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('days.0.items.0');
        $reference['item_index'] = 5;
        $this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['photo_reference'=>$reference])]],['items'=>[]]]]))->assertUnprocessable();
        $this->assertSame(1,DB::table('itinerary')->count()); Http::assertNothingSent();
    }

    public function test_reviewed_thuong_bac_photo_appears_on_existing_saved_trip_without_network_or_rewriting_it(): void
    {
        $trip=$this->send('POST','/planner',$this->payload())->assertCreated()->json('data');
        $id=$trip['days'][0]['items'][0]['id'];
        DB::table('itinerary_item')->where('id',$id)->update(['title'=>'Thương Bạc']);
        DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->update(['snapshot'=>json_encode([
            'provider'=>'geoapify','name'=>'Thương Bạc','coordinates'=>[107.5855685,16.46803325],'image'=>null,
        ],JSON_THROW_ON_ERROR)]);
        $before=DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->value('snapshot');
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.days.0.items.0.image.url','/image/ai-places/thuong-bac-hue.jpg');
        $this->assertSame($before,DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip['id'])->value('snapshot'));
        $photos=app(\App\Services\Itinerary\PlacePhoto::class);
        $this->assertNull($photos->known(['name'=>'Thương Bạc','coordinates'=>[106.7,10.77]]));
        $this->assertNull($photos->known(['provider'=>'google_sheet','name'=>'Thương Bạc','coordinates'=>[107.5855685,16.46803325]]));
        $this->assertFileExists(public_path('image/ai-places/thuong-bac-hue.jpg'));
        Http::assertNothingSent();
    }

    public function test_missing_proposal_photo_can_load_after_save_and_reopen_without_a_revision_change(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        [$reference]=$this->photoProposal(['image'=>null,'coordinates'=>[106.7,10.77],'wikidata_id'=>'Q99991']);
        $trip=$this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['photo_reference'=>$reference])]],['items'=>[]]]]))
            ->assertCreated()->assertJsonPath('data.days.0.items.0.photo_lookup',true)->assertJsonPath('data.days.0.items.0.image',null)->json('data');
        Http::assertNothingSent();$this->photoProvider();
        $lookup=['itinerary_id'=>$trip['id'],'item_id'=>$trip['days'][0]['items'][0]['id'],'title'=>'Dạo phố','place_name'=>'Đường sách'];
        $this->postJson('/api/v2/itineraries/planner/place-photo',$lookup)->assertOk()->assertJsonPath('image.license','Public domain')
            ->assertJsonPath('image.license_url','https://commons.wikimedia.org/wiki/File:Owned.jpg');
        $this->getJson('/api/v2/itineraries/'.$trip['id'])->assertOk()->assertJsonPath('data.revision',1)
            ->assertJsonPath('data.days.0.items.0.image.url','https://upload.wikimedia.org/owned.jpg');
        $this->assertSame(2,count(Http::recorded()));
        $this->postJson('/api/v2/itineraries/planner/place-photo',$lookup)->assertOk();
        $this->assertSame(2,count(Http::recorded()));
        $this->postJson('/api/v2/itineraries/planner/place-photo',[...$lookup,'title'=>'Khách tự đổi tên'])->assertUnprocessable();
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->postJson('/api/v2/itineraries/planner/place-photo',$lookup)->assertNotFound();
        $this->postJson('/api/v2/itineraries/planner/place-photo',['photo_reference'=>$reference,'title'=>'Dạo phố','place_name'=>'Đường sách'])->assertUnprocessable();
    }

    public function test_fourth_place_photo_is_not_limited_by_the_initial_three_photo_budget_and_failure_keeps_schedule(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        [$reference]=$this->photoProposal(['image'=>null,'coordinates'=>[106.7,10.77],'wikidata_id'=>'Q99991']);
        $message=DB::table('chat_message')->find($reference['message_id']);$entities=json_decode($message->entities_json,true);
        $source=$entities['assistant']['components'][0]['days'][0]['items'][0];
        $entities['assistant']['components'][0]['days'][0]['items']=array_fill(0,4,$source);
        DB::table('chat_message')->where('id',$message->id)->update(['entities_json'=>json_encode($entities)]);
        $reference['item_index']=3;$lookup=['photo_reference'=>$reference,'title'=>'Dạo phố','place_name'=>'Đường sách'];
        Http::fake(['https://www.wikidata.org/*'=>Http::response([],503)]);
        $this->postJson('/api/v2/itineraries/planner/place-photo',$lookup)->assertOk()->assertJsonPath('image',null);
        $this->assertSame(1,DB::table('itinerary')->count());
        \Illuminate\Support\Facades\Cache::flush();Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();$this->photoProvider();
        $this->postJson('/api/v2/itineraries/planner/place-photo',$lookup)->assertOk()->assertJsonPath('image.url','https://upload.wikimedia.org/owned.jpg');
    }

    private function photoProvider(): void
    {
        Http::fake(function($request){
            if(str_contains($request->url(),'wikidata.org/'))return Http::response(['entities'=>['Q99991'=>[
                'id'=>'Q99991','labels'=>['vi'=>['value'=>'Dạo phố']], 'claims'=>[
                    'P625'=>[['mainsnak'=>['datavalue'=>['value'=>['longitude'=>106.7,'latitude'=>10.77]]]]],
                    'P18'=>[['mainsnak'=>['datavalue'=>['value'=>'Owned.jpg']]]],
                ]]]]);
            return Http::response(['query'=>['pages'=>['1'=>['imageinfo'=>[[
                'thumburl'=>'https://upload.wikimedia.org/owned.jpg','descriptionurl'=>'https://commons.wikimedia.org/wiki/File:Owned.jpg','mime'=>'image/jpeg',
                'extmetadata'=>['Artist'=>['value'=>'Owned photographer'],'LicenseShortName'=>['value'=>'Public domain']],
            ]]]]]]);
        });
    }

    public function test_existing_breaks_use_friendly_copy_without_rewriting_notes_or_claiming_unknown_costs(): void
    {
        $old = 'Nghỉ ngơi hoặc tự khám phá; không phát sinh đặt dịch vụ hoặc chi phí đã xác nhận.';
        $trip = $this->send('POST','/planner',$this->payload(['days'=>[['items'=>[$this->item(['title'=>'Thời gian tự do','description'=>$old])]],
            ['items'=>[$this->item(['title'=>'Ghi chú riêng','description'=>'Hẹn bạn lúc 15:00 ở cổng phía Tây.','estimated_cost'=>150000])]]]]))->assertCreated()->json('data');
        $this->assertStringContainsString('Bạn có thể nghỉ ngơi, lấy lại sức',$trip['days'][0]['items'][0]['description']);
        $this->assertSame($old,DB::table('itinerary_item')->where('id',$trip['days'][0]['items'][0]['id'])->value('description'));
        $this->assertSame('Hẹn bạn lúc 15:00 ở cổng phía Tây.',$trip['days'][1]['items'][0]['description']);
        $html = view('pdf.itinerary',['trip'=>$trip])->render();
        $this->assertStringContainsString('Bạn có thể nghỉ ngơi, lấy lại sức',$html);
        $this->assertStringNotContainsString('Chi phí: Chưa xác định',$html); $this->assertStringContainsString('150.000',$html);
        Http::assertNothingSent();
    }
}
