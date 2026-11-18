<?php

namespace App\Services\Itinerary;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Fail closed: exact label/alias + nearby coordinates + item P18 + explicit Commons license. */
final class PlacePhoto
{
    public function forPlace(array $place, ?TravelProgress $progress = null): ?array
    {
        if (($place['provider'] ?? '')==='google_sheet') return null; // Do not infer images for source rows with no licensed photo metadata.
        if ($known=$this->known($place)) return $known;
        if (!config('services.geoapify.external_photos',true) || empty($place['coordinates'])) return null;
        $key=$this->key($place);
        $cached=Cache::get($key);if(is_array($cached))return $cached['image'];
        $progress?->emit('searching_images');
        try {
            $id=$place['wikidata_id'] ?? null;
            if (!is_string($id) || !preg_match('/^Q[1-9][0-9]*$/D',$id)) {
                $search=$this->get('https://www.wikidata.org/w/api.php',['action'=>'wbsearchentities','format'=>'json','search'=>$place['name'],'language'=>'vi','uselang'=>'vi','limit'=>3]);
                $ids=array_column($search['search'] ?? [],'id');
            } else $ids=[$id];
            $ids=array_values(array_filter($ids,fn($id)=>is_string($id)&&preg_match('/^Q[1-9][0-9]*$/D',$id)));
            if(!$ids)return $this->remember($key,null);
            $entities=$this->get('https://www.wikidata.org/w/api.php',['action'=>'wbgetentities','format'=>'json','ids'=>implode('|',$ids),'props'=>'labels|aliases|claims','languages'=>'vi|en']);
            $matches=[];
            foreach($entities['entities'] ?? [] as $entity) {
                $names=array_column($entity['labels'] ?? [],'value');foreach($entity['aliases'] ?? [] as $aliases)array_push($names,...array_column($aliases,'value'));
                if(!in_array($this->name($place['name']),array_map($this->name(...),$names),true))continue;
                $coordinate=$this->claim($entity,'P625');$file=$this->claim($entity,'P18');
                if(!is_array($coordinate)||!is_string($file)||!isset($coordinate['latitude'],$coordinate['longitude'])||$this->distance($place['coordinates'],[$coordinate['longitude'],$coordinate['latitude']])>500)continue;
                $matches[]=['id'=>$entity['id'],'file'=>$file];
            }
            if(count($matches)!==1)return $this->remember($key,null);
            $match=$matches[0];
            $data=$this->get('https://commons.wikimedia.org/w/api.php',['action'=>'query','format'=>'json','prop'=>'imageinfo','titles'=>'File:'.$match['file'],
                'iiprop'=>'url|mime|extmetadata','iiurlwidth'=>720,'iiextmetadatafilter'=>'Artist|Credit|LicenseShortName|LicenseUrl|Restrictions']);
            $info=array_values($data['query']['pages'] ?? [])[0]['imageinfo'][0] ?? [];$meta=$info['extmetadata'] ?? [];
            $license=trim(strip_tags($meta['LicenseShortName']['value'] ?? ''));$author=trim(strip_tags($meta['Artist']['value'] ?? ''));
            if(!$author||!preg_match('/^(?:CC BY(?:-SA)? [1-4]\.0|CC0(?: 1\.0)?|Public domain)$/iD',$license)
                || !in_array($info['mime'] ?? '',['image/jpeg','image/png','image/webp'],true) || !empty($meta['Restrictions']['value']))return $this->remember($key,null);
            $url=$info['thumburl'] ?? null;$source=$info['descriptionurl'] ?? null;$licenseUrl=$meta['LicenseUrl']['value'] ?? null;
            if(is_string($licenseUrl)&&str_starts_with($licenseUrl,'http://creativecommons.org/'))$licenseUrl='https://'.substr($licenseUrl,7);
            // Commons public-domain files often declare the rights on the file page without a LicenseUrl.
            if (!$licenseUrl && strcasecmp($license,'Public domain')===0) $licenseUrl=$source;
            if(!$this->url($url,['upload.wikimedia.org','thumb.wikimedia.org'])||!$this->url($source,['commons.wikimedia.org'])
                ||!$this->url($licenseUrl,['creativecommons.org','commons.wikimedia.org']))return $this->remember($key,null);
            return $this->remember($key,['url'=>strtok($url,'?'),'alt'=>$place['name'],'author'=>Str::limit($author,250),'license'=>$license,'license_url'=>$licenseUrl,
                'source_url'=>$source,'source'=>'Wikimedia Commons','identity_url'=>'https://www.wikidata.org/wiki/'.$match['id'],
                'verification'=>'Tên/biệt danh khớp và tọa độ cách dưới 500 m; ảnh P18 của thực thể.']);
        } catch(\Exception $error) {
            // An optional photo failure never blocks advice, routing or state persistence. Never log response HTML.
            return $this->remember($key,null);
        }
    }

    /** No network: safe for saved-trip resources and the synchronous generation budget. */
    public function known(array $place): ?array
    {
        if (!empty($place['image'])) return $place['image'];
        if (($place['provider'] ?? '')==='google_sheet') return null;
        if (empty($place['coordinates']) || empty($place['name'])) return null;
        $path=resource_path('content/itinerary/place-photos.json');
        $records=is_file($path)?json_decode(file_get_contents($path),true):[];
        foreach ($records['places'] ?? [] as $record) {
            if (in_array($this->name($place['name']),array_map($this->name(...),$record['names']),true)
                && $this->distance($place['coordinates'],$record['coordinates'])<=$record['radius_metres']) return $record['image'];
        }
        return Cache::get($this->key($place))['image'] ?? null;
    }

    private function key(array $place): string
    {
        return 'itinerary.photo.v2.'.hash('sha256',json_encode([$place['name'],$place['coordinates'] ?? null,$place['wikidata_id'] ?? null]));
    }

    private function get(string $url,array $params):array
    {
        $response=Http::acceptJson()->withUserAgent('AUREL-Travel/1.0 (place photo attribution)')->connectTimeout(2)->timeout(4)
            ->withOptions(['allow_redirects'=>false])->get($url,$params);
        if(!$response->successful()||!is_array($response->json()))throw new \RuntimeException('Photo source unavailable.');
        return $response->json();
    }
    private function claim(array $entity,string $property):mixed
    {
        $values=array_values(array_filter($entity['claims'][$property] ?? [],fn($claim)=>($claim['rank'] ?? '')!=='deprecated'&&isset($claim['mainsnak']['datavalue']['value'])));
        usort($values,fn($a,$b)=>(($b['rank'] ?? '')==='preferred')<=>(($a['rank'] ?? '')==='preferred'));
        return $values[0]['mainsnak']['datavalue']['value'] ?? null;
    }
    private function remember(string $key,?array $image):?array {Cache::put($key,['image'=>$image],$image ? 86400 : 600);return $image;}
    private function name(string $name):string {return preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($name)));}
    private function url(mixed $url,array $hosts):bool {return is_string($url)&&parse_url($url,PHP_URL_SCHEME)==='https'&&in_array(parse_url($url,PHP_URL_HOST),$hosts,true)&&!parse_url($url,PHP_URL_USER);}
    private function distance(array $a,array $b):float
    {
        $lat=deg2rad($b[1]-$a[1]);$lon=deg2rad($b[0]-$a[0]);$h=sin($lat/2)**2+cos(deg2rad($a[1]))*cos(deg2rad($b[1]))*sin($lon/2)**2;
        return 6371000*2*asin(min(1,sqrt($h)));
    }
}
