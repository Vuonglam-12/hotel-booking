<?php

namespace App\Services\Itinerary;

use App\Models\Customer;
use App\Models\Itinerary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Prepare outside transactions, then commit only verified activities in the caller's transaction. */
final class ConversationalPlanner
{
    public function __construct(private GenerationService $generator, private TravelCatalog $catalog, private TravelDialogue $dialogue) {}

    public function prepare(array $state, array $decision, ?array $previous, ?TravelProgress $progress = null): array
    {
        $assumptions = [];
        if(!empty($state['duration_range']))$assumptions[]='Bạn dự kiến '.$state['duration_range'][0].'–'.$state['duration_range'][1].' ngày; lịch sơ bộ chọn '.$state['duration_days'].' ngày.';
        $provisional = empty($state['start_date']) || empty($state['end_date']);
        // This anchor is internal scheduling arithmetic, never a claimed travel date or persisted trip.
        $start = $provisional ? ($state['proposal_visit_start_date'] ?? now('Asia/Ho_Chi_Minh')->addDay()->toDateString()) : $state['start_date'];
        $end = $provisional ? CarbonImmutable::parse($start)->addDays($state['duration_days'] - 1)->toDateString() : $state['end_date'];
        if ($provisional) $assumptions[] = 'Lịch đang xếp theo ngày thứ nhất, ngày thứ hai; bạn có thể bổ sung ngày khởi hành để chốt chi tiết.';
        foreach (['adults'=>[1,'Tạm xếp cho 1 người lớn theo lựa chọn lên lịch trước.'],'stay_mode'=>['own_accommodation','Lịch hiện tập trung vào tham quan; bạn có thể yêu cầu gợi ý chỗ ở.'],
            'transport'=>['drive','Di chuyển tạm xếp bằng ô tô; có thể đổi sang đi bộ hoặc xe đạp.'],
            'activity_window_start'=>['08:00','Xếp theo các khung sáng, trưa, chiều, tối; khuya từ 21:00 khi có điểm phù hợp.'],'activity_window_end'=>['23:59','Khung khuya phụ thuộc giờ tham khảo của địa điểm.']] as $field => [$default,$note]) {
            if (empty($state[$field])) {$state[$field]=$default;$assumptions[]=$note;}
        }
        if (!empty($state['requested_transport'])) $this->dialogue->fail('Nguồn định tuyến hiện hỗ trợ đi bộ, ô tô và xe đạp. Bạn muốn dùng phương tiện nào cho lịch sơ bộ?', 'transport_needs_clarification', 422);
        foreach (['children','infants'] as $field) {
            if (!isset($state[$field])) $state[$field]=array_fill(0,$state[$field.'_count'] ?? 0,['age'=>null,'unit'=>'years']);
            if (array_filter($state[$field],fn($p)=>($p['age'] ?? null)===null)) $assumptions[]='Chưa có đủ tuổi trẻ nhỏ; chưa xác minh chính sách sức chứa hoặc giá trẻ em.';
        }
        $dates=[];for($day=CarbonImmutable::parse($start);$day->toDateString()<=$end;$day=$day->addDay()) $dates[]=$day->toDateString();
        $data=['location_id'=>$state['location_id'],'start_date'=>$start,'end_date'=>$end,'adults'=>$state['adults'],
            'children'=>$state['children'],'infants'=>$state['infants'],'stay_mode'=>$state['stay_mode'],'estimated_budget'=>$state['budget'] ?? null,
            'activity_window_confirmed'=>true,'activity_window_start'=>$state['activity_window_start'],'activity_window_end'=>$state['activity_window_end'],
            'arrival_time'=>$state['arrival_time'] ?? null,'departure_time'=>$state['departure_time'] ?? null,'transport'=>$state['transport'],
            'pace'=>$state['pace'] ?? 'moderate','interests'=>$state['interests'] ?? [],'food_restrictions'=>$state['food_restrictions'] ?? [],
            'requirements'=>implode('; ',$state['constraints'] ?? []),'excluded_ids'=>$state['excluded_ids'] ?? [],'selected_dates'=>$dates,'_include_breaks'=>true,'_provisional'=>$provisional,'_hours_date_confirmed'=>!$provisional || !empty($state['proposal_visit_start_date'])];
        if($state['activity_window_start']!=='08:00' || $state['activity_window_end']!=='23:59')$data['_slot_scope']=['start'=>$state['activity_window_start'],'end'=>$state['activity_window_end']];
        if ($data['stay_mode']==='day_trip') {
            $data['arrival_time'] ??= $data['activity_window_start'];$data['departure_time'] ??= $data['activity_window_end'];
        }
        if (($data['stay_mode']==='day_trip' ? $data['departure_time'] : $data['activity_window_end']) <= ($data['stay_mode']==='day_trip' ? $data['arrival_time'] : $data['activity_window_start'])) {
            $this->dialogue->fail('Giờ kết thúc phải sau giờ bắt đầu. Bạn muốn dành khoảng thời gian nào để khám phá?', 'invalid_activity_window', 422);
        }
        $target = null;$exclude=[];$excludedPlaces=[];$targetWindow=null;
        $period=$decision['revision_period'] ?? null;
        if ($decision['intent']==='revise_day') {
            $target=$decision['target_day'] ?? null;
            if (!$previous || !$target || !isset($previous['dates'][$target-1]) || $previous['data']['location_id']!==$state['location_id']) {
                $this->dialogue->fail('Bạn muốn chỉnh ngày nào của lịch đã có? Mình chưa thể xác định đúng ngày cần sửa.', 'revision_needs_day', 422);
            }
            // Changes to trip-wide facts need a new plan; never silently mutate unrelated days.
            foreach (['start_date','end_date','duration_days','adults','children','infants','children_count','infants_count','stay_mode','transport','arrival_time','departure_time','activity_window_start','activity_window_end'] as $field) {
                if (in_array($period,['morning','afternoon'],true) && in_array($field,['activity_window_start','activity_window_end','arrival_time','departure_time'],true)) continue;
                if (($state[$field] ?? null) !== ($previous['state'][$field] ?? null)) $this->dialogue->fail('Thông tin chung của chuyến đi đã đổi. Bạn muốn lập lại lịch theo thông tin mới trước khi chỉnh riêng một ngày không?', 'trip_facts_changed', 422);
            }
            $provisional=$previous['provisional'];$dates=$previous['dates'];$data=$previous['data'];
            $data['estimated_budget']=$state['budget'] ?? null;
            foreach(['pace','interests','food_restrictions','excluded_ids'] as $preference)if(isset($state[$preference]))$data[$preference]=$state[$preference];
            $data['selected_dates']=[$dates[$target-1]];
            $data['requirements']=trim($data['requirements'].'; '.($decision['revision_instruction'] ?? 'Điều chỉnh ngày theo yêu cầu khách'));
            if (in_array($period,['morning','afternoon'],true)) {
                $targetWindow=['start'=>$period==='afternoon' ? '14:00' : '08:00','end'=>$period==='morning' ? '11:30' : '17:30'];
                $data['_slot_scope']=$targetWindow;
                if ($targetWindow['end'] <= $targetWindow['start']) $this->dialogue->fail('Buổi bạn muốn thêm nằm ngoài khung giờ của chuyến đi. Bạn hãy điều chỉnh giờ bắt đầu hoặc kết thúc trước nhé.','revision_window_unavailable',422);
                $data['activity_window_start']=$targetWindow['start']; $data['activity_window_end']=$targetWindow['end'];
                if ($data['stay_mode']==='day_trip') { $data['arrival_time']=max($data['arrival_time'] ?? $targetWindow['start'],$targetWindow['start']); $data['departure_time']=min($data['departure_time'] ?? $targetWindow['end'],$targetWindow['end']); }
                foreach (['activity_window_start','activity_window_end','arrival_time','departure_time'] as $field) {
                    if (array_key_exists($field,$previous['state'])) $state[$field]=$previous['state'][$field]; else unset($state[$field]);
                }
            }
            foreach ($previous['prepared']['activities'] as $activity) if (isset($activity['place']) && ($activity['date']!==$data['selected_dates'][0]
                || $targetWindow && (($activity['end_time'] ?? '') <= $targetWindow['start'] || ($activity['start_time'] ?? '') >= $targetWindow['end']))) {
                $exclude[]=$activity['place']['provider_place_id'];$excludedPlaces[]=$activity['place'];
                $data['_budget_spent']=($data['_budget_spent'] ?? 0)+($activity['ticket_estimate']['amount'] ?? 0);
            }
            $oldCount=count(array_filter($previous['prepared']['activities'],fn($a)=>$a['date']===$data['selected_dates'][0] && isset($a['place'])));
            if ($decision['reduce_stops'] ?? false) {$data['_min_stops']=1;$data['_max_stops']=max(1,$oldCount-1);}
        }
        $progress?->planning($target);
        $pool=$this->catalog->pool($state['location_id'],count($dates)>5,'general',$progress);$prepared=null;
        if ($area=app(GeoapifyClient::class)->planningArea($pool['region'])) $assumptions[]=$area['notice'];
        if ($targetWindow) {
            $keepNames=[];
            foreach ($previous['prepared']['activities'] as $activity) if ($activity['date']!==$dates[$target-1]
                || empty($activity['start_time']) || empty($activity['end_time']) || $activity['end_time'] <= $targetWindow['start'] || $activity['start_time'] >= $targetWindow['end']) {
                $keepNames[]=mb_strtolower(trim($activity['place']['name'] ?? $activity['title'] ?? ''));
            }
            $pool['places']=array_values(array_filter($pool['places'],fn($place)=>!in_array(mb_strtolower(trim($place['name'])),$keepNames,true)));
        }
        foreach (array_chunk($data['selected_dates'],5) as $chunk) {
            $part=$this->generator->prepare([...$data,'selected_dates'=>$chunk,'_exclude_ids'=>$exclude,'_exclude_places'=>$excludedPlaces],$pool,$progress);
            $prepared ??= ['title'=>$part['title'],'region'=>$part['region'],'activities'=>[],'warnings'=>[]];
            array_push($prepared['activities'],...$part['activities']);
            array_push($prepared['warnings'],...($part['warnings'] ?? []));
            $assumptions=array_merge($assumptions,$part['warnings'] ?? []);
            foreach ($part['activities'] as $activity) if(isset($activity['place'])){
                $exclude[]=$activity['place']['provider_place_id'];$excludedPlaces[]=$activity['place'];
                $data['_budget_spent']=($data['_budget_spent'] ?? 0)+($activity['ticket_estimate']['amount'] ?? 0);
            }
        }
        if ($target) {
            $activities=array_values(array_filter($previous['prepared']['activities'],fn($a)=>$a['date']!==$dates[$target-1]
                || $targetWindow && (empty($a['start_time']) || empty($a['end_time']) || $a['end_time'] <= $targetWindow['start'] || $a['start_time'] >= $targetWindow['end'])));
            array_push($activities,...$prepared['activities']);usort($activities,fn($a,$b)=>[$a['date'],$a['order_in_day']]<=>[$b['date'],$b['order_in_day']]);
            if ($targetWindow) {
                usort($activities,fn($a,$b)=>[$a['date'],$a['start_time'] ?? '99:99',$a['order_in_day']]<=>[$b['date'],$b['start_time'] ?? '99:99',$b['order_in_day']]);
                $orders=[];foreach($activities as &$activity)$activity['order_in_day']=$orders[$activity['date']]=($orders[$activity['date']] ?? 0)+1;unset($activity);
            }
            $prepared=[...$prepared,'title'=>$previous['prepared']['title'],'region'=>$prepared['region'],'activities'=>$activities,'warnings'=>$prepared['warnings'] ?? []];
        }
        foreach ($prepared['activities'] as $activity) if (app(PlaceAvailability::class)->blocked($activity['place'] ?? ['name'=>$activity['title']],$state['destination_name'] ?? $pool['region']['name'])) {
            $this->dialogue->fail('Lịch đang giữ một địa điểm đã được báo đóng cửa. Hãy thay địa điểm đó trước khi tiếp tục.','place_permanently_closed',422);
        }
        $remaining=3;
        foreach($prepared['activities'] as &$activity)if(isset($activity['place']) && empty($activity['place']['image'])) {
            $activity['place']['image']=app(PlacePhoto::class)->known($activity['place']);
            if(empty($activity['place']['image']) && $remaining-- > 0)$activity['place']['image']=app(PlacePhoto::class)->forPlace($activity['place'],$progress);
        }
        unset($activity);
        if ($targetWindow) foreach (['activity_window_start','activity_window_end','arrival_time','departure_time'] as $field) $data[$field]=$previous['data'][$field] ?? null;
        unset($data['_budget_spent']);
        $assumptions=array_merge($assumptions,app(PlanningPolicy::class)->warnings($prepared['activities'],$data['estimated_budget']));
        return ['data'=>[...$data,'selected_dates'=>$dates],'dates'=>$dates,'prepared'=>$prepared,'provisional'=>$provisional,
            'assumptions'=>array_values(array_unique($assumptions)),'state'=>$state,'target_day'=>$target,'target_window'=>$targetWindow,
            'trip_id'=>$target ? ($previous['trip_id'] ?? null) : null,'trip_revision'=>$target ? ($previous['trip_revision'] ?? null) : null];
    }

