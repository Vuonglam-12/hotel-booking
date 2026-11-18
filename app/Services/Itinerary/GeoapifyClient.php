<?php

namespace App\Services\Itinerary;

use App\Models\Location;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GeoapifyClient
{
    public const ENTERTAINMENT_CATEGORIES = ['entertainment.theme_park','entertainment.water_park','entertainment.activity_park',
        'entertainment.amusement_arcade','entertainment.aquarium','entertainment.zoo'];

    public function region(Location $location): array
    {
        return Cache::remember($this->regionCacheKey($location), 86400, function () use ($location) {
            $data = $this->request('/v1/geocode/search', ['text'=>$location->name.', Việt Nam', 'type'=>'city', 'filter'=>'countrycode:vn', 'lang'=>'vi', 'limit'=>5]);
            $matches = [];
            foreach ($data['features'] ?? [] as $feature) {
                $p = $feature['properties'] ?? [];
                if (($p['country_code'] ?? '') !== 'vn' || ($p['result_type'] ?? '') !== 'city' || !in_array($p['category'] ?? '',['administrative','populated_place'],true)) continue;
                $names = [$p['name'] ?? '', $p['city'] ?? '', $p['other_names']['name:vi'] ?? ''];
                if (!collect($names)->contains(fn ($name) => $this->name($name) === $this->name($location->name))) continue;
                if (!is_string($p['place_id'] ?? null) || strlen($p['place_id']) > 255 || !isset($p['lon'], $p['lat']) || !$this->coordinates([$p['lon'], $p['lat']])) continue;
                $searchArea=$p['category']==='populated_place';$bbox=$feature['bbox'] ?? null;
                if ($searchArea && !$this->validSearchBox($bbox,[$p['lon'],$p['lat']])) continue;
                $matches[$p['place_id']] = ['place_id'=>$p['place_id'], 'name'=>$p['name'] ?? $p['city'], 'lon'=>(float)$p['lon'], 'lat'=>(float)$p['lat'],
                    'boundary'=>['bbox'=>$bbox,...($searchArea?['kind'=>'search_area']:[])], 'evidence'=>['method'=>$searchArea?'provider_search_area':'administrative_region', 'source_reference'=>'https://api.geoapify.com/v1/geocode/search',
                        'country_code'=>'vn', 'result_type'=>'city', 'category'=>$p['category'], 'matched_name'=>$location->name, 'provider_name'=>$p['name'] ?? $p['city']]];
            }
            if (count($matches) !== 1) $this->unavailable('Chưa xác minh được đúng ranh giới hành chính của địa phương. Vui lòng chọn điểm đến khác.', 'region_unverified', 422);
            return array_values($matches)[0];
        });
    }

    public function places(array $region, bool $extended = false, string $focus = 'general'): array
    {
        abort_unless(in_array($focus,['general','entertainment','food'],true),422,'Nhóm địa điểm chưa được hỗ trợ.');
        $cacheSuffix = $focus === 'general' ? '' : '|'.$focus;
        $availability=app(PlaceAvailability::class);
        if ($this->isSearchArea($region)) $this->boundary($region); // Validate the provider box before sending a rectangle query.
        $filter=$this->isSearchArea($region) ? 'rect:'.implode(',',$region['boundary']['bbox']) : 'place:'.$region['place_id'];
        $places=Cache::remember('itinerary.geo.places.v5.'.hash('sha256', $filter.'|'.(int)$extended.$cacheSuffix), 1800, function () use ($region, $extended, $focus, $availability, $filter) {
            $features=[];
            // Separate small queries keep nearby restaurants from crowding landmarks out of the candidate pool.
            $queries = $focus === 'entertainment' ? [implode(',', self::ENTERTAINMENT_CATEGORIES) => 40]
                : ($focus === 'food' ? ['catering.restaurant,catering.cafe'=>20]
                : ['tourism.sights,leisure.park'=>($extended ? 80 : 20),'catering.restaurant,catering.cafe'=>($extended ? 40 : 10)]);
            foreach ($queries as $categories=>$limit) {
                $data=$this->request('/v2/places',['categories'=>$categories,'filter'=>$filter,
                    'bias'=>'proximity:'.$region['lon'].','.$region['lat'],'limit'=>$limit,'lang'=>'vi']);
                $features=[...$features,...($data['features']??[])];
            }
            $places = []; $names=[];
            foreach ($features as $feature) {
                $p = $feature['properties'] ?? []; $coordinates = $feature['geometry']['coordinates'] ?? [];
                if (strtolower($p['country_code'] ?? '') !== 'vn' || !is_string($p['place_id'] ?? null) || strlen($p['place_id']) > 255
                    || !is_string($p['name'] ?? null) || trim($p['name']) === '' || !$this->coordinates($coordinates)) continue;
                $nameKey=$this->name($p['name']);
                // Keep closure evidence even if an earlier category returned the same name without a status.
                $isClosed=$availability->permanentlyClosed($p);
                if(!$isClosed && isset($names[$nameKey]))continue;
                $names[$nameKey]=true;
                $categories = array_values(array_filter($p['categories'] ?? [], 'is_string'));
                if ($focus === 'entertainment' && !self::isEntertainment($categories)) continue;
                if ($focus === 'food' && !collect($categories)->contains(fn($c)=>str_starts_with($c,'catering.')))continue;
                $type = self::isEntertainment($categories) ? 'activity' : (collect($categories)->contains(fn ($c) => str_starts_with($c, 'catering.')) ? 'restaurant' : 'attraction');
                $website = $p['website'] ?? $p['datasource']['raw']['website'] ?? null;
                if (!is_string($website) || !in_array(parse_url($website,PHP_URL_SCHEME),['http','https'],true) || !parse_url($website,PHP_URL_HOST)
                    || parse_url($website,PHP_URL_USER) || parse_url($website,PHP_URL_PASS)) $website = null;
                $address=(string)($p['formatted'] ?? '');
                // Provider ward/city labels can lag administrative changes. Do not repeat a different city label as verified.
                if($focus === 'entertainment') {
                    $street=trim(implode(' ',array_filter([$p['housenumber'] ?? null,$p['street'] ?? null],'is_string')));
                    $address='Vị trí theo bản đồ: '.($street!==''?$street.', ':'').$region['name'];
                }
                $places[($isClosed?'closed:':'').$p['place_id']] = ['provider_place_id'=>$p['place_id'], 'name'=>mb_substr($p['name'], 0, 200), 'coordinates'=>[(float)$coordinates[0], (float)$coordinates[1]],
                    'address'=>mb_substr($address, 0, 500), 'categories'=>$categories, 'item_type'=>$type,
                    'opening_hours'=>$p['opening_hours'] ?? null, 'opening_hours_verified'=>false, 'price_status'=>'unknown',
                    'source'=>'Geoapify / OpenStreetMap', 'source_url'=>'https://www.openstreetmap.org/?mlat='.$coordinates[1].'&mlon='.$coordinates[0],
                    'website'=>$website,
                    'operating_status'=>$isClosed ? 'closed_permanently' : 'unknown',
                    'wikidata_id'=>$p['datasource']['raw']['wikidata'] ?? null,
                    'attribution'=>'© OpenStreetMap contributors', 'retrieved_at'=>now()->toIso8601String()];
            }
            return array_values($places);
        });
        $availability->observe($places,$region['name']);
        $places=$availability->filter($places,$region['name']);
        if ($focus === 'general' && count($places) < 2) $this->unavailable('Chưa đủ địa điểm có nguồn và chưa bị báo đóng cửa trong khu vực này.', 'insufficient_places', 422);
        return $places;
    }

    public static function isEntertainment(array $categories): bool
    {
        foreach ($categories as $category) foreach(self::ENTERTAINMENT_CATEGORIES as $parent) {
            if ($category === $parent || str_starts_with($category,$parent.'.')) return true;
        }
        return false;
    }

    public function boundary(array $region): array
    {
        if ($this->isSearchArea($region)) {
            $box=$region['boundary']['bbox'] ?? null;
            if (!$this->validSearchBox($box,[$region['lon'],$region['lat']])) $this->unavailable('Phạm vi tìm địa điểm chưa hợp lệ. Bạn thử lại sau nhé.','region_unverified',422);
            [$west,$south,$east,$north]=$box;
            return ['type'=>'Polygon','coordinates'=>[[[$west,$south],[$east,$south],[$east,$north],[$west,$north],[$west,$south]]]];
        }
        return Cache::remember('itinerary.geo.full-boundary.'.hash('sha256', $region['place_id']), 86400, function () use ($region) {
            $data = $this->request('/v2/place-details', ['id' => $region['place_id'], 'features' => 'details.full_geometry']);
            foreach ($data['features'] ?? [] as $feature) {
                $geometry = $feature['geometry'] ?? [];
                $properties = $feature['properties'] ?? [];
                if (!in_array($geometry['type'] ?? '', ['Polygon', 'MultiPolygon'], true) || empty($geometry['coordinates'])) continue;
                // Details may re-encode the same OSM relation with a different center in place_id.
                // It must still match the exact administrative name, country and original city center.
                $normalizedIdentity = ($properties['country_code'] ?? '') === 'vn'
                    && $this->name($properties['name'] ?? '') === $this->name($region['name'])
                    && ($properties['datasource']['raw']['osm_type'] ?? '') === 'r'
                    && is_numeric($properties['datasource']['raw']['osm_id'] ?? null)
                    && $this->contains($geometry, [$region['lon'],$region['lat']]);
                if (($properties['place_id'] ?? null) === $region['place_id'] || $normalizedIdentity) return $geometry;
            }
            $this->unavailable('Chưa xác minh được ranh giới địa phương để lên tuyến an toàn. Bạn thử lại sau nhé.', 'region_geometry_unverified', 422);
        });
    }

    public function contains(array $geometry, array $point): bool
    {
        if (!$this->coordinates($point)) return false;
        $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : (($geometry['type'] ?? '') === 'MultiPolygon' ? $geometry['coordinates'] : []);
        foreach ($polygons as $rings) {
            if (!$rings || !$this->inRing($rings[0], $point)) continue;
            $inHole = false;foreach (array_slice($rings, 1) as $hole) if ($this->inRing($hole, $point)) $inHole = true;
            if (!$inHole) return true;
        }
        return false;
    }

    private function inRing(array $ring, array $point): bool
    {
        if (count($ring) < 4) return false;
        [$x, $y] = $point;$inside = false;$previous = count($ring) - 1;
        foreach ($ring as $index => $vertex) {
            if (!$this->coordinates($vertex) || !$this->coordinates($ring[$previous])) return false;
            [$xi, $yi] = $vertex;[$xj, $yj] = $ring[$previous];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) $inside = !$inside;
            $previous = $index;
        }
        return $inside;
    }

    public function matrix(array $places, string $mode, ?TravelProgress $progress = null): array
    {
        if (!in_array($mode, ['walk','drive','bicycle'], true) || count($places) > 15 || !$places) $this->unavailable('Phạm vi tính tuyến chưa phù hợp.', 'invalid_matrix', 422);
        $points = array_map(fn ($place) => ['location'=>$place['coordinates']], $places);
        $key = 'itinerary.geo.matrix.'.hash('sha256', json_encode([$mode,$points], JSON_THROW_ON_ERROR));
        return Cache::remember($key, 1800, function () use ($points, $mode, $progress) {
            $progress?->emit('calculating_route');
            $data = $this->request('/v1/routematrix', [], ['mode'=>$mode, 'sources'=>$points, 'targets'=>$points]);
            if (!is_array($data['sources_to_targets'] ?? null) || count($data['sources_to_targets']) !== count($points)) $this->unavailable('Dịch vụ bản đồ chưa trả đủ dữ liệu di chuyển.', 'invalid_matrix');
            return $data['sources_to_targets'];
        });
    }

    public function unavailable(string $message, string $code, int $status = 503, ?int $providerStatus = null): never
    {
        throw new HttpResponseException(response()->json(['message'=>$message, 'code'=>$code, ...($providerStatus === null ? [] : ['provider_status'=>$providerStatus])], $status));
    }

    public function route(array $points,string $mode):array
    {
        if(!in_array($mode,['walk','drive','bicycle'],true)||count($points)<2||count($points)>15
            ||array_filter($points,fn($point)=>!is_array($point)||!$this->coordinates($point)))$this->unavailable('Các điểm hoặc phương tiện chưa phù hợp để mở tuyến đường.','invalid_route',422);
        return Cache::remember('itinerary.route.shape.'.hash('sha256',json_encode([$points,$mode])),1800,function()use($points,$mode){
            $data=$this->request('/v1/routing',['waypoints'=>implode('|',array_map(fn($p)=>$p[1].','.$p[0],$points)),'mode'=>$mode]);
            $feature=$data['features'][0] ?? [];$geometry=$feature['geometry'] ?? [];
            if(!in_array($geometry['type'] ?? '',['LineString','MultiLineString'],true)||empty($geometry['coordinates']))$this->unavailable('Chưa tải được tuyến đường. Bạn có thể mở các vị trí trên bản đồ.','route_shape_unavailable',422);
            $lines=$geometry['type']==='LineString'?[$geometry['coordinates']]:$geometry['coordinates'];$count=0;
            foreach($lines as $line){if(!is_array($line)||count($line)<2)$this->unavailable('Tuyến đường chưa hợp lệ.','invalid_route_shape');foreach($line as $point)if(++$count>20000||!is_array($point)||!$this->coordinates($point))$this->unavailable('Tuyến đường chưa hợp lệ.','invalid_route_shape');}
            return ['geometry'=>$geometry,'mode'=>$mode,'distance_metres'=>$feature['properties']['distance'] ?? null,
                'time_seconds'=>$feature['properties']['time'] ?? null,'attribution'=>'© OpenStreetMap contributors'];
        });
    }

    private function request(string $path, array $query, ?array $body = null): array
    {
        $key = config('services.geoapify.api_key');
        if (!is_string($key) || trim($key) === '') $this->unavailable('Chưa cấu hình khóa dịch vụ bản đồ.', 'geoapify_key_missing');
        try {
            $http = Http::acceptJson()->connectTimeout(5)->timeout((int)config('services.geoapify.timeout',15))->withOptions(['allow_redirects'=>false]);
            $url = 'https://api.geoapify.com'.$path;
            $response = $body === null ? $http->get($url, [...$query,'apiKey'=>$key]) : $http->post($url.'?'.http_build_query(['apiKey'=>$key]), $body);
        } catch (ConnectionException $error) {
            // Deliberately discard exceptions containing API-key query strings.
            $this->unavailable('Chưa kết nối được dịch vụ bản đồ. Vui lòng thử lại.', 'geoapify_unreachable');
        }
        if (!$response->successful()) {
            $code = match ($response->status()) {401,403=>'geoapify_access_denied', 429=>'geoapify_quota', default=>'geoapify_unavailable'};
            $this->unavailable($code === 'geoapify_quota' ? 'Dịch vụ bản đồ đã đạt hạn mức. Vui lòng thử lại sau.' : 'Dịch vụ bản đồ chưa sẵn sàng. Vui lòng thử lại sau.', $code);
        }
        $data = $response->json();
        if (!is_array($data)) $this->unavailable('Dịch vụ bản đồ trả dữ liệu không hợp lệ.', 'geoapify_invalid_response');
        return $data;
    }

    private function coordinates(array $value): bool
    {
        return count($value) >= 2 && is_numeric($value[0]) && is_numeric($value[1]) && is_finite((float)$value[0]) && is_finite((float)$value[1])
            && abs((float)$value[0]) <= 180 && abs((float)$value[1]) <= 90;
    }

    public function isSearchArea(array $region): bool
    {
        return ($region['evidence']['method'] ?? '')==='provider_search_area' && ($region['boundary']['kind'] ?? '')==='search_area';
    }

    public function planningArea(array $region): ?array
    {
        if(($region['provider'] ?? '')==='google_sheet')return ['kind'=>'source_catalog','name'=>$region['name'],
            'notice'=>'Lịch dùng danh sách địa điểm từ nguồn Google Sheet; bạn cần kiểm tra đúng chi nhánh qua liên kết Google Maps. '.PlanningPolicy::UNVERIFIED_TRANSFER_NOTICE];
        return $this->isSearchArea($region) ? ['kind'=>'search_area','name'=>$region['name'],
            'notice'=>'AI gợi ý các địa điểm trong phạm vi tìm kiếm gần đúng quanh '.$region['name'].'. Bạn có thể kiểm tra vị trí và điều chỉnh các điểm theo nhu cầu.'] : null;
    }

    public function cachedPlanningArea(Location $location): ?array
    {
        $region=Cache::get($this->regionCacheKey($location));
        return is_array($region)?$this->planningArea($region):null;
    }

    private function regionCacheKey(Location $location): string { return 'itinerary.geo.region.v2.'.hash('sha256',$location->name.'|'.$location->country); }

    private function validSearchBox(mixed $box, array $center): bool
    {
        if (!is_array($box) || count($box)!==4 || !$this->coordinates(array_slice($box,0,2)) || !$this->coordinates(array_slice($box,2,2))) return false;
        [$west,$south,$east,$north]=$box;
        // Use the provider's bounded search area, never invent a radius or label it an administrative boundary.
        return $west<$east && $south<$north && $west<=$center[0] && $center[0]<=$east && $south<=$center[1] && $center[1]<=$north
            && ($north-$south)*111.32<=80 && ($east-$west)*111.32*cos(deg2rad($center[1]))<=80;
    }

    private function name(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/^(thanh pho|tinh)\s+/', '', Str::lower(Str::ascii(trim($value))))));
    }
}
