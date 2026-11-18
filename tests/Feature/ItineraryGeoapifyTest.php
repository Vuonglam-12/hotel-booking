<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ItineraryFixture;

final class ItineraryGeoapifyTest extends TestCase
{
    public function createApplication() { return ItineraryFixture::application(); }
    protected function setUp():void
    {
        parent::setUp();ItineraryFixture::schema();
        if(!\Illuminate\Support\Facades\Schema::hasTable('travel_source_place'))(require base_path('database/migrations/2026_10_31_000001_create_travel_source_places.php'))->up();
        if(!\Illuminate\Support\Facades\Schema::hasColumn('travel_source_place','opening_schedule'))(require base_path('database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php'))->up();
        DB::beginTransaction();Cache::flush();
        // The owned schema can also hold an explicitly retained real-provider browser fixture.
        // Clear generated fixtures inside this test transaction; tearDown restores them.
        DB::table('itinerary')->where('schema_version',2)->delete();
        DB::table('itinerary_write_request')->delete();
        DB::table('itinerary_region_mapping')->delete();
        Http::preventStrayRequests();Sanctum::actingAs(Customer::findOrFail(41));$this->withoutVite();
        config(['services.geoapify.api_key'=>'isolated-map-key','services.groq.api_key'=>'isolated-ai-key','services.groq.model'=>'openai/gpt-oss-20b','logging.channels.stderr.level'=>'critical']);
    }
    protected function tearDown():void { while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown(); }
    private function payload(array $extra=[]):array
    {
        return array_replace(['location_id'=>901,'start_date'=>'2030-01-01','end_date'=>'2030-01-01','adults'=>2,'children'=>[],'infants'=>[],
            'stay_mode'=>'own_accommodation','selected_dates'=>['2030-01-01'],'activity_window_start'=>'08:00','activity_window_end'=>'20:00',
            'activity_window_confirmed'=>true,'estimated_budget'=>1000000,'contingency_amount'=>null,'transport'=>'walk','interests'=>['văn hóa']],$extra);
    }
    private function send(array $extra=[],string $key='generate-key') { return $this->postJson('/api/v2/itineraries/generate',$this->payload($extra),['Idempotency-Key'=>$key]); }
    private function providers(array $change=[]):void
    {
        $region=['features'=>[['bbox'=>[106,10,107,11],'properties'=>['place_id'=>'verified-region-901','country_code'=>'vn','result_type'=>'city','category'=>'administrative','name'=>'Thành phố Hồ Chí Minh','lon'=>106.7,'lat'=>10.77]]]];
        $places=['features'=>[]];
        foreach(isset($change['extra_place'])?[0,1,2]:[0,1] as $i)$places['features'][]=['properties'=>['place_id'=>'place-'.$i,'name'=>'Isolated place '.$i,'country_code'=>'vn','categories'=>['tourism.sights'],'formatted'=>'Isolated address'], 'geometry'=>['coordinates'=>[106.7+$i/1000,10.77]]];
        $plan=['is_valid'=>true,'title'=>'Lịch kiểm thử cô lập','days'=>[['date'=>'2030-01-01','items'=>[['candidate_id'=>0,'duration_minutes'=>60,'description'=>'Gợi ý tham quan'],['candidate_id'=>1,'duration_minutes'=>60,'description'=>'Điểm tiếp theo']]]]];
        if(isset($change['plan']))$plan=$change['plan'];
        if(isset($change['bad_candidate']))$plan['days'][0]['items'][1]['candidate_id']=99;
        if(isset($change['duplicate']))$plan['days'][0]['items'][1]['candidate_id']=0;
        if(isset($change['bad_duration']))$plan['days'][0]['items'][0]['duration_minutes']=-1;
        if(isset($change['bad_day']))$plan['days'][0]['date']='2030-01-02';
        if(isset($change['bad_region']))$region['features'][0]['properties']['name']='Hà Nội';
        $route=['time'=>$change['travel_seconds']??90,'distance'=>200];
        $matrix=['sources_to_targets'=>[[['time'=>0,'distance'=>0],$route],[$route,['time'=>0,'distance'=>0]]]];
        if(isset($change['unreachable']))$matrix['sources_to_targets'][0][1]=null;
        Http::fake(function($request)use($region,$places,$plan,$matrix,$change){
            if(str_contains($request->url(),'/v2/place-details'))return Http::response(['features'=>[['properties'=>['place_id'=>'verified-region-901'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]]]]]]]);
            if(str_contains($request->url(),'/geocode/'))return Http::response($region,$change['geo_status']??200);
            if(str_contains($request->url(),'/v2/places'))return Http::response($places,200);
            if(str_contains($request->url(),'/routematrix'))return Http::response($matrix,200);
            if(str_contains($request->url(),'api.groq.com/'))return Http::response(['choices'=>[['message'=>['content'=>json_encode($plan,JSON_THROW_ON_ERROR)]]]],$change['groq_status']??200);
            throw new \RuntimeException('Unexpected request in isolated test.');
        });
    }
    public function test_generation_persists_provider_sources_routes_party_and_unknown_prices_atomically():void
    {
        $this->providers();$catalog=DB::table('attraction')->count();
        $data=$this->send()->assertCreated()->assertJsonPath('data.schema_version',2)->assertJsonPath('data.party.total',2)
            ->assertJsonPath('data.total_days',1)->assertJsonPath('data.end_date','2030-01-01')->assertJsonPath('data.readiness.activities_generated',true)
            ->assertJsonPath('data.readiness.provider_region_verified',true)->assertJsonPath('data.budget.complete',false)
            ->assertJsonPath('data.days.0.items.0.estimated_cost',null)->assertJsonPath('data.days.0.items.1.start_time','09:10')
            ->assertJsonPath('data.days.0.items.1.travel_from_previous.time_seconds',90)->json('data');
        $this->assertSame(2,DB::table('itinerary_place_snapshot')->where('itinerary_id',$data['id'])->count());
        $this->assertSame(2,DB::table('itinerary_cost_line')->where('itinerary_id',$data['id'])->whereNull('unit_amount')->count());
        $this->assertSame($catalog,DB::table('attraction')->count());Http::assertSentCount(6);
        Http::assertSent(fn($r)=>str_contains($r->url(),'/v2/places') && $r['filter']==='place:verified-region-901' && !str_contains($r['filter'],'circle:'));
    }
    public function test_retry_replays_without_more_provider_calls_or_duplicate_trips():void
    {
        $this->providers();$id=$this->send()->assertCreated()->json('data.id');
        $this->send()->assertCreated()->assertHeader('Idempotency-Replayed','true')->assertJsonPath('data.id',$id);
        $this->send(['estimated_budget'=>2000000])->assertConflict();Http::assertSentCount(6);
        $this->assertSame(1,DB::table('itinerary')->where('schema_version',2)->count());
    }
    public function test_generated_trip_can_be_saved_reopened_and_deleted_without_losing_sources():void
    {
        $this->providers();$id=$this->send()->assertCreated()->json('data.id');
        $this->patchJson('/api/v2/itineraries/'.$id,['status'=>'saved','expected_revision'=>1],['Idempotency-Key'=>'save-key'])->assertOk()->assertJsonPath('data.revision',2);
        $this->getJson('/api/v2/itineraries/'.$id)->assertOk()->assertJsonPath('data.status','saved')->assertJsonPath('data.days.0.items.0.source','Geoapify / OpenStreetMap');
        $this->deleteJson('/api/v2/itineraries/'.$id,['expected_revision'=>2],['Idempotency-Key'=>'delete-key'])->assertNoContent();
        $this->assertSame(0,DB::table('itinerary_place_snapshot')->count());$this->assertSame(0,DB::table('itinerary_item')->where('itinerary_id',$id)->count());
    }
    public static function rejectedPlans():array
    {
        return [[['bad_candidate'=>true],503,'invalid_ai_plan'],[['duplicate'=>true],503,'invalid_ai_candidate'],[['bad_duration'=>true],503,'invalid_ai_plan'],
            [['bad_day'=>true],503,'invalid_ai_dates'],[['bad_region'=>true],422,'region_unverified'],[['unreachable'=>true],422,'route_unavailable'],
            [['travel_seconds'=>86400],422,'schedule_overflow'],[['geo_status'=>429],503,'geoapify_quota'],[['geo_status'=>403],503,'geoapify_access_denied'],
            [['groq_status'=>429],503,'groq_quota']];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedPlans')]
    public function test_bad_sources_ai_routes_and_quotas_create_no_partial_records(array $changes,int $status,string $code):void
    {
        $this->providers($changes);$before=DB::table('itinerary')->count();
        $this->send()->assertStatus($status)->assertJsonPath('code',$code);
        $this->assertSame($before,DB::table('itinerary')->count());
        foreach(['itinerary_item','itinerary_place_snapshot','itinerary_cost_line','itinerary_region_mapping','itinerary_write_request']as$table){
            if($table==='itinerary_item')$this->assertSame(1,DB::table($table)->count());else $this->assertSame(0,DB::table($table)->count());
        }
    }
    public function test_unknown_destination_and_unconfirmed_hours_never_call_providers():void
    {
        $this->send(['location_id'=>99999])->assertUnprocessable();
        $this->send(['activity_window_confirmed'=>false])->assertUnprocessable()->assertJsonPath('code','generation_window_required');
        $this->send(['start_date'=>'2020-01-01','end_date'=>'2020-01-01','selected_dates'=>['2020-01-01']])->assertUnprocessable()->assertJsonPath('code','generation_date_in_past');
        Http::assertNothingSent();
    }
    public function test_non_owner_and_missing_key_are_refused():void
    {
        $this->providers();$id=$this->send()->assertCreated()->json('data.id');Sanctum::actingAs(Customer::findOrFail(42));
        $this->getJson('/api/v2/itineraries/'.$id)->assertNotFound();
        $this->deleteJson('/api/v2/itineraries/'.$id,['expected_revision'=>1],['Idempotency-Key'=>'not-owner'])->assertNotFound();
        $this->postJson('/api/v2/itineraries/generate',$this->payload())->assertUnprocessable();
    }
    public function test_legacy_generator_is_read_only_and_does_not_call_ai():void
    {
        $this->postJson('/api/itineraries/generate',[])->assertConflict()->assertJsonPath('code','use_itinerary_v2_api');Http::assertNothingSent();
    }
    public function test_failure_after_an_activity_insert_rolls_back_trip_sources_costs_and_idempotency():void
    {
        $this->providers();$before=DB::table('itinerary')->count();$injected=false;
        DB::listen(function($query)use(&$injected){if(!$injected&&str_starts_with(strtolower($query->sql),'insert into `itinerary_item`')){$injected=true;throw new \RuntimeException('Injected isolated persistence failure.');}});
        $this->send()->assertStatus(500);$this->assertTrue($injected);
        $this->assertSame($before,DB::table('itinerary')->count());
        $this->assertSame(1,DB::table('itinerary_item')->count());
        foreach(['itinerary_place_snapshot','itinerary_cost_line','itinerary_region_mapping','itinerary_write_request']as$table)$this->assertSame(0,DB::table($table)->count());
    }
    public function test_duplicate_in_flight_request_does_not_call_providers_or_hold_a_customer_lock():void
    {
        $lock=Cache::lock('itinerary.generate.41.'.hash('sha256','generate-key'),120);$this->assertTrue($lock->get());
        try{$this->send()->assertConflict()->assertJsonPath('code','generation_in_progress');Http::assertNothingSent();}
        finally{$lock->release();}
    }

    private function sourceHours(int $index,array $rule):void
    {
        DB::table('travel_source_place')->insert(['source'=>\App\Services\Places\SheetPlaceSync::source(),'external_id'=>'HOURS-'.$index,'location_id'=>901,'name'=>'Isolated place '.$index,'category'=>'Tham quan','area'=>'Owned area','address'=>'Isolated address','tags'=>'[]','maps_url'=>'https://www.google.com/maps/search/?api=1&query=Owned','is_enabled'=>true,'content_hash'=>str_repeat('0',64),'guide_hash'=>str_repeat('0',64),
            'opening_schedule'=>json_encode(['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>[2=>$rule],'exceptions'=>[]])]);
    }
    public function test_generation_waits_for_a_shift_that_can_cover_the_entire_visit():void
    {
        $this->sourceHours(1,['state'=>'open','intervals'=>[['start'=>'14:00','end'=>'18:00','end_day_offset'=>0]]]);
        $this->providers();$data=$this->send([], 'generation-hours-wait')->assertCreated()->json('data');
        $stop=collect($data['days'][0]['items'])->firstWhere('title','Isolated place 1');$this->assertSame('14:00',$stop['start_time']);$this->assertSame('15:00',$stop['end_time']);$this->assertSame('within_hours',$stop['opening_check']['status']);
    }
    public function test_generation_omits_a_known_closed_day_and_uses_an_available_alternative():void
    {
        $this->sourceHours(0,['state'=>'closed','intervals'=>[]]);$this->providers(['extra_place'=>true]);$data=$this->send([], 'generation-hours-closed')->assertCreated()->json('data');
        $names=array_column($data['days'][0]['items'],'title');$this->assertNotContains('Isolated place 0',$names);$this->assertContains('Isolated place 1',$names);$this->assertContains('Isolated place 2',$names);
    }
}
