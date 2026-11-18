<?php

namespace App\Services\Itinerary;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** A missing status is unknown, never evidence that a place is operating. */
final class PlaceAvailability
{
    private ?array $rules = null;
    private ?bool $hasTable = null;

    public function permanentlyClosed(array $place): bool
    {
        $raw=$place['datasource']['raw'] ?? []; if (!is_array($raw)) $raw=[];
        foreach ([$place,$raw] as $fields) {
            foreach (['business_status','operating_status','operational_status','status'] as $field) {
                if (is_string($fields[$field] ?? null) && in_array($this->normalize($fields[$field]),['closedpermanently','permanentlyclosed','permanentclosure'],true)) return true;
            }
            $flag=$fields['permanently_closed'] ?? null;
            if ($flag===true || $flag===1 || is_string($flag) && in_array(strtolower($flag),['1','true','yes'],true)) return true;
        }
        return false;
    }

    public function restrictions(): array
    {
        if ($this->rules !== null) return $this->rules;
        $file=resource_path('content/itinerary/place-exclusions.json');
        $this->rules=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR)['entries'] ?? [];
        $this->hasTable ??= Schema::hasTable('itinerary_place_exclusion');
        if ($this->hasTable) foreach (DB::table('itinerary_place_exclusion')->whereIn('status',['closed_permanently','reported_closed'])->get() as $entry) {
            $this->rules[]=['location_name'=>$entry->location_name,'names'=>[$entry->name],'provider_place_id'=>$entry->provider_place_id,
                'coordinates'=>$entry->coordinates ? json_decode($entry->coordinates,true) : null,'address'=>$entry->address,'status'=>$entry->status];
        }
        return $this->rules;
    }

    public function blocked(array $place, string $location): bool
    {
        if ($this->permanentlyClosed($place)) return true;
        $name=$this->normalize($place['name'] ?? $place['title'] ?? '');
        foreach ($this->restrictions() as $entry) {
            if ($this->normalizeLocation($entry['location_name']) !== $this->normalizeLocation($location)) continue;
            if (!empty($entry['provider_place_id']) && $entry['provider_place_id'] === ($place['provider_place_id'] ?? null)) return true;
            if (!in_array($name,array_map($this->normalize(...),$entry['names']),true)) continue;
            if (!empty($entry['coordinates']) && !empty($place['coordinates'])) {
                if ($this->distance($entry['coordinates'],$place['coordinates']) > 150) continue;
            } elseif (!empty($entry['address']) && !empty($place['address']) && $this->normalize($entry['address']) !== $this->normalize($place['address'])) continue;
            return true;
        }
        return false;
    }

    public function filter(array $places, string $location): array
    {
        return array_values(array_filter($places,fn($place)=>!$this->blocked($place,$location)));
    }

    /** Persist explicit provider closure signals, including those read from an older candidate cache. */
    public function observe(array $places, string $location): void
    {
        $this->hasTable ??= Schema::hasTable('itinerary_place_exclusion');
        if (!$this->hasTable) return;
        $changed=false;
        foreach ($places as $place) {
            if (!$this->permanentlyClosed($place) || empty($place['name'])) continue;
            $identity=$place['provider_place_id'] ?? $this->normalize($place['name']).'|'.json_encode($place['coordinates'] ?? []);
            $changed = DB::table('itinerary_place_exclusion')->insertOrIgnore([
                'identity_key'=>hash('sha256',$this->normalizeLocation($location).'|'.$identity),'location_name'=>mb_substr($location,0,150),
                'name'=>mb_substr($place['name'],0,200),'provider_place_id'=>$place['provider_place_id'] ?? null,
                'coordinates'=>isset($place['coordinates'])?json_encode($place['coordinates']):null,'address'=>mb_substr($place['address'] ?? '',0,500),
                'status'=>'closed_permanently','source'=>'Geoapify status signal',
                'observed_at'=>isset($place['retrieved_at'])?date('Y-m-d H:i:s',strtotime($place['retrieved_at'])):now(),
            ]) > 0 || $changed;
        }
        if ($changed) $this->rules=null;
    }

    public function mentionsBlocked(string $text, string $location): bool
    {
        $text=' '.preg_replace('/[^a-z0-9]+/',' ',Str::lower(Str::ascii($text))).' ';
        foreach ($this->restrictions() as $entry) {
            if ($this->normalizeLocation($entry['location_name']) !== $this->normalizeLocation($location)) continue;
            foreach ($entry['names'] as $name) {
                $name=preg_replace('/[^a-z0-9]+/',' ',Str::lower(Str::ascii($name)));
                if ($name!=='' && str_contains($text,' '.$name.' ')) return true;
            }
        }
        return false;
    }

    private function normalize(string $text): string { return preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($text))); }
    private function normalizeLocation(string $text): string { return preg_replace('/^(?:thanhpho|tinh)|city$/','',$this->normalize($text)); }
    private function distance(array $a,array $b): float
    {
        $h=sin(deg2rad($b[1]-$a[1])/2)**2+cos(deg2rad($a[1]))*cos(deg2rad($b[1]))*sin(deg2rad($b[0]-$a[0])/2)**2;
        return 6371000*2*asin(min(1,sqrt($h)));
    }
}
