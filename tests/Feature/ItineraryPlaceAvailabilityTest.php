<?php

namespace Tests\Feature;

use App\Services\Itinerary\GeoapifyClient;
use App\Services\Itinerary\GenerationService;
use App\Services\Itinerary\PlaceAvailability;
use App\Services\Itinerary\TravelCatalog;
use App\Services\Itinerary\TravelDialogue;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ItineraryFixture;

final class ItineraryPlaceAvailabilityTest extends TestCase
{
    public function createApplication(){return ItineraryFixture::application();}
    protected function setUp():void
    {
        parent::setUp();ItineraryFixture::schema();
        if(!Schema::hasColumn('attraction','slug'))(require base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'))->up();
        DB::beginTransaction();Cache::flush();Http::preventStrayRequests();
        DB::table('location')->where('id',901)->update(['code'=>'ho-chi-minh']);
        config(['services.geoapify.api_key'=>'owned-map','services.groq.api_key'=>'owned-ai','services.geoapify.external_photos'=>false]);
    }
    protected function tearDown():void {while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown();}
    private function place(string $name,array $extra=[]):array
    {
        return [...['name'=>$name,'provider_place_id'=>'owned:'.$name,'coordinates'=>[106.72,10.77],'address'=>'Owned address',
            'item_type'=>'attraction','source'=>'Owned provider','source_url'=>'https://example.invalid/place'],...$extra];
    }

    public function test_reported_listing_aliases_are_blocked_only_in_the_correct_city():void
    {
        $service=app(PlaceAvailability::class);
        foreach(['Thành cổ Điện Hải','Thanh Dien Hai','Dien Hai Citadel'] as $name)$this->assertTrue($service->blocked(['name'=>$name],'Thành phố Đà Nẵng'));
        $this->assertFalse($service->blocked(['name'=>'Thành cổ Điện Hải'],'Hồ Chí Minh'));
        $this->assertFalse($service->blocked(['name'=>'Bảo tàng Đà Nẵng','address'=>'42 Bạch Đằng'],'Đà Nẵng'));
        $this->assertFalse($service->blocked(['name'=>'Cà phê Điện Hải'],'Đà Nẵng'));
    }

    public function test_permanent_closure_signals_are_distinct_from_temporary_closure_and_unknown():void
    {
        $service=app(PlaceAvailability::class);
        foreach([['business_status'=>'CLOSED_PERMANENTLY'],['permanently_closed'=>true],['datasource'=>['raw'=>['permanently_closed'=>'yes']]],['operating_status'=>'permanently_closed']] as $signal)$this->assertTrue($service->blocked($signal,'Hồ Chí Minh'));
        foreach([[],['business_status'=>'OPERATIONAL'],['business_status'=>'CLOSED_TEMPORARILY'],['opening_hours'=>'Mo off'],['permanently_closed'=>false],['business_status'=>[]]] as $signal)$this->assertFalse($service->blocked($signal,'Hồ Chí Minh'));
    }

    public function test_provider_closed_place_is_filtered_and_remains_blocked_after_cache_clear():void
    {
        $closed=true;
        Http::fake(function($request)use(&$closed){return Http::response(['features'=>array_map(fn($i)=>['properties'=>[
            'place_id'=>'owned-poi-'.$i,'name'=>'Owned status place '.$i,'country_code'=>'vn','business_status'=>$i===0&&$closed&&($request['categories'] ?? '')!=='tourism.sights,leisure.park'?'CLOSED_PERMANENTLY':'OPERATIONAL',
            'categories'=>['tourism.sights']],'geometry'=>['coordinates'=>[106.72+$i/1000,10.77]]],range(0,2))]);});
        $region=['place_id'=>'owned-region','name'=>'Hồ Chí Minh','lon'=>106.7,'lat'=>10.77];
        $places=app(GeoapifyClient::class)->places($region);$this->assertCount(2,$places);$this->assertNotContains('Owned status place 0',array_column($places,'name'));
        $this->assertSame(1,DB::table('itinerary_place_exclusion')->count());
        $closed=false;Cache::flush();app()->forgetScopedInstances();
        $this->assertNotContains('Owned status place 0',array_column(app(GeoapifyClient::class)->places($region),'name'));
    }

    public function test_provider_closure_also_blocks_matching_mysql_record_without_blocking_another_branch():void
    {
        $service=app(PlaceAvailability::class);$service->observe([$this->place('Owned closed café',['operating_status'=>'closed_permanently'])],'Hồ Chí Minh');
        $base=['location_id'=>901,'name'=>'Owned closed café','item_type'=>'attraction','is_active'=>true,'experience_category'=>'tham-quan',
            'experience_location_code'=>'ho-chi-minh','experience_verified_at'=>now(),'experience_published_at'=>now(),'lat'=>10.77];
        DB::table('attraction')->insert([...$base,'slug'=>'closed-cafe-original','lng'=>106.72]);
        DB::table('attraction')->insert([...$base,'slug'=>'closed-cafe-another-branch','lng'=>106.77]);
        $places=app(TravelCatalog::class)->approved(901);
        $this->assertCount(1,array_filter($places,fn($p)=>$p['name']==='Owned closed café'));
        $this->assertSame(106.77,array_values(array_filter($places,fn($p)=>$p['name']==='Owned closed café'))[0]['coordinates'][0]);
        Http::assertNothingSent();
    }

    public function test_generator_filters_closed_candidates_even_when_supplied_a_cached_pool():void
    {
        $seen=[];
        Http::fake(function($request)use(&$seen){
            if(str_contains($request->url(),'/routematrix'))return Http::response(['sources_to_targets'=>[[['time'=>0,'distance'=>0],['time'=>60,'distance'=>200]],[['time'=>60,'distance'=>200],['time'=>0,'distance'=>0]]]]);
            $payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);$seen=array_column($payload['candidates'],'name');
            return Http::response(['choices'=>[['message'=>['content'=>json_encode(['is_valid'=>true,'title'=>'Owned plan','days'=>[['date'=>'2030-01-01','items'=>[
                ['candidate_id'=>0,'duration_minutes'=>60,'description'=>'Gợi ý tham quan'],['candidate_id'=>1,'duration_minutes'=>60,'description'=>'Gợi ý tham quan']]]]])]]]]);
        });
        $data=['location_id'=>901,'selected_dates'=>['2030-01-01'],'start_date'=>'2030-01-01','end_date'=>'2030-01-01','adults'=>2,'children'=>[],'infants'=>[],
            'stay_mode'=>'own_accommodation','estimated_budget'=>null,'activity_window_confirmed'=>true,'activity_window_start'=>'08:30','activity_window_end'=>'17:30','transport'=>'drive'];
        $plan=app(GenerationService::class)->prepare($data,['region'=>['name'=>'Hồ Chí Minh'],'places'=>[
            $this->place('Owned blocked',['operating_status'=>'closed_permanently']),$this->place('Owned available A'),$this->place('Owned available B')]]);
        $this->assertSame(['Owned available A','Owned available B'],$seen);$this->assertCount(2,$plan['activities']);
        $this->assertSame(1,DB::table('itinerary')->count());
    }

    public function test_advice_does_not_reintroduce_a_blocked_place_from_model_memory():void
    {
        $seen=[];
        Http::fake(function($request)use(&$seen){
            $payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);$seen=array_column($payload['facts']['places'],'name');
            return Http::response(['choices'=>[['message'=>['content'=>json_encode([
                'message'=>'Bạn có thể tham quan Thành cổ Điện Hải.','place_ids'=>['owned:available'],'hotel_ids'=>[],'location_ids'=>[],
                'question'=>null,'quick_replies'=>['Tham quan Thành Điện Hải','Gợi ý điểm khác'],
            ])]]]]);
        });
        $result=app(TravelDialogue::class)->advise('Gợi ý tham quan',['destination_name'=>'Đà Nẵng'],['places'=>[
            [...$this->place('Thành cổ Điện Hải'),'id'=>'owned:closed'],[...$this->place('Điểm tham quan khác'),'id'=>'owned:available'],
        ]],[],null);
        $this->assertSame(['Điểm tham quan khác'],$seen);
        $this->assertStringNotContainsString('Điện Hải',$result['message']);
        $this->assertSame(['Gợi ý điểm khác'],$result['quick_replies']);
        $this->assertSame(['owned:available'],$result['place_ids']);
    }
}
