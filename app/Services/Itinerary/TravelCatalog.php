<?php

namespace App\Services\Itinerary;

use App\Models\Hotel;
use App\Models\Location;
use App\Services\ExperienceCatalogService;
use App\Support\ExperienceSchema;
use Illuminate\Support\Str;

/** Public, approved catalog only. No seed prices, ratings or unverified hotel quotes. */
final class TravelCatalog
{
    public function __construct(private GeoapifyClient $geo, private DestinationService $destinations) {}

    public function locations(): array
    {
        return $this->destinations->listing(null);
    }

    public function approved(int $locationId): array
    {
        if (!app(ExperienceSchema::class)->ready()) return [];
        $places=app(ExperienceCatalogService::class)->publicQuery()->where('location_id', $locationId)
            ->with('images')->orderBy('id')->limit(120)->get()->map(fn($place) => [
                'id' => 'aurel:'.$place->id, 'provider' => 'aurel', 'provider_place_id' => 'attraction:'.$place->id,
                'attraction_id' => $place->id, 'name' => $place->name, 'item_type' => $place->item_type,
                'description' => Str::limit(strip_tags($place->experience_summary ?: $place->description ?: ''), 500),
                'address' => $place->address, 'coordinates' => $place->lat !== null && $place->lng !== null ? [(float)$place->lng, (float)$place->lat] : null,
                'source' => 'Trải nghiệm đã duyệt', 'source_url' => route('experiences.show', $place->slug),
                'source_reference' => $place->experience_source_url, 'categories' => [$place->experience_category],
                'opening_hours_verified' => false, 'price_status' => 'unknown', 'region_verified' => false,
                'suggested_duration_minutes' => $place->experience_duration_minutes,
                'image' => collect($place->images)->sortByDesc('is_primary')->map(fn($image) => $image->source_note && ($url=\App\Support\AccommodationImage::localUrl($image->image_url))
                    ? ['url'=>$url,'alt'=>$place->name,'attribution_note'=>$image->source_note,'source'=>'Nguồn ảnh','source_url'=>route('experiences.show',$place->slug)] : null)->filter()->first(),
            ])->all();
        return app(PlaceAvailability::class)->filter($places,Location::findOrFail($locationId)->name);
    }

