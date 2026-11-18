<?php

namespace App\Services\Itinerary;

use App\Models\Customer;
use App\Models\Itinerary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The two-step editor uses the existing calendar, snapshots, prices and revision guard. */
final class PlannerService
{
    public function __construct(private TripService $trips) {}

    public function state(array $data): array
    {
        return ['location_id' => $data['location_id'], 'duration_days' => $data['duration_days'],
            ...(!empty($data['visit_start_date'])?['start_date'=>$data['visit_start_date'],'end_date'=>CarbonImmutable::parse($data['visit_start_date'])->addDays($data['duration_days']-1)->toDateString()]:[]),
            'adults' => $data['people'], 'children' => [], 'infants' => [], 'stay_mode' => $data['stay_mode'],
            'budget' => $data['estimated_budget'], 'budget_flexible' => $data['estimated_budget'] === null,
            ...array_intersect_key($data,array_flip(['pace','interests','food_restrictions','excluded_ids']))];
    }

    public function conversationContext(array $data): array
    {
        $start = !empty($data['visit_start_date'])?CarbonImmutable::parse($data['visit_start_date'],'Asia/Ho_Chi_Minh'):now('Asia/Ho_Chi_Minh')->addDay()->startOfDay();
        $dates = array_map(fn ($day) => $start->addDays($day)->toDateString(), range(0, $data['duration_days'] - 1));
        $state = [...$this->state($data), 'destination_name' => \App\Models\Location::findOrFail($data['location_id'])->name,
            'transport' => 'drive', 'activity_window_start' => '08:00', 'activity_window_end' => '23:59',
            'proposal_visit_start_date'=>$data['visit_start_date'] ?? null];
        $activities = [];
        foreach ($data['days'] ?? [] as $day => $entries) foreach ($entries['items'] as $order => $item) {
            // Client text is a manual activity, never a verified catalog place or price.
            $activities[] = ['date' => $dates[$day], 'kind' => 'break', 'title' => $item['title'],
                'description' => trim(($item['place_name'] ?? '').' '.($item['description'] ?? '')),
                'start_time' => $item['start_time'] ?? null, 'end_time' => $item['end_time'] ?? null, 'order_in_day' => $order + 1];
        }
        $tripData = ['location_id' => $data['location_id'], 'start_date' => $dates[0], 'end_date' => end($dates),
            'adults' => $data['people'], 'children' => [], 'infants' => [], 'stay_mode' => $data['stay_mode'],
            'estimated_budget' => $data['estimated_budget'], 'selected_dates' => $dates, 'transport' => 'drive',
            'activity_window_start' => '08:00', 'activity_window_end' => '23:59', 'activity_window_confirmed' => true,
            'arrival_time' => $data['stay_mode'] === 'day_trip' ? '08:00' : null,
            'departure_time' => $data['stay_mode'] === 'day_trip' ? '23:59' : null, 'pace' => $data['pace'] ?? 'moderate',
            'interests' => $data['interests'] ?? [], 'food_restrictions' => $data['food_restrictions'] ?? [], 'excluded_ids'=>$data['excluded_ids'] ?? [],'requirements' => '', '_provisional' => true, '_include_breaks' => true,'_hours_date_confirmed'=>!empty($data['visit_start_date'])];
        return ['state' => $state, 'last_plan' => ['state' => $state, 'data' => $tripData, 'dates' => $dates,
            'prepared' => ['title' => 'Lịch trình của bạn', 'activities' => $activities], 'provisional' => true,
            'assumptions' => [], 'target_day' => null, 'trip_id' => null, 'trip_revision' => null]];
    }

    public function create(Customer $customer, array $data, ?array $plan = null): Itinerary
    {
        // Calendar dates are internal anchors; the editor and PDF only show day numbers.
        $start = !empty($data['visit_start_date'])?CarbonImmutable::parse($data['visit_start_date']):($plan ? CarbonImmutable::parse($plan['data']['start_date']) : now('Asia/Ho_Chi_Minh')->addDay()->startOfDay());
        $dates = array_map(fn ($day) => $start->addDays($day)->toDateString(), range(0, $data['duration_days'] - 1));
        $trip = $this->trips->create($customer, ['location_id' => $data['location_id'], 'start_date' => $dates[0],
            'end_date' => end($dates), 'adults' => $data['people'], 'children' => [], 'infants' => [],
            'stay_mode' => $data['stay_mode'], 'estimated_budget' => $data['estimated_budget'], 'selected_dates' => array_slice($dates, 0, 5),
            'activity_window_confirmed' => false]);
        $trip->forceFill(['is_provisional' => empty($data['visit_start_date'])])->save();
        if ($plan) app(GenerationService::class)->persist($trip, $plan['prepared']);
        elseif (isset($data['days'])) $this->items($trip, $data['days']);
        $this->planningMetadata($trip,$data);
        return $trip->refresh();
    }

