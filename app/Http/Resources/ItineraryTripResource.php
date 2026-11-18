<?php

namespace App\Http\Resources;

use App\Services\Itinerary\BudgetCalculator;
use App\Services\Itinerary\ActivityCopy;
use App\Services\Itinerary\DestinationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

final class ItineraryTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $days = DB::table('itinerary_calendar_day')->where('itinerary_id', $this->id)->orderBy('date')->get();
        $budget = app(BudgetCalculator::class)->forTrip($this->resource);
        $regionVerified = app(DestinationService::class)->verifiedRegion($this->location_id);
        $items = DB::table('itinerary_item')->where('itinerary_id', $this->id)->orderBy('day_number')->orderBy('order_in_day')->get();
        $snapshots = DB::table('itinerary_place_snapshot')->where('itinerary_id', $this->id)->get()->keyBy('id');
        $generated = $snapshots->contains(fn ($snapshot) => $snapshot->provider !== 'manual');
        $manual = $snapshots->contains(fn ($snapshot) => ! empty(json_decode($snapshot->snapshot, true)['manually_edited']));
        $sheetPrimary=app(\App\Services\Places\SheetPlaceCatalog::class)->isPrimary($this->location_id);
        $unverifiedRoute=$snapshots->contains(fn($snapshot)=>($snapshot->provider==='google_sheet') || (json_decode($snapshot->snapshot,true)['travel_from_previous']['status'] ?? '')==='unverified');
        $planningArea=$snapshots->map(fn($snapshot)=>json_decode($snapshot->snapshot,true)['planning_area'] ?? null)->filter()->first();
        $availability=app(\App\Services\Itinerary\PlaceAvailability::class);$locationName=$this->location?->name ?? '';
        $hours=app(\App\Services\Places\ItineraryHours::class);$visitDatesConfirmed=!(bool)$this->resource->getAttribute('is_provisional');

        return [
            'id' => $this->id, 'schema_version' => $this->schema_version, 'revision' => $this->revision,
            'title' => $this->title, 'status' => $this->status,
            'provisional' => (bool) $this->resource->getAttribute('is_provisional'),
            'planning_area'=>$planningArea,
            'planning_warnings'=>$this->resource->planning_metadata['warnings'] ?? [],
            'planning_slots'=>$this->resource->planning_metadata['days'] ?? [],
            'planning_policy'=>$this->resource->planning_metadata['policy'] ?? null,
            'location' => ['id' => $this->location_id, 'name' => $this->location?->name],
            'start_date' => $this->start_date->toDateString(), 'end_date' => $this->end_date->toDateString(),
            'total_days' => $this->total_days, 'total_nights' => max(0, $this->total_days - 1),
            'party' => ['adults' => $this->adults, 'children' => $this->children, 'infants' => $this->infants,
                'total' => $this->adults + count($this->children) + count($this->infants), 'age_policy_verified' => false],
            'stay_mode' => $this->stay_mode,
            'arrival_time' => $this->time($this->arrival_time), 'departure_time' => $this->time($this->departure_time),
            'activity_window' => ['start' => $this->time($this->stay_mode === 'day_trip' ? $this->arrival_time : $this->activity_window_start),
                'end' => $this->time($this->stay_mode === 'day_trip' ? $this->departure_time : $this->activity_window_end),
                'confirmed' => $this->activity_window_confirmed],
            'lodging' => ['hotel_id' => $this->hotel_id, 'check_in' => $this->lodging_check_in?->toDateString(),
                'check_out' => $this->lodging_check_out?->toDateString(), 'uncovered_nights' => $budget['uncovered_nights'], 'reservation_created' => false],
            'transport' => $this->transport, 'pace' => $this->pace, 'interests' => $this->interests,
            'food_restrictions' => $this->food_restrictions, 'requirements' => $this->requirements,
            'selected_dates' => $days->where('selected_for_planning', true)->pluck('date')->values()->all(),
            'days' => $days->map(fn ($day) => ['date' => $day->date, 'selected_for_planning' => (bool) $day->selected_for_planning,
                'planning_status' => $day->planning_status, 'free_day_allowance' => $day->free_day_allowance === null ? null : (int) $day->free_day_allowance,
                'items'=>$items->where('calendar_day_id', $day->id)->map(function ($item) use ($snapshots,$availability,$locationName,$hours,$day,$visitDatesConfirmed) {
                    $snapshot = json_decode($snapshots->get($item->place_snapshot_id)?->snapshot ?? 'null', true) ?? [];
                    $imageMatches = isset($snapshot['image_place_name'])
                        ? trim($snapshot['image_place_name']) === trim($snapshot['manual_place_name'] ?? '') && ($snapshot['image_title'] ?? '') === $item->title
                        : empty($snapshot['place_changed']) && ($snapshot['name'] ?? '') === $item->title;
                    $photoPlace=app(\App\Services\Itinerary\PlannerPhotos::class)->place($snapshot['photo_place'] ?? $snapshot);
                    $photoLookup=$imageMatches && app(\App\Services\Itinerary\PlannerPhotos::class)->eligible($photoPlace);
                    $excluded=$availability->blocked(['name'=>$item->title,'coordinates'=>empty($snapshot['place_changed']) ? ($snapshot['coordinates'] ?? null) : null,
                        'address'=>$snapshot['manual_place_name'] ?? $snapshot['address'] ?? null,'operating_status'=>empty($snapshot['place_changed']) ? ($snapshot['operating_status'] ?? null) : null],$locationName);
                    $openingCheck=$hours->checkItem($this->location_id,['title'=>$item->title,'item_type'=>$item->item_type,'place_name'=>$snapshot['manual_place_name'] ?? $snapshot['address'] ?? null,
                        'source_place_id'=>$snapshot['source_place_id'] ?? null,'start_time'=>$this->time($item->start_time),'end_time'=>$this->time($item->end_time),'end_day_offset'=>$snapshot['end_day_offset'] ?? 0,
                        'hours_metadata'=>empty($snapshot['place_changed'])?$snapshot:[]],$visitDatesConfirmed?$day->date:null);
                    if($excluded && !empty($openingCheck['display']['has_data'])){$openingCheck['display']['status']='unknown';$openingCheck['display']['summary']='Giờ hoạt động: Cần xác nhận lại với địa điểm';}
                    return ['id'=>$item->id,'item_type'=>$item->item_type,'title'=>$item->title,'description'=>ActivityCopy::description($item->description),'excluded_from_ai'=>$excluded,
                        'source_place_id'=>$snapshot['source_place_id'] ?? $openingCheck['source_place_id'] ?? null,'end_day_offset'=>$snapshot['end_day_offset'] ?? 0,'opening_check'=>$openingCheck,
                        'place_name'=>array_key_exists('manual_place_name', $snapshot) ? $snapshot['manual_place_name'] : ($snapshot['name'] ?? $snapshot['address'] ?? null),
                        'manually_edited'=>!empty($snapshot['manually_edited']), 'image'=>$imageMatches ? ($snapshot['image'] ?? app(\App\Services\Itinerary\PlacePhoto::class)->known($photoPlace)) : null,
                        'photo_lookup'=>$photoLookup && !$excluded,
                        'start_time'=>$this->time($item->start_time),'end_time'=>$this->time($item->end_time),'order_in_day'=>$item->order_in_day,
                        'estimated_cost'=>$item->estimated_cost===null?null:(int)$item->estimated_cost,'price_status'=>empty($snapshot['place_changed']) ? ($snapshot['ticket_estimate']['status'] ?? 'unknown') : 'unknown',
                        'ticket'=>empty($snapshot['place_changed']) ? ($snapshot['ticket'] ?? null) : null,'ticket_estimate'=>empty($snapshot['place_changed']) ? ($snapshot['ticket_estimate'] ?? null) : null,'source'=>empty($snapshot['place_changed']) ? ($snapshot['source'] ?? null) : null,'source_url'=>empty($snapshot['place_changed']) ? ($snapshot['source_url'] ?? null) : null,
                        'coordinates'=>empty($snapshot['place_changed']) ? ($snapshot['coordinates'] ?? null) : null,'address'=>empty($snapshot['place_changed']) ? ($snapshot['address'] ?? null) : null,'travel_from_previous'=>$snapshot['travel_from_previous'] ?? null,
                        'provider'=>empty($snapshot['place_changed']) ? ($snapshot['provider'] ?? null) : null,
                        ...(empty($snapshot['place_changed'])?array_intersect_key($snapshot,array_flip(['maps_url','maps_link_type'])):[]),
                        'price_reference'=>empty($snapshot['place_changed']) ? ($snapshot['price_reference'] ?? null) : null,
                        'reason'=>empty($snapshot['place_changed'])?($snapshot['selection_reason'] ?? null):null,'planning_slot'=>$snapshot['planning_slot'] ?? null,
                        'hours_status'=>$snapshot['hours_status'] ?? null,
                        'suggested_duration_minutes'=>$snapshot['suggested_duration_minutes'] ?? null,'opening_hours_verified'=>false];
                })->values()->all()])->all(),
            'budget' => $budget,
            'readiness' => ['validation_status' => $manual ? 'manually_edited' : ($generated ? ($unverifiedRoute?'route_unverified':'route_checked') : 'not_checked'), 'activities_generated' => $generated && ! $manual,
                'provider_region_verified' => $regionVerified, 'provider_lookup_permitted' => $regionVerified, 'provider_candidates_available' => $generated,
                'opening_hours_verified'=>false,'prices_verified'=>false,
                'generation_available' => ($sheetPrimary || (bool)config('services.geoapify.api_key')) && (bool)config('services.groq.api_key'), 'phase' => $generated ? 2 : 1],
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }

    private function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