    public function pool(int $locationId, bool $extended = false, string $focus = 'general', ?TravelProgress $progress = null): array
    {
        abort_unless($this->destinations->supported($locationId), 422, 'Điểm đến chưa được AUREL hỗ trợ.');
        $progress?->emit('searching_places');
        $sheet=app(\App\Services\Places\SheetPlaceCatalog::class);
        if($sheet->isPrimary($locationId)){
            $location=Location::findOrFail($locationId);
            $source=app(\App\Services\Places\SheetPlaceSources::class)->forLocation($locationId);
            $places=app(PlaceAvailability::class)->filter($sheet->forPlanning($locationId,$focus),$location->name);
            if(!$places && $focus==='general')$this->geo->unavailable('Chưa có địa điểm Google Sheet hợp lệ đã đồng bộ cho '.$location->name.'. Hãy kiểm tra nguồn Sheet và đồng bộ lại.','sheet_places_unavailable',422);
            return ['region'=>['provider'=>'google_sheet','name'=>$location->name,
                'place_id'=>\App\Services\Places\SheetPlaceSync::source($locationId).':'.$locationId,
                'boundary'=>['kind'=>'source_catalog','location_id'=>$locationId],
                'evidence'=>['method'=>'sheet_catalog','source_reference'=>'https://docs.google.com/spreadsheets/d/'.rawurlencode($source['spreadsheet_id'] ?? ''),'location_id'=>$locationId]],
                'places'=>$places];
        }
        $region = $this->geo->region(Location::findOrFail($locationId));
        $regionVerified=!$this->geo->isSearchArea($region);
        $geometry = $this->geo->boundary($region);$region['boundary']['geometry'] = $geometry;
        $geo = array_values(array_filter($this->geo->places($region, $extended, $focus), fn($place) => $this->geo->contains($geometry, $place['coordinates'])));
        $byName = [];foreach ($geo as $index => $place) $byName[$this->name($place['name'])][] = $index;
        $used = [];$places = [];
        foreach ($this->approved($locationId) as $place) {
            if (!$this->matchesFocus($place,$focus)) continue;
            $matches = $byName[$this->name($place['name'])] ?? [];
            if ($place['coordinates'] === null && count($matches) === 1) $place['coordinates'] = $geo[$matches[0]]['coordinates'];
            if ($place['coordinates'] === null || !$this->geo->contains($geometry, $place['coordinates'])) continue;
            if (count($matches) === 1) $used[$matches[0]] = true;
            if (count($matches) === 1) $place['wikidata_id'] = $geo[$matches[0]]['wikidata_id'] ?? null;
            $place['region_verified'] = $regionVerified;$places[] = $place;
        }
        foreach ($geo as $index => $place) {
            if (isset($used[$index])) continue;
            $places[] = [...$place, 'id' => 'geo:'.substr(hash('sha256',$place['provider_place_id']),0,20), 'provider' => 'geoapify', 'region_verified' => $regionVerified];
        }
        if (!$places && $focus === 'general') $this->geo->unavailable('Chưa đủ địa điểm được xác minh trong ranh giới này để lập lịch.', 'insufficient_places', 422);
        $locationName=Location::findOrFail($locationId)->name;
        $hours=app(\App\Services\Places\ItineraryHours::class);$withHours=[];
        foreach($places as $place){
            $source=$hours->source($locationId,['title'=>$place['name'],'address'=>$place['address'] ?? null]);
            if($source && !$source->is_enabled)continue;
            if($source && !empty($source->opening_schedule)){$place['opening_schedule']=json_decode($source->opening_schedule,true);$place['source_place_id']=$source->id;}
            $withHours[]=$place;
        }
        $places=$withHours;
        $places=array_map(fn($place)=>app(VisitInformation::class)->enrich($place,$locationName),$places);
        $places=app(PlaceAvailability::class)->filter($places,$locationName);
        return ['region' => $region, 'places' => $places];
    }