    public function save(Customer $customer, int $id, array $data): Itinerary
    {
        $trip = $this->trips->owned($customer, $id, true);
        app(WriteGuard::class)->revision($trip, $data['expected_revision']);
        abort_unless(in_array($trip->status, ['draft', 'saved'], true), 409, 'Lịch trình này hiện chỉ có thể xem.');
        abort_if(count($trip->children ?? []) || count($trip->infants ?? []) || $trip->hotel_id || DB::table('itinerary_lodging_room')->where('itinerary_id', $id)->exists(), 409, 'Lịch có thông tin khách hoặc lưu trú chi tiết cần được giữ nguyên.');
        abort_unless($trip->location_id === $data['location_id'], 422, 'Đổi điểm đến cần tạo một lịch mới để giữ nguyên lịch cũ.');
        abort_unless(isset($data['days']), 422, 'Cần gửi các ngày và hoạt động của lịch trình.');
        $start = !empty($data['visit_start_date'])?CarbonImmutable::parse($data['visit_start_date']):CarbonImmutable::parse($trip->start_date);
        $dates = array_map(fn ($day) => $start->addDays($day)->toDateString(), range(0, $data['duration_days'] - 1));
        foreach ($dates as $date) DB::table('itinerary_calendar_day')->updateOrInsert(['itinerary_id' => $id, 'date' => $date], []);
        $trip->forceFill(['total_days' => $data['duration_days'], 'start_date'=>$dates[0], 'end_date' => end($dates), 'adults' => $data['people'],
            'is_provisional'=>array_key_exists('visit_start_date',$data)?empty($data['visit_start_date']):$trip->is_provisional,
            'estimated_budget' => $data['estimated_budget'], 'stay_mode' => $data['stay_mode'],
            'revision' => $trip->revision + 1, 'status' => 'saved', 'updated_at' => now()])->save();
        $this->items($trip, $data['days']);
        DB::table('itinerary_calendar_day')->where('itinerary_id', $id)->whereNotIn('date', $dates)->delete();
        $this->planningMetadata($trip,$data);
        return $trip->refresh();
    }

    private function planningMetadata(Itinerary $trip,array $data): void
    {
        $incoming=null;
        if(!empty($data['planning_reference']['message_id'])){
            $json=DB::table('chat_message as m')->join('chat_session as s','s.id','=','m.session_id')
                ->where('s.customer_id',$trip->customer_id)->where('m.id',$data['planning_reference']['message_id'])->where('m.role','bot')->value('m.entities_json');
            $assistant=$json?json_decode($json,true)['assistant'] ?? null:null;
            abort_unless($assistant && (int)($assistant['state']['location_id'] ?? 0)===$trip->location_id,422,'Đề xuất không thuộc điểm đến hoặc tài khoản của lịch trình.');
            $timeline=collect($assistant['components'] ?? [])->firstWhere('type','timeline');
            $incoming=$timeline['planning_metadata'] ?? null;
            abort_unless(is_array($incoming) && ($incoming['policy'] ?? '')==='fixed_slots',422,'Đề xuất không có thông tin khung giờ phù hợp.');
        }
        if(!empty($data['pace']) || isset($data['interests']))$trip->forceFill([
            'pace'=>($data['pace'] ?? $trip->pace)==='normal'?'moderate':($data['pace'] ?? $trip->pace),
            'interests'=>$data['interests'] ?? $trip->interests])->save();
        app(PlanningMetadata::class)->rebuild($trip,$incoming);
    }

