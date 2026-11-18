<?php

namespace App\Services\Itinerary;

use Illuminate\Support\Str;

/** Reviewed source metadata enriches existing catalog/provider records; it never creates a destination. */
final class VisitInformation
{
    public function enrich(array $place,string $location):array
    {
        $path=resource_path('content/itinerary/visit-facts.json');
        $data=is_file($path)?json_decode(file_get_contents($path),true):null;
        $today=now('Asia/Ho_Chi_Minh')->toDateString();
        if(!$data)return $place;
        foreach($data['places'] ?? [] as $record) {
            if($this->name($location)!==$this->name($record['location']) || !in_array($this->name($place['name']),array_map($this->name(...),$record['names']),true))continue;
            if(empty($place['coordinates']) || $this->distance($place['coordinates'],$record['coordinates'])>$record['radius_metres'])continue;
            foreach(['description','website','wikidata_id','image'] as $field)if(empty($place[$field])&&!empty($record[$field]))$place[$field]=$record[$field];
            $checked=$record['reviewed_on'] ?? $data['reviewed_on'];$due=$record['review_due'] ?? $data['review_due'];
            if($today<$checked || $today>$due || empty($record['ticket']))return $place;
            return [...$place,'ticket'=>[...$record['ticket'],'checked_at'=>$checked,'review_due'=>$due,'currency'=>'VND'],
                'regular_opening'=>$record['opening'] ?? null,'closed_weekdays'=>$record['closed_weekdays'] ?? []];
        }
        return $place;
    }

    public function estimate(?array $ticket,array $party):?array
    {
        if(!$ticket || ($ticket['review_due'] ?? '')<now('Asia/Ho_Chi_Minh')->toDateString())return null;
        if($ticket['status']==='free')return ['amount'=>0,'status'=>'known','scope'=>'entry_only','conditions'=>[$ticket['note']],'source_url'=>$ticket['source_url']];
        if(($ticket['status'] ?? '')!=='priced' || !is_int($ticket['adult_reference'] ?? null))return null;
        $amount=($party['adults'] ?? 0)*$ticket['adult_reference'];$unknownChildren=0;
        $freeRemaining=isset($ticket['free_child_limit_per_adult'])?($party['adults'] ?? 0)*$ticket['free_child_limit_per_adult']:null;
        foreach([...($party['children'] ?? []),...($party['infants'] ?? [])] as $child) {
            $height=$child['height_cm'] ?? null;$matched=false;
            if(is_int($height))foreach($ticket['tiers'] as $tier)if($height>=($tier['min_height_cm'] ?? 0)&&$height<=($tier['max_height_cm'] ?? 250)){
                if($tier['amount']===0 && $freeRemaining!==null){if($freeRemaining<=0){$unknownChildren++;$matched=true;break;}$freeRemaining--;}
                $amount+=$tier['amount'];$matched=true;break;
            }
            if(!$matched)$unknownChildren++;
        }
        return ['amount'=>$amount,'status'=>'estimated','scope'=>$unknownChildren?'adults_and_known_heights':'party',
            'unknown_children'=>$unknownChildren,'conditions'=>['Tạm tính người lớn theo vé tiêu chuẩn; đối chiếu chiều cao và điều kiện vé trước khi mua.'],
            'source_url'=>$ticket['source_url']];
    }

    private function name(string $name):string{return preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($name)));}
    private function distance(array $a,array $b):float
    {
        $h=sin(deg2rad($b[1]-$a[1])/2)**2+cos(deg2rad($a[1]))*cos(deg2rad($b[1]))*sin(deg2rad($b[0]-$a[0])/2)**2;
        return 6371000*2*asin(min(1,sqrt($h)));
    }
}