    public function facts(array $state, string $intent, ?TravelProgress $progress = null, string $message = ''): array
    {
        $locations = $this->locations();$focus=$state['place_focus'] ?? 'general';
        $facts = ['locations' => $locations, 'places' => [], 'hotels' => [], 'warnings' => [],
            'lookup'=>['focus'=>$focus,'online_attempted'=>false,'status'=>'not_needed']];
        if($intent==='preferences')return $facts;
        $needsPlaces=in_array($intent,['explore','travel_question'],true);
        if (empty($state['location_id'])) {
            if($needsPlaces){
                $progress?->emit('searching_places');
                foreach ($locations as &$location) $location['experiences'] = array_map(fn($p) => ['name' => $p['name'], 'description' => $p['description']], array_slice($this->approved($location['id']), 0, 3));
            }
            $facts['locations'] = $locations;return $facts;
        }
        $id = $state['location_id'];
        $sheetPlaces=[];
        if($needsPlaces){
            $progress?->emit('searching_places');
            $sheetPlaces=app(\App\Services\Places\SheetPlaceCatalog::class)->candidates($id,$focus,$state,$message);
            $facts['places'] = $sheetPlaces ?: (app(\App\Services\Places\SheetPlaceCatalog::class)->isPrimary($id)?[]:array_slice(array_values(array_filter($this->approved($id),fn($place)=>$this->matchesFocus($place,$focus))),0,12));
            if($sheetPlaces){$facts['lookup']['status']='local_source';$facts['warnings'][]=\App\Services\Places\SheetPlaceCatalog::NOTICE;
                $facts['warnings'][]='Link Maps có thể là kết quả tìm theo tên và địa chỉ; bạn cần chọn đúng chi nhánh. Dữ liệu thiếu tọa độ hoặc ảnh được giữ trống.';}
        }
        if ($needsPlaces && !$sheetPlaces && count($facts['places']) < ($focus === 'entertainment' ? 4 : 3)) {
            $facts['lookup']['online_attempted']=!app(\App\Services\Places\SheetPlaceCatalog::class)->isPrimary($id);
            try {
                $pool = $this->pool($id,false,$focus,$progress);$facts['lookup']['status']='complete';
                if ($area=$this->geo->planningArea($pool['region'])) $facts['warnings'][]=$area['notice'];
                $seen=[];$combined=[];
                foreach([...$facts['places'],...$pool['places']] as $place) {
                    $key=$this->name($place['name']);if(isset($seen[$key]))continue;
                    $seen[$key]=true;$combined[]=$place;
                }
                $facts['places']=array_slice($combined,0,12);
                if($focus === 'entertainment' && $facts['places'])$facts['warnings'][]='Vị trí và danh mục theo nguồn bản đồ. Tình trạng hoạt động, giá vé, giờ mở cửa và điều kiện tham gia cần kiểm tra trực tiếp với địa điểm.';
            } catch(\Illuminate\Http\Exceptions\HttpResponseException $error) {
                if(!in_array($focus,['entertainment','food'],true)) throw $error;
                $body=json_decode($error->getResponse()->getContent(),true);
                if(in_array($body['code'] ?? '',['request_cancelled','request_timeout'],true))throw $error;
                $facts['lookup']['status']='unavailable';$facts['lookup']['code']=$body['code'] ?? 'online_lookup_unavailable';
                $facts['warnings'][]='Chưa truy vấn được nguồn trực tuyến. Điều này không có nghĩa địa phương không có '.($focus==='food'?'chỗ ăn uống':'khu vui chơi').'.';
            }
        }
        if ($intent === 'hotels') {
            $progress?->emit('searching_hotels');
            $facts['hotels'] = Hotel::when(\Illuminate\Support\Facades\Schema::hasTable('hotel_image'),fn($query)=>$query->with('images'))
                ->where('location_id', $id)->where('status', 'active')->orderBy('name')->limit(6)
                ->get(['id','name','address','location_id'])->map(fn($hotel) => ['id' => $hotel->id, 'name' => $hotel->name,
                    'address' => $hotel->address, 'location_id' => $hotel->location_id, 'url' => route('hotel.detail', $hotel->id),
                    'price' => null, 'availability_checked' => false, 'source' => 'Khách sạn',
                    'image'=>$hotel->relationLoaded('images') ? collect($hotel->images)->sortByDesc('is_primary')->map(fn($image)=>($url=\App\Support\AccommodationImage::localUrl($image->image_url))
                        ? ['url'=>$url,'alt'=>$hotel->name,'source'=>'Nguồn ảnh','source_url'=>route('hotel.detail',$hotel->id)] : null)->filter()->first() : null])->all();
            $facts['warnings'][] = 'Chưa kiểm tra giá, phòng trống hoặc chính sách trẻ em; xem trang khách sạn để kiểm tra theo ngày lưu trú.';
        }
        if(!empty($state['destination_name']))$facts['places']=array_map(fn($place)=>($place['provider'] ?? '')==='google_sheet'?$place:app(VisitInformation::class)->enrich($place,$state['destination_name']),$facts['places']);
        $facts['places']=app(PlaceAvailability::class)->filter($facts['places'],Location::findOrFail($id)->name);
        return $facts;
    }

    private function name(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }

    private function matchesFocus(array $place,string $focus):bool
    {
        if($focus === 'general')return true;
        if($focus === 'food')return ($place['item_type'] ?? '')==='restaurant' || in_array('am-thuc',$place['categories'] ?? [],true);
        if($focus !== 'entertainment')return false;
        if(in_array('vui-choi',$place['categories'] ?? [],true) || GeoapifyClient::isEntertainment($place['categories'] ?? []))return true;
        return preg_match('/\b(?:khu vui choi|cong vien (?:giai tri|nuoc)|theme park|water park)\b/',Str::lower(Str::ascii($place['name'].' '.($place['description'] ?? ''))))===1;
    }
}
