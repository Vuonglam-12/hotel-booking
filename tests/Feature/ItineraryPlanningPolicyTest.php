<?php
namespace Tests\Feature;

use App\Services\Itinerary\GenerationService;
use App\Services\Itinerary\PlanningPolicy;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\ItineraryFixture;

final class ItineraryPlanningPolicyTest extends TestCase
{
    private array $payload=[];
    public function createApplication(){return ItineraryFixture::application();}
    protected function setUp(): void
    {
        parent::setUp();ItineraryFixture::schema();DB::beginTransaction();Cache::flush();Http::preventStrayRequests();
        config(['services.groq.api_key'=>'isolated-key','services.geoapify.api_key'=>'isolated-map-key']);
    }
    protected function tearDown(): void {while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown();}
    private function data(array $changes=[]): array
    {
        return array_replace(['location_id'=>901,'start_date'=>'2030-01-01','end_date'=>'2030-01-01','selected_dates'=>['2030-01-01'],
            'adults'=>2,'children'=>[],'infants'=>[],'stay_mode'=>'own_accommodation','estimated_budget'=>null,'transport'=>'walk',
            'activity_window_confirmed'=>true,'activity_window_start'=>'08:30','activity_window_end'=>'17:30','interests'=>['văn hóa'],
            '_include_breaks'=>true,'_provisional'=>false],$changes);
    }
    private function places(int $count=8): array
    {
        return array_map(fn($i)=>['provider'=>'geoapify','provider_place_id'=>'policy-'.$i,'name'=>'Điểm trải nghiệm '.$i,
            'coordinates'=>[106.7+$i*.001,10.77],'address'=>'Địa chỉ '.$i,'item_type'=>'attraction','categories'=>['tourism.sights'],
            'source'=>'Owned source','source_url'=>'https://example.invalid/place/'.$i],range(0,$count-1));
    }
    private function ticket(int $amount): array
    {
        return ['status'=>'priced','adult_reference'=>$amount,'tiers'=>[],'review_due'=>'2031-01-01','source_url'=>'https://example.invalid/prices'];
    }
    private function prepare(array $data,?array $places=null,array $names=[]): array
    {
        $places??=$this->places();
        Http::fake(function($request)use($names){
            if(str_contains($request->url(),'api.groq.com')){
                $this->payload=json_decode($request['messages'][array_key_last($request['messages'])]['content'],true);$days=[];$next=0;
                foreach($this->payload['dates'] as $date){$items=[];
                    $ids=$names?array_map(fn($name)=>collect($this->payload['candidates'])->firstWhere('name',$name)['candidate_id'],$names):range($next,$next+$this->payload['stops_per_day']['min']-1);
                    foreach($ids as $id)$items[]=['candidate_id'=>$id,'duration_minutes'=>60,'description'=>'Bạn có thể khám phá địa điểm này theo sở thích.'];$next+=count($ids);$days[]=['date'=>$date,'items'=>$items];
                }
                return Http::response(['choices'=>[['message'=>['content'=>json_encode(['is_valid'=>true,'title'=>'Lịch trải nghiệm','days'=>$days])]]]]);
            }
            if(str_contains($request->url(),'/routematrix'))return Http::response(['sources_to_targets'=>array_fill(0,count($request['sources']),array_fill(0,count($request['targets']),['time'=>60,'distance'=>200]))]);
            throw new \RuntimeException('Unexpected provider');
        });
        return app(GenerationService::class)->prepare($data,['region'=>['name'=>'Hồ Chí Minh'],'places'=>$places]);
    }
    public function test_no_budget_still_plans_morning_and_afternoon_without_filling_the_day_with_rest(): void
    {
        $prepared=$this->prepare($this->data());$places=array_values(array_filter($prepared['activities'],fn($a)=>isset($a['place'])));
        $this->assertGreaterThanOrEqual(5,count($places));$this->assertGreaterThanOrEqual('16:00',end($places)['end_time']);
        $this->assertContains('Ăn trưa',array_column($prepared['activities'],'title'));
        $this->assertNotContains('Thời gian tự do',array_column($prepared['activities'],'title'));
        $this->assertNotSame([60],array_values(array_unique(array_column($places,'duration_minutes'))));
        foreach($places as $place){$this->assertSame(0,(int)substr($place['start_time'],3)%5);$this->assertNull($place['ticket_estimate']);}
        $this->assertSame(['văn hóa'],$this->payload['interests']);$this->assertNull($this->payload['budget_context']['total_group_vnd']);
        $this->assertNull($places[0]['travel']);$this->assertSame(60,$places[1]['travel']['time_seconds']);
        $this->assertGreaterThanOrEqual(5,$places[1]['travel']['scheduled_minutes']);$this->assertTrue($places[1]['travel']['buffer_is_planning_assumption']);
    }
    public function test_budget_checks_group_prices_and_replaces_an_expensive_choice_with_a_sourced_cheaper_place(): void
    {
        $places=$this->places(4);$places[0]['ticket']=$this->ticket(100000);$places[1]['ticket']=$this->ticket(150000);$places[2]['ticket']=$this->ticket(0);
        $prepared=$this->prepare($this->data(['estimated_budget'=>400000,'activity_window_end'=>'11:30']),$places,[$places[0]['name'],$places[1]['name']]);
        $this->assertSame(200000,array_sum(array_column(array_column($prepared['activities'],'ticket_estimate'),'amount')));
        $this->assertNotContains($places[1]['name'],array_column(array_column($prepared['activities'],'place'),'name'));
        $this->assertSame(2,$this->payload['budget_context']['people']);$this->assertSame(1,$this->payload['budget_context']['days']);
    }
    public function test_small_budget_overrun_is_reported_without_inventing_missing_costs(): void
    {
        $places=$this->places(2);$places[0]['ticket']=$this->ticket(100000);$places[1]['ticket']=$this->ticket(110000);
        $prepared=$this->prepare($this->data(['estimated_budget'=>400000,'activity_window_end'=>'11:30']),$places,[$places[0]['name'],$places[1]['name']]);
        $warnings=app(PlanningPolicy::class)->warnings($prepared['activities'],400000);
        $this->assertStringContainsString('20.000',implode(' ',$warnings));$this->assertStringContainsString('Chưa cộng',implode(' ',$warnings));
        $this->assertSame(420000,array_sum(array_column(array_column($prepared['activities'],'ticket_estimate'),'amount')));
    }
    public function test_unknown_price_cannot_be_used_as_a_free_replacement_for_a_price_over_budget(): void
    {
        $places=$this->places(3);$places[0]['ticket']=$this->ticket(100000);
        try{$this->prepare($this->data(['estimated_budget'=>100000,'activity_window_end'=>'10:30']),$places,[$places[0]['name'],$places[1]['name']]);$this->fail('An unpriced replacement must not count as free.');}
        catch(HttpResponseException $error){$this->assertSame('budget_not_feasible',$error->getResponse()->getData(true)['code']);}
    }
    public function test_budget_is_for_every_person_and_every_day_and_respects_previously_planned_costs(): void
    {
        $places=$this->places(4);foreach($places as &$place)$place['ticket']=$this->ticket(100000);unset($place);
        try{$this->prepare($this->data(['adults'=>4,'estimated_budget'=>800000,'end_date'=>'2030-01-02','selected_dates'=>['2030-01-01','2030-01-02'],'activity_window_end'=>'11:30','_min_stops'=>2]),$places);$this->fail('The budget is shared across both days.');}
        catch(HttpResponseException $error){$this->assertSame('budget_not_feasible',$error->getResponse()->getData(true)['code']);}
        $this->assertSame(100000,$this->payload['budget_context']['per_person_day_vnd']);
        $policy=app(PlanningPolicy::class);$context=$policy->budgetContext($this->data(['estimated_budget'=>800000,'_budget_spent'=>500000,'end_date'=>'2030-01-06']));
        $this->assertSame(6,$context['days']);$this->assertSame(500000,$context['accounted_before_vnd']);
    }
    public function test_same_complex_is_not_split_into_multiple_stops_but_a_nearby_restaurant_is_distinct(): void
    {
        $places=$this->places();$places[0]['name']='Thương Bạc';$places[1]['name']='Đình Thương Bạc';$places[1]['coordinates']=$places[0]['coordinates'];
        $places[2]['name']='Thương Bạc';$places[2]['item_type']='restaurant';$places[2]['coordinates']=[106.7005,10.77];
        // A differently named restaurant at this location remains a distinct experience.
        $places[2]['name']='Quán ăn Thương Bạc';
        $this->prepare($this->data(),$places);$names=array_column($this->payload['candidates'],'name');
        $this->assertContains('Thương Bạc',$names);$this->assertNotContains('Đình Thương Bạc',$names);$this->assertContains('Quán ăn Thương Bạc',$names);
        $policy=app(PlanningPolicy::class);$this->assertFalse($policy->samePlace($places[3],$places[4]));
    }
    public function test_a_whole_morning_is_not_inserted_as_rest_while_waiting_for_an_afternoon_shift(): void
    {
        $places=$this->places(2);$places[0]['opening_schedule']=['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>[2=>['state'=>'open','intervals'=>[['start'=>'14:00','end'=>'18:00','end_day_offset'=>0]]]],'exceptions'=>[]];
        $prepared=$this->prepare($this->data(),$places);$stops=array_values(array_filter($prepared['activities'],fn($a)=>isset($a['place'])));
        $this->assertSame($places[1]['name'],$stops[0]['place']['name']);$this->assertSame('14:00',$stops[1]['start_time']);
        foreach($prepared['activities'] as $activity)if(($activity['kind'] ?? '')==='break')$this->assertNotSame('Thời gian tự do',$activity['title']);
        $this->assertNotEmpty($prepared['warnings']);
    }
    public function test_a_real_restaurant_visit_replaces_the_generic_lunch_stop(): void
    {
        $places=$this->places();$places[0]['suggested_duration_minutes']=90;$places[1]['suggested_duration_minutes']=90;
        $places[2]['item_type']='restaurant';$places[2]['categories']=['catering.restaurant'];
        $prepared=$this->prepare($this->data(),$places);
        $this->assertContains($places[2]['name'],array_column(array_column($prepared['activities'],'place'),'name'));
        $this->assertNotContains('Ăn trưa',array_column($prepared['activities'],'title'));
    }
}
