<?php

namespace App\Services\Itinerary;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Support\CustomerSessions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class TravelAssistantService
{
    public function __construct(private TravelDialogue $dialogue, private ConversationState $memory, private TravelCatalog $catalog,
        private ConversationalPlanner $planner, private IdempotentWriter $writer) {}

    public function turn(Customer $customer,int $sessionId,string $message,string $requestKey,?TravelProgress $progress = null,?array $plannerContext = null): array
    {
        abort_if(CustomerSessions::isAdminToken($customer,$customer->currentAccessToken()),403,'Dùng tài khoản khách hàng để tư vấn chuyến đi.');
        if (!Schema::hasTable('itinerary_write_request') || !Schema::hasColumn('chat_session','context_json')) {
            $this->dialogue->fail('Chức năng tư vấn chuyến đi đang được chuẩn bị. Vui lòng thử lại sau.', 'assistant_schema_unavailable');
        }
        $key=hash('sha256','chat:'.$sessionId.':'.$requestKey);$operation='chat:'.$sessionId;$payload=['message'=>$message];
        if ($plannerContext !== null) $payload['planner_context'] = $plannerContext;
        // Verify ownership even when replaying a previously completed operation.
        $session=ChatSession::where('customer_id',$customer->id)->findOrFail($sessionId);
        if($previous=$this->writer->replay($customer,$key,$operation,$payload)) return $this->availableResponse($previous['body']);
        abort_unless($session->status==='active',409,'Cuộc trò chuyện này đã đóng. Hãy bắt đầu cuộc trò chuyện mới.');
        $lock=Cache::lock('aurel:chat:'.$sessionId,600);
        abort_unless($lock->get(),409,'AI đang xử lý tin nhắn trước. Bạn chờ một chút nhé.');
        try {
            $progress?->emit('reading_context');
            $session->refresh();$context=$session->context_json ?? [];$revision=$context['revision'] ?? 0;
            if ($plannerContext !== null){
                $fresh=app(PlannerService::class)->conversationContext($plannerContext);
                foreach(['pace','interests','food_restrictions','excluded_ids','constraints'] as $preference)if(!array_key_exists($preference,$plannerContext) && isset($context['state'][$preference])){
                    $fresh['state'][$preference]=$context['state'][$preference];
                    if(array_key_exists($preference,$fresh['last_plan']['data']))$fresh['last_plan']['data'][$preference]=$context['state'][$preference];
                }
                $fresh['last_plan']['state']=$fresh['state'];$context=[...$context,...$fresh];
            }
            $history=$session->messages()->orderByDesc('id')->limit(12)->get()->reverse()->map(function($entry){
                $content=$entry->content;
                if($entry->role==='bot') {
                    $shown=[];
                    foreach($entry->entities_json['assistant']['components'] ?? [] as $component) {
                        if(isset($component['items']) && in_array($component['type'],['places','hotels','destinations'],true))$shown[]=['type'=>$component['type'],'items'=>array_map(fn($item)=>['id'=>$item['id'],'name'=>$item['name']],$component['items'])];
                        if($component['type']==='timeline')$shown[]=['type'=>'timeline','days'=>array_map(fn($day)=>['day'=>$day['day_number'],'date'=>$day['date'],'places'=>array_column($day['items'],'title')],$component['days'])];
                    }
                    if($shown)$content.="\nDữ liệu đã hiển thị: ".json_encode($shown,JSON_UNESCAPED_UNICODE);
                }
                return ['role'=>$entry->role==='bot' ? 'assistant' : 'user','content'=>$content];
            })->values()->all();
            if ($plannerContext !== null) $history[] = ['role'=>'user', 'content'=>'Lịch đang chỉnh thủ công (chỉ đề xuất, khách sẽ quyết định áp dụng): '.json_encode($plannerContext['days'], JSON_UNESCAPED_UNICODE)];
            $locations=$this->catalog->locations();
            $decision=$this->dialogue->understand($message,[...($context['state'] ?? []),'has_plan'=>isset($context['last_plan']),
                'plan_days'=>count($context['last_plan']['dates'] ?? []),'pending_action'=>($context['pending_plan'] ?? false)?($context['pending_request']['intent'] ?? 'plan'):null],$history,$locations,$progress);
            // A clear lookup keyword still triggers the source lookup if the model omits its optional focus.
            if(in_array($decision['intent'],['explore','travel_question'],true)
                && preg_match('/\b(?:khu vui choi|cong vien (?:giai tri|nuoc)|theme park|water park)\b/',\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($message)))) $decision['patch']['place_focus']='entertainment';
            elseif(in_array($decision['intent'],['explore','travel_question'],true)
                && preg_match('/\b(?:an uong|quan an|nha hang|am thuc|ca phe|cafe)\b/',\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($message))))$decision['patch']['place_focus']='food';
            $state=$this->memory->merge($context['state'] ?? [],$decision['patch'],$locations);
            // This editor only requests proposals; it never supplies confirmed calendar dates to commit().
            if ($plannerContext !== null) {
                if(!empty($state['start_date']))$state['proposal_visit_start_date']=$state['start_date'];
                unset($state['start_date'], $state['end_date']);
            }
            if(($context['pending_plan'] ?? false) && $decision['intent']==='preferences' && isset($context['pending_request'])) {
                $decision=[...$decision,...$context['pending_request']];
            }
            $intent=$decision['intent'];$question=null;$components=[];$quick=[];$newPlan=null;$pending=false;
            if ($intent==='out_of_scope') {
                $state=$context['state'] ?? [];
                $text='Mình hỗ trợ tư vấn và lên kế hoạch du lịch. Bạn muốn khám phá điểm đến hay chuẩn bị chuyến đi nào?';
                $quick=['Gợi ý một điểm đến','Tôi muốn lên lịch trình'];
            } elseif (($state['supported'] ?? null)===false) {
                $text='AUREL chưa hỗ trợ lập lịch tại '.$state['destination_name'].'. Mình có thể tư vấn từ các địa phương đang có: '.implode(', ',array_column($locations,'name')).'.';
                $components[]=['type'=>'destinations','items'=>$locations];
            } elseif ($intent==='plan' || $intent==='revise_day' || (($context['pending_plan'] ?? false) && $intent==='preferences')) {
                if (empty($state['location_id'])) {$question='Bạn muốn lên lịch tại địa phương nào?';$pending=true;}
                elseif (empty($state['duration_days'])) {$question='Bạn muốn dành mấy ngày cho chuyến đi này?';$pending=true;}
                elseif (empty($state['budget']) && empty($state['budget_flexible'])) {$question='Bạn dự tính ngân sách khoảng bao nhiêu cho cả chuyến đi?';$pending=true;}
                elseif ($intent!=='revise_day' && empty($state['adults']) && empty($state['allow_assumptions'])) {$question='Chuyến này có bao nhiêu người lớn đi cùng bạn?';$pending=true;}
                if($question) {
                    $text=$question;
                    $quick=empty($state['location_id']) ? array_slice(array_column($locations,'name'),0,3)
                        : (empty($state['duration_days']) ? ['Đi 1 ngày','Đi 2 ngày','Đi 3 ngày']
                        : (empty($state['budget']) && empty($state['budget_flexible']) ? ['Khoảng 2 triệu','Khoảng 5 triệu','Chưa chốt, cứ lên lịch trước'] : ['1 người lớn','2 người lớn','2 người lớn và 1 trẻ em']));
                }
                else {
                    $newPlan=$this->planner->prepare($state,$decision,$context['last_plan'] ?? null,$progress);
                    if (!empty($newPlan['target_window'])) foreach (['activity_window_start','activity_window_end','arrival_time','departure_time'] as $field) {
                        if (array_key_exists($field,$newPlan['state'])) $state[$field]=$newPlan['state'][$field]; else unset($state[$field]);
                    }
                    $text=$intent==='revise_day' ? 'Mình đã điều chỉnh riêng ngày '.$newPlan['target_day'].', giữ nguyên các ngày còn lại.' : 'Mình đã sắp xếp các điểm theo từng ngày, có thời gian di chuyển và khoảng nghỉ để bạn dễ theo dõi.';
                    if ($plannerContext !== null && $intent==='revise_day') {
                        $text='Mình đề xuất điều chỉnh '.(!empty($newPlan['target_window']) ? (($decision['revision_period'] ?? '')==='morning' ? 'buổi sáng ' : 'buổi chiều ') : '').'ngày '.$newPlan['target_day'].'. Các hoạt động ngoài phần này được giữ nguyên; bạn chọn Áp dụng nếu phù hợp.';
                    }
                    if($intent==='plan' && !empty($state['duration_range']))$text='Mình chọn '.$state['duration_days'].' ngày trong khoảng '.$state['duration_range'][0].'–'.$state['duration_range'][1].' ngày bạn nêu để xếp lịch sơ bộ.'.(empty($state['adults'])?' Tạm xếp cho 1 người lớn; bạn có thể bổ sung số người để mình điều chỉnh.':'').' '.$text;
                    $quick=[count($newPlan['dates'])>1 ? 'Ngày 2 nhiều điểm quá' : 'Ngày 1 đi nhẹ nhàng hơn nhé','Gợi ý nơi ở phù hợp'];
                }
            } else {
                $facts=$this->catalog->facts($state,$intent,$progress,$message);
                $topic=($facts['lookup']['focus'] ?? '')==='food'?'chỗ ăn uống':'khu vui chơi';
                if(in_array($facts['lookup']['focus'] ?? '',['entertainment','food'],true) && empty($state['location_id']) && in_array($intent,['explore','travel_question'],true)) {
                    $advice=['message'=>'Bạn muốn tìm '.$topic.' ở tỉnh/thành nào?','question'=>null,'place_ids'=>[],'hotel_ids'=>[],'location_ids'=>[],
                        'quick_replies'=>array_slice(array_column($locations,'name'),0,3)];
                } elseif(($facts['lookup']['status'] ?? '')==='unavailable' && !$facts['places']) {
                    $advice=['message'=>'Mình chưa tra cứu được nguồn trực tuyến lúc này. Điều đó không có nghĩa là địa phương không có '.$topic.'. Bạn thử lại sau một chút nhé.',
                        'question'=>null,'place_ids'=>[],'hotel_ids'=>[],'location_ids'=>[],'quick_replies'=>['Tìm '.$topic.' lại giúp tôi']];
                } elseif(in_array($facts['lookup']['focus'] ?? '',['entertainment','food'],true) && ($facts['lookup']['status'] ?? '')==='complete' && !$facts['places']) {
                    $advice=['message'=>'Mình đã tra cứu nhưng chưa tìm thấy '.$topic.' có nguồn và vị trí đủ rõ trong địa phương này. Bạn muốn tham khảo các điểm tham quan khác không?',
                        'question'=>null,'place_ids'=>[],'hotel_ids'=>[],'location_ids'=>[],'quick_replies'=>['Gợi ý các điểm tham quan khác']];
                } else $advice=$this->dialogue->advise($message,$state,[...$facts,'intent'=>$intent],$history,$decision['question'] ?? null,$progress);
                if($intent==='explore' && !empty($state['location_id']) && !empty($advice['place_ids'])) {
                    $oldQuestion=$advice['question'] ?? null;$knownDays=!empty($state['duration_days'])&&preg_match('/\b(?:may|bao nhieu|\d+)\s*ngay\b/',\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($oldQuestion ?? '')));
                    if(!$oldQuestion || $knownDays) {
                        if($oldQuestion)$advice['message']=trim(str_replace($oldQuestion,'',$advice['message']));
                        $hasStay=in_array($state['stay_mode'] ?? '',['own_accommodation','day_trip'],true);
                        $food=($state['place_focus'] ?? '')==='food';
                        $advice['question']=$food?'Bạn muốn chọn quán theo khẩu vị hay sắp xếp các điểm ăn uống vào lịch đi chơi?'
                            : ($hasStay?'Bạn có muốn mình gợi ý thêm chỗ ăn uống tại '.$state['destination_name'].' không?':'Bạn có muốn mình gợi ý thêm chỗ ăn uống hoặc nơi nghỉ tại '.$state['destination_name'].' không?');
                        $advice['quick_replies']=$food?['Gợi ý quán theo khẩu vị','Xếp các điểm thành lịch trình']
                            : ($hasStay?['Gợi ý chỗ ăn uống','Xếp các điểm thành lịch trình']:['Gợi ý chỗ ăn uống','Gợi ý nơi nghỉ','Xếp các điểm thành lịch trình']);
                    }
                }
                $text=$advice['message'];$question=$advice['question'] ?? null;$quick=$advice['quick_replies'] ?? [];
                if($question && !str_contains($text,$question))$text.="\n\n".$question;
                foreach(['places'=>['place_ids','places'],'hotels'=>['hotel_ids','hotels'],'destinations'=>['location_ids','locations']] as $type=>[$ids,$source]) {
                    $items=array_values(array_filter($facts[$source],fn($item)=>in_array($item['id'],$advice[$ids] ?? [],true)));
                    if($type==='places')$items=app(\App\Services\Places\SheetPlaceCatalog::class)->refreshPlaces($items,$state['location_id'] ?? null);
                    if($items)$components[]=['type'=>$type,'items'=>$items];
                }
                if($facts['warnings'])$components[]=['type'=>'notice','items'=>$facts['warnings']];
                if(isset($state['budget']))$components[]=['type'=>'budget','limit'=>$state['budget'],'status'=>'incomplete','message'=>'Đây là ngân sách bạn dự kiến, chưa phải báo giá chuyến đi.'];
            }
            $remaining=3;
            foreach($components as &$component) if($component['type']==='places') foreach($component['items'] as &$place) {
                $place['opening_check']=app(\App\Services\Places\OpeningHours::class)->check($place['opening_schedule'] ?? null,$plannerContext['visit_start_date'] ?? $state['start_date'] ?? null);
                $place['opening_check']['display']=app(\App\Services\Places\ItineraryHours::class)->displayForPlace($place);
                if(empty($place['image']) && $remaining-- > 0)$place['image']=app(PlacePhoto::class)->forPlace($place,$progress);
            }
            unset($component,$place);
            $text=$this->dialogue->displayText($text);
            $question=$question===null?null:$this->dialogue->displayText($question);
            $quick=array_values(array_filter(array_map($this->dialogue->displayText(...),$quick),fn($reply)=>$reply!==''));
            if($newPlan)$progress?->emit('checking_itinerary');
            $progress?->assertRunning();
            // Provider calls above have no locks or DB writes. Everything below commits atomically.
            $result=$this->writer->run($customer,$key,$operation,$payload,function()use($customer,$sessionId,$revision,$state,$intent,$text,$question,$quick,$components,$newPlan,$pending,$context,$message,$decision,$progress){
                $progress?->assertRunning();
                $session=ChatSession::where('customer_id',$customer->id)->where('status','active')->lockForUpdate()->findOrFail($sessionId);
                abort_unless(($session->context_json['revision'] ?? 0)===$revision,409,'Ngữ cảnh đã thay đổi. Hãy gửi lại trên cuộc trò chuyện mới nhất.');
                $plan=$newPlan ? $this->planner->commit($customer,$newPlan) : ($context['last_plan'] ?? null);
                if($newPlan)$components[]=$this->planner->present($plan);
                $assistant=['intent'=>$intent,'question'=>$question,'quick_replies'=>$quick,'components'=>$components,'state'=>$state,
                    'assumptions'=>$newPlan ? $plan['assumptions'] : [],'context_revision'=>$revision+1];
                $hotel=collect($components)->firstWhere('type','hotels')['items'][0] ?? null;
                $body=['message'=>$text,'session_id'=>$session->id,'hotel_id'=>$hotel['id'] ?? null,'hotel'=>$hotel,'assistant'=>$assistant];
                ChatMessage::create(['session_id'=>$session->id,'role'=>'user','content'=>$message,'intent'=>$intent,'entities_json'=>$decision['patch'],'created_at'=>now()]);
                $bot=ChatMessage::create(['session_id'=>$session->id,'role'=>'bot','content'=>$text,'intent'=>$intent,'entities_json'=>['assistant'=>$assistant],
                    'referenced_hotel_id'=>$hotel['id'] ?? null,'created_at'=>now()]);
                $body['message_id']=$bot->id;
                $session->forceFill(['context_json'=>['version'=>1,'revision'=>$revision+1,'state'=>$state,'last_plan'=>$plan,'pending_plan'=>$pending,
                    'pending_request'=>$pending?array_intersect_key($decision,array_flip(['intent','target_day','revision_instruction','reduce_stops','revision_period'])):null],
                    'title'=>$session->title==='Cuộc trò chuyện mới' ? mb_substr($message,0,80) : $session->title])->save();
                $progress?->assertRunning();
                return ['status'=>200,'body'=>$body,'itinerary_id'=>$newPlan ? $plan['trip_id'] : null];
            });
            return $this->availableResponse($result['body']);
        } finally {$lock->release();}
    }

    private function availableResponse(array $body): array
    {
        $assistant=$body['assistant'] ?? [];$location=$assistant['state']['destination_name'] ?? '';
        if (!$location && !empty($assistant['state']['location_id'])) $location=\App\Models\Location::find($assistant['state']['location_id'])?->name ?? '';
        $availability=app(PlaceAvailability::class);
        foreach ($assistant['components'] ?? [] as $index=>$component) {
            if(($component['type'] ?? '')==='places'){
                $fresh=app(\App\Services\Places\SheetPlaceCatalog::class)->refreshPlaces($component['items'] ?? [],$assistant['state']['location_id'] ?? null);
                if(count($fresh)!==count($component['items'] ?? []))$this->dialogue->fail('Một địa điểm trong gợi ý cũ đã bị ngừng gợi ý. Hãy gửi yêu cầu mới.','stale_closed_place',422);
                $body['assistant']['components'][$index]['items']=$fresh;
            }
            $items=($component['type'] ?? '')==='places' ? ($component['items'] ?? []) : (($component['type'] ?? '')==='timeline' ? array_merge([], ...array_column($component['days'] ?? [],'items')) : []);
            foreach ($items as $item) if ($availability->blocked($item,$location)) {
                $this->dialogue->fail('Gợi ý cũ có địa điểm đã được báo đóng cửa. Hãy gửi yêu cầu mới để AI chọn địa điểm khác.','stale_closed_place',422);
            }
        }
        return $body;
    }
}