    private function items(Itinerary $trip, array $days): void
    {
        $planningArea=app(GeoapifyClient::class)->cachedPlanningArea($trip->location);
        $hours=app(\App\Services\Places\ItineraryHours::class);
        $old = DB::table('itinerary_item')->where('itinerary_id', $trip->id)->get()->keyBy('id');
        $snapshots = DB::table('itinerary_place_snapshot')->where('itinerary_id', $trip->id)->get()->keyBy('id');
        $calendars = DB::table('itinerary_calendar_day')->where('itinerary_id', $trip->id)->whereBetween('date',[$trip->start_date->toDateString(),$trip->end_date->toDateString()])->orderBy('date')->get();
        $scheduleChanged = array_sum(array_map(fn ($day) => count($day['items']), $days)) !== $old->count();
        foreach ($days as $dayNumber => $day) foreach ($day['items'] as $order => $item) {
            $existing = $old->get($item['id'] ?? null);
            $details = json_decode($snapshots->get($existing?->place_snapshot_id)?->snapshot ?? '{}', true);
            $place = array_key_exists('manual_place_name', $details) ? $details['manual_place_name'] : ($details['name'] ?? $details['address'] ?? null);
            if (! $existing || $existing->day_number !== $dayNumber + 1 || $existing->order_in_day !== $order + 1
                || substr($existing->start_time ?? '', 0, 5) !== ($item['start_time'] ?? '')
                || substr($existing->end_time ?? '', 0, 5) !== ($item['end_time'] ?? '')
                || (int)($details['end_day_offset'] ?? 0)!==(int)($item['end_day_offset'] ?? 0)
                || trim($place ?? '') !== trim($item['place_name'] ?? '')) $scheduleChanged = true;
        }
        $keep = []; $photoMessages = [];
        foreach ($days as $dayNumber => $day) {
            $calendar = $calendars[$dayNumber];
            DB::table('itinerary_calendar_day')->where('id', $calendar->id)->update(['planning_status' => $day['items'] ? 'planned' : 'free']);
            foreach ($day['items'] as $order => $item) {
                $existing = empty($item['id']) ? null : $old->get($item['id']);
                abort_if(! empty($item['id']) && ! $existing, 422, 'Hoạt động không thuộc lịch trình này.');
                $snapshot = $snapshots->get($existing?->place_snapshot_id);
                $details = json_decode($snapshot?->snapshot ?? '{}', true);
                $source=$hours->source($trip->location_id,$item);
                if($source && (!$snapshot || ($details['provider'] ?? '')==='google_sheet')
                    && \App\Services\Places\SheetPlaceMapper::normalize($source->name)===\App\Services\Places\SheetPlaceMapper::normalize($item['title'])
                    && in_array(\App\Services\Places\SheetPlaceMapper::normalize($item['place_name'] ?? ''),[
                        \App\Services\Places\SheetPlaceMapper::normalize($source->address ?? ''),\App\Services\Places\SheetPlaceMapper::normalize($source->name)],true)){
                    $sourceDetails=app(\App\Services\Places\SheetPlaceCatalog::class)->find($trip->location_id,$source->id);
                    if($sourceDetails){
                        $details=[...$details,...$sourceDetails];
                        if(!$snapshot)$details['manual_place_name']=$item['place_name'] ?? null;
                        $details['planning_area']=app(GeoapifyClient::class)->planningArea(['provider'=>'google_sheet','name'=>$trip->location->name]);
                    }
                }
                $details['source_place_id']=$source?->id;$details['end_day_offset']=(int)($item['end_day_offset'] ?? 0);
                if (empty($details['planning_area']) && $planningArea) $details['planning_area']=$planningArea;
                $oldPlace = array_key_exists('manual_place_name', $details) ? $details['manual_place_name'] : ($details['name'] ?? $details['address'] ?? null);
                if (!empty($details['image']) && !array_key_exists('image_place_name', $details)) {
                    $details['image_place_name'] = $oldPlace ?? ''; $details['image_title'] = $existing->title;
                }
                if ($photoPlace = $this->proposalPlace($trip, $item, $photoMessages)) {
                    $details['photo_place']=$photoPlace;
                    $details['image'] = app(PlacePhoto::class)->known($photoPlace); $details['image_place_name'] = $item['place_name'] ?? ''; $details['image_title'] = $item['title'];
                }
                $placeChanged = ! empty($details['place_changed']) || trim($oldPlace ?? '') !== trim($item['place_name'] ?? '');
                $modified = ! $existing || $scheduleChanged || $existing->title !== $item['title']
                    || ($existing->description ?? '') !== ($item['description'] ?? '')
                    || ($existing->estimated_cost === null) !== (($item['estimated_cost'] ?? null) === null)
                    || (int) $existing->estimated_cost !== (int) ($item['estimated_cost'] ?? null);
                $details = [...$details, 'manual_place_name' => $item['place_name'] ?? null, 'manually_edited' => $modified || ! empty($details['manually_edited']),
                    'place_changed' => $placeChanged, 'travel_from_previous' => $scheduleChanged ? null : ($details['travel_from_previous'] ?? null)];
                if ($snapshot) {
                    DB::table('itinerary_place_snapshot')->where('id', $snapshot->id)->update(['snapshot' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                    $snapshotId = $snapshot->id;
                } else {
                    $snapshotId = DB::table('itinerary_place_snapshot')->insertGetId(['itinerary_id' => $trip->id, 'provider' => 'manual',
                        'provider_place_id' => 'manual:'.Str::uuid(), 'snapshot' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
                $values = ['calendar_day_id' => $calendar->id, 'place_snapshot_id' => $snapshotId, 'day_number' => $dayNumber + 1,
                    'order_in_day' => $order + 1, 'item_type' => $item['item_type'], 'title' => $item['title'],
                    'description' => $item['description'] ?? null, 'start_time' => $item['start_time'] ?? null,
                    'end_time' => $item['end_time'] ?? null, 'estimated_cost' => $item['estimated_cost'] ?? null];
                if ($existing) {
                    $itemId = $existing->id;
                    DB::table('itinerary_item')->where('id', $itemId)->update($values);
                } else $itemId = DB::table('itinerary_item')->insertGetId(['itinerary_id' => $trip->id, ...$values]);
                $keep[] = $itemId;
                if (! $existing || $placeChanged || $existing->estimated_cost !== null || $values['estimated_cost'] !== null) {
                    DB::table('itinerary_cost_line')->where('itinerary_id', $trip->id)->where('item_id', $itemId)->delete();
                    DB::table('itinerary_cost_line')->insert(['itinerary_id' => $trip->id, 'calendar_day_id' => $calendar->id, 'item_id' => $itemId,
                        'category' => 'activity', 'price_status' => $values['estimated_cost'] === null ? 'unknown' : 'estimated',
                        'unit' => 'group', 'currency' => 'VND', 'unit_amount' => $values['estimated_cost'], 'quantity' => 1, 'source' => 'Khách tự nhập']);
                } else DB::table('itinerary_cost_line')->where('itinerary_id', $trip->id)->where('item_id', $itemId)->update(['calendar_day_id' => $calendar->id]);
            }
        }
        $removed = $old->keys()->diff($keep)->all();
        DB::table('itinerary_cost_line')->where('itinerary_id', $trip->id)->whereIn('item_id', $removed)->delete();
        DB::table('itinerary_item')->where('itinerary_id', $trip->id)->whereIn('id', $removed)->delete();
        $usedSnapshots = DB::table('itinerary_item')->where('itinerary_id', $trip->id)->whereNotNull('place_snapshot_id')->pluck('place_snapshot_id');
        DB::table('itinerary_place_snapshot')->where('itinerary_id', $trip->id)->whereNotIn('id', $usedSnapshots)->delete();
    }

    private function proposalPlace(Itinerary $trip, array $item, array &$messages): ?array
    {
        $reference = $item['photo_reference'] ?? null;
        if (!$reference) return null;
        $id = $reference['message_id'];
        if (!array_key_exists($id, $messages)) {
            $json = DB::table('chat_message as messages')->join('chat_session as sessions', 'sessions.id', '=', 'messages.session_id')
                ->where('sessions.customer_id', $trip->customer_id)->where('messages.id', $id)->where('messages.role', 'bot')->value('messages.entities_json');
            $messages[$id] = $json ? (json_decode($json, true)['assistant'] ?? null) : null;
        }
        $assistant = $messages[$id];
        abort_unless($assistant && ($assistant['state']['location_id'] ?? null) === $trip->location_id, 422, 'Ảnh không thuộc đề xuất của chuyến đi này.');
        $timeline = collect($assistant['components'] ?? [])->firstWhere('type', 'timeline');
        $day = collect($timeline['days'] ?? [])->firstWhere('day_number', $reference['day_number']);
        $source = $day['items'][$reference['item_index']] ?? null;
        abort_unless($source && ($source['title'] ?? '') === $item['title'] && trim($source['address'] ?? '') === trim($item['place_name'] ?? ''),
            422, 'Địa điểm đã thay đổi. Vui lòng bỏ liên kết ảnh cũ trước khi lưu.');
        abort_if(app(PlaceAvailability::class)->blocked($source,$trip->location->name),422,'Địa điểm trong đề xuất cũ đã được báo đóng cửa. Hãy yêu cầu AI đề xuất lại.');
        return app(PlannerPhotos::class)->place($source);
    }
}
