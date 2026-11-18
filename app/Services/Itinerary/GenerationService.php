<?php

namespace App\Services\Itinerary;

use App\Models\Itinerary;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerationService
{
    public function __construct(private GeoapifyClient $geo) {}

    public function prepare(array $data, ?array $pool = null, ?TravelProgress $progress = null): array
    {
        if (($data['stay_mode'] ?? '') === 'day_trip') {
            $data['activity_window_start'] = $data['arrival_time'] ?? null;
            $data['activity_window_end'] = $data['departure_time'] ?? null;
        }
        $dates = $data['selected_dates']; sort($dates);
        if($dates[0]<now('Asia/Ho_Chi_Minh')->toDateString())$this->geo->unavailable('Ngày lên lịch không được ở trong quá khứ.','generation_date_in_past',422);
        if (!$data['activity_window_confirmed'] || empty($data['activity_window_start']) || empty($data['activity_window_end']) || !in_array($data['transport'] ?? '', ['walk','drive','bicycle'], true)) {
            $this->geo->unavailable('Chọn phương tiện và xác nhận khung giờ hoạt động trước khi tạo lịch.', 'generation_window_required', 422);
        }
        $key = config('services.groq.api_key');
        if (!is_string($key) || trim($key) === '') $this->geo->unavailable('Chưa cấu hình dịch vụ tạo lịch trình AI.', 'groq_key_missing');
        $model = config('services.groq.model');
        if (!is_string($model) || trim($model) === '') $this->geo->unavailable('Mô hình AI hiện chưa khả dụng. Vui lòng thử lại sau.', 'groq_model_unavailable');
        $location = Location::findOrFail($data['location_id']);
        $pool ??= app(TravelCatalog::class)->pool($location->id,false,'general',$progress);
        return app(FixedSlotPlanner::class)->prepare($data,$pool,$progress);
    }

    public function persist(Itinerary $trip, array $prepared, bool $replaceTitle = true): void
    {
        foreach ($prepared['activities'] as $activity) if (isset($activity['place']) && app(PlaceAvailability::class)->blocked($activity['place'],$trip->location->name)) {
            $this->geo->unavailable('Một địa điểm đã được báo đóng cửa. Hãy tạo lại lịch để chọn địa điểm khác.','place_permanently_closed',422);
        }
        $region = $prepared['region'];
        $regionProvider=$region['provider'] ?? 'geoapify';
        $regionVerified=$regionProvider==='geoapify' && !$this->geo->isSearchArea($region);
        DB::table('itinerary_region_mapping')->updateOrInsert(['location_id'=>$trip->location_id,'provider'=>$regionProvider], [
            'provider_region_id'=>$region['place_id'],'verification_status'=>$regionVerified?'verified':($regionProvider==='google_sheet'?'unverified':'search_area'),'boundary'=>json_encode($region['boundary'],JSON_THROW_ON_ERROR),
            'verification_evidence'=>json_encode($region['evidence'],JSON_THROW_ON_ERROR),'verified_at'=>$regionVerified?now():null]);
        $mappingId = DB::table('itinerary_region_mapping')->where('location_id',$trip->location_id)->where('provider',$regionProvider)->value('id');
        if ($replaceTitle) $trip->forceFill(['title'=>$prepared['title']])->save();
        foreach ($prepared['activities'] as $activity) {
            $calendar = DB::table('itinerary_calendar_day')->where('itinerary_id',$trip->id)->where('date',$activity['date'])->first();
            if (($activity['kind'] ?? '') === 'break') {
                DB::table('itinerary_item')->insert(['itinerary_id'=>$trip->id,'calendar_day_id'=>$calendar->id,'day_number'=>CarbonImmutable::parse($trip->start_date)->diffInDays(CarbonImmutable::parse($activity['date']))+1,
                    'order_in_day'=>$activity['order_in_day'],'item_type'=>'activity','title'=>$activity['title'],'description'=>$activity['description'],
                    'start_time'=>$activity['start_time'],'end_time'=>$activity['end_time'],'estimated_cost'=>null]);
                continue;
            }
            $snapshot = [...$activity['place'],'planning_area'=>$this->geo->planningArea($region),'suggested_duration_minutes'=>$activity['duration_minutes'],'travel_from_previous'=>$activity['travel'],'ticket_estimate'=>$activity['ticket_estimate'] ?? null,
                'planning_slot'=>$activity['planning_slot'] ?? null,'planning_category'=>$activity['planning_category'] ?? null,'selection_reason'=>$activity['reason'] ?? null,
                'hours_status'=>$activity['hours_status'] ?? null,'end_day_offset'=>$activity['end_day_offset'] ?? 0];
            $snapshotId = DB::table('itinerary_place_snapshot')->insertGetId(['itinerary_id'=>$trip->id,'provider'=>$activity['place']['provider'] ?? 'geoapify','attraction_id'=>$activity['place']['attraction_id'] ?? null,
                'provider_place_id'=>$activity['place']['provider_place_id'],'region_mapping_id'=>$mappingId,'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'region_verified_at'=>$regionVerified?now():null]);
            $itemId = DB::table('itinerary_item')->insertGetId(['itinerary_id'=>$trip->id,'calendar_day_id'=>$calendar->id,'place_snapshot_id'=>$snapshotId,
                'attraction_id'=>$activity['place']['attraction_id'] ?? null,'day_number'=>CarbonImmutable::parse($trip->start_date)->diffInDays(CarbonImmutable::parse($activity['date']))+1,'order_in_day'=>$activity['order_in_day'],
                'item_type'=>$activity['place']['item_type'],'title'=>$activity['place']['name'],'description'=>$activity['description'],
                'start_time'=>$activity['start_time'],'end_time'=>$activity['end_time'],'estimated_cost'=>null]);
            $quote=$activity['ticket_estimate'] ?? null;
            DB::table('itinerary_cost_line')->insert(['itinerary_id'=>$trip->id,'calendar_day_id'=>$calendar->id,'item_id'=>$itemId,'category'=>'activity',
                'price_status'=>$quote['status'] ?? 'unknown','unit'=>'group','currency'=>'VND','unit_amount'=>$quote['amount'] ?? null,'quantity'=>1,
                'source'=>$quote?'Bảng giá công bố':$activity['place']['source'],'source_reference'=>$quote['source_url'] ?? null,
                'priced_at'=>$quote?now():null]);
            if(($quote['unknown_children'] ?? 0)>0)DB::table('itinerary_cost_line')->insert(['itinerary_id'=>$trip->id,'calendar_day_id'=>$calendar->id,'item_id'=>$itemId,'category'=>'activity',
                'price_status'=>'unknown','unit'=>'group','currency'=>'VND','unit_amount'=>null,'quantity'=>1,'source'=>'Vé trẻ em cần chiều cao']);
            DB::table('itinerary_calendar_day')->where('id',$calendar->id)->update(['planning_status'=>'planned']);
        }
        app(PlanningMetadata::class)->rebuild($trip,$prepared['planning_metadata'] ?? null);
    }
}