    public function commit(Customer $customer,array $plan): array
    {
        if ($plan['provisional']) return $plan;
        if (!$plan['target_day']) {
            $trip=app(TripService::class)->create($customer,$plan['data']);
            $this->generator->persist($trip,$plan['prepared']);
        } else {
            $trip=app(TripService::class)->owned($customer,$plan['trip_id'],true);
            app(WriteGuard::class)->revision($trip,$plan['trip_revision']);
            abort_unless(in_array($trip->status,['draft','saved'],true),409,'Chuyến đi hiện không thể chỉnh sửa.');
            $date=$plan['dates'][$plan['target_day']-1];
            $items=DB::table('itinerary_item')->where('itinerary_id',$trip->id)->where('calendar_day_id',DB::table('itinerary_calendar_day')->where('itinerary_id',$trip->id)->where('date',$date)->value('id'));
            $ids=(clone $items)->pluck('id');$snapshotIds=(clone $items)->whereNotNull('place_snapshot_id')->pluck('place_snapshot_id');
            DB::table('itinerary_cost_line')->where('itinerary_id',$trip->id)->whereIn('item_id',$ids)->delete();
            $items->delete();DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip->id)->whereIn('id',$snapshotIds)->delete();
            $this->generator->persist($trip,[...$plan['prepared'],'activities'=>array_values(array_filter($plan['prepared']['activities'],fn($a)=>$a['date']===$date))],false);
            $trip->forceFill(['revision'=>$trip->revision+1,'status'=>'draft','estimated_budget'=>$plan['data']['estimated_budget'],'updated_at'=>now()])->save();
        }
        return [...$plan,'trip_id'=>$trip->id,'trip_revision'=>$trip->revision];
    }

    public function present(array $plan): array
    {
        $days=[];$ticketTotal=0;$unpriced=0;$partialChildren=0;$knownTickets=0;
        foreach($plan['dates'] as $index=>$date) {
            $items=[];
            foreach($plan['prepared']['activities'] as $activity) {
                if($activity['date']!==$date)continue;$place=$activity['place'] ?? [];
                $estimate=$activity['ticket_estimate'] ?? null;
                if(isset($activity['place'])){if($estimate){$ticketTotal+=$estimate['amount'];$knownTickets++;}else $unpriced++;$partialChildren+=$estimate['unknown_children'] ?? 0;}
                $items[]=['title'=>$place['name'] ?? $activity['title'],'description'=>ActivityCopy::description($activity['description']),'start_time'=>$activity['start_time'],'end_time'=>$activity['end_time'],
                    'kind'=>$activity['kind'] ?? 'place','coordinates'=>$place['coordinates'] ?? null,'address'=>$place['address'] ?? null,
                    'source'=>$place['source'] ?? null,'source_url'=>$place['source_url'] ?? null,'travel_from_previous'=>$activity['travel'] ?? null,
                    ...array_intersect_key($place,array_flip(['maps_url','maps_link_type','price_reference'])),
                    'image'=>$place['image'] ?? null,'source_place_id'=>$place['source_place_id'] ?? null,
                    'provider'=>$place['provider'] ?? null,'item_type'=>$place['item_type'] ?? 'activity','wikidata_id'=>$place['wikidata_id'] ?? null,
                    'reason'=>$activity['reason'] ?? null,'planning_slot'=>$activity['planning_slot'] ?? null,'end_day_offset'=>$activity['end_day_offset'] ?? 0,'hours_status'=>$activity['hours_status'] ?? null,
                    'opening_display'=>app(\App\Services\Places\ItineraryHours::class)->displayForPlace($place),
                    'opening_check'=>app(\App\Services\Places\OpeningHours::class)->check($place['opening_schedule'] ?? null,(!empty($plan['data']['_hours_date_confirmed']) || !$plan['provisional'])?$date:null,$activity['start_time'],$activity['end_time'],(int)($activity['end_day_offset'] ?? 0)),
                    'ticket'=>$place['ticket'] ?? null,'ticket_estimate'=>$estimate,'regular_opening'=>$place['regular_opening'] ?? null,
                    'duration_estimated'=>true,'opening_hours_verified'=>false,'price_status'=>$estimate['status'] ?? 'unknown'];
            }
            $days[]=['day_number'=>$index+1,'date'=>$plan['provisional'] ? null : $date,'items'=>$items];
        }
        return ['type'=>'timeline','title'=>$plan['prepared']['title'],'days'=>$days,'target_day'=>$plan['target_day'],'target_window'=>$plan['target_window'] ?? null,'trip_id'=>$plan['trip_id'],'revision'=>$plan['trip_revision'],'transport'=>$plan['data']['transport'],
            'planning_metadata'=>$plan['prepared']['planning_metadata'] ?? null,'pace'=>$plan['data']['pace'] ?? 'moderate','interests'=>$plan['data']['interests'] ?? [],
            'visit_start_date'=>!empty($plan['data']['_hours_date_confirmed'])?$plan['data']['start_date']:null,
            'provisional'=>$plan['provisional'],'assumptions'=>$plan['assumptions'],'planning_area'=>app(GeoapifyClient::class)->planningArea($plan['prepared']['region'] ?? []),'budget'=>['limit'=>$plan['data']['estimated_budget'],
            'flexible'=>$plan['state']['budget_flexible'] ?? false,'ticket_total'=>$ticketTotal,'ticket_known_places'=>$knownTickets,'unpriced_places'=>$unpriced,'children_needing_height'=>$partialChildren,
            'status'=>$plan['data']['estimated_budget']!==null && $ticketTotal>$plan['data']['estimated_budget']?'over_budget':'incomplete',
            'over_budget_amount'=>$plan['data']['estimated_budget']===null?null:max(0,$ticketTotal-$plan['data']['estimated_budget']),
            'warnings'=>app(PlanningPolicy::class)->warnings($plan['prepared']['activities'],$plan['data']['estimated_budget']),
            'prices_verified'=>false,'message'=>'Các khoản vé có nguồn được tách ở từng điểm. Ăn uống, chỗ ở và di chuyển chưa có giá thì chưa được cộng vào tổng.'],
            'party'=>['adults'=>$plan['data']['adults'],'children'=>count($plan['data']['children']),'infants'=>count($plan['data']['infants'])],
            'warnings'=>array_values(array_unique([...app(PlanningPolicy::class)->warnings($plan['prepared']['activities'],$plan['data']['estimated_budget']),
                ...($plan['prepared']['warnings'] ?? []),
                ($plan['prepared']['region']['provider'] ?? '')==='google_sheet'?PlanningPolicy::UNVERIFIED_TRANSFER_NOTICE:'Thời lượng trải nghiệm là gợi ý; thời gian tuyến đi lấy từ Geoapify, lịch có chừa thêm thời gian chuyển điểm.']))];
    }
}
