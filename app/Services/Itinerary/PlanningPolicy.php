<?php

namespace App\Services\Itinerary;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Planning assumptions are separate from sourced prices, opening hours and route measurements. */
final class PlanningPolicy
{
    public const UNVERIFIED_TRANSFER_MINUTES=30;
    public const UNVERIFIED_TRANSFER_NOTICE='Lịch tạm chừa 30 phút giữa các địa điểm để bạn chủ động điều chỉnh. Đây là khoảng đệm sắp lịch, chưa phải thời gian đi thực tế; quãng đường và tuyến đi chưa được xác minh.';

    public function samePlace(array $a,array $b): bool
    {
        foreach(['provider_place_id','wikidata_id','cluster_id'] as $key)if(!empty($a[$key]) && ($a[$key]===($b[$key] ?? null)))return true;
        $namesA=[$a['name'] ?? '',...($a['aliases'] ?? [])];$namesB=[$b['name'] ?? '',...($b['aliases'] ?? [])];
        $near=$this->distance($a['coordinates'] ?? null,$b['coordinates'] ?? null);
        foreach($namesA as $x)foreach($namesB as $y){
            if($this->name($x)==='' || $this->name($y)==='')continue;
            if($this->name($x)===$this->name($y) && ($near===null || $near<=300))return true;
            // Proximity alone never collapses independent venues (especially restaurants).
            if(($a['item_type'] ?? '')==='restaurant' || ($b['item_type'] ?? '')==='restaurant')continue;
            $base=fn($n)=>preg_replace('/^(?:cong vien|vuon hoa|dinh|den|chua|khu di tich|di tich|quang truong)\s+/','',$this->name($n));
            if($near!==null && $near<=250 && strlen($base($x))>=6 && $base($x)===$base($y))return true;
        }
        return false;
    }

    public function unique(array $places,array $excluded=[]): array
    {
        $result=[];
        foreach($places as $place){foreach([...$excluded,...$result] as $other)if($this->samePlace($place,$other))continue 2;$result[]=$place;}
        return $result;
    }

    public function stops(array $data,array $places): array
    {
        $available=count($places);
        $minutes=$this->windowMinutes($data);
        [$min,$max]=$minutes>=480?[5,6]:($minutes>=390?[4,6]:($minutes>=240?[2,4]:[1,2]));
        $longest=max([60,...array_map(fn($place)=>$this->duration($place,60),$places)]);
        if($minutes>=390)$min=max(2,$min-(int)floor(max(0,$longest-60)/90));
        if(($data['pace'] ?? '')==='relaxed')$min=max(1,$min-1);
        $min=$data['_min_stops'] ?? $min;$max=$data['_max_stops'] ?? $max;
        $perDay=(int)floor($available/max(1,count($data['selected_dates'])));
        return ['min'=>max(1,min($min,$perDay)),'max'=>max(1,min($max,$perDay)),'preferred_min'=>$min];
    }

    public function windowMinutes(array $data): int
    {
        return max(0,(int)CarbonImmutable::parse($data['activity_window_start'])->diffInMinutes(CarbonImmutable::parse($data['activity_window_end']),false));
    }

    public function duration(array $place,int $suggested): int
    {
        if(is_numeric($place['suggested_duration_minutes'] ?? null))return max(30,min(360,(int)(round($place['suggested_duration_minutes']/5)*5)));
        $categories=implode(' ',$place['categories'] ?? []);
        [$min,$max]=str_contains($categories,'theme_park')||str_contains($categories,'water_park')?[150,300]
            :(str_contains($categories,'museum')?[75,150]:(($place['item_type'] ?? '')==='restaurant'?[45,90]:[45,120]));
        return max($min,min($max,(int)(round($suggested/5)*5)));
    }

    public function maxDuration(array $place): int
    {
        if(is_numeric($place['suggested_duration_minutes'] ?? null))return $this->duration($place,60);
        $categories=implode(' ',$place['categories'] ?? []);
        return str_contains($categories,'theme_park')||str_contains($categories,'water_park')?300:(str_contains($categories,'museum')?150:(($place['item_type'] ?? '')==='restaurant'?90:120));
    }

    public function isMeal(array $place): bool
    {
        return ($place['item_type'] ?? '')==='restaurant' && !str_contains(implode(' ',$place['categories'] ?? []),'catering.cafe');
    }

    public function quote(array $place,array $data): ?array
    {
        return app(VisitInformation::class)->estimate($place['ticket'] ?? null,$data);
    }

    public function tripDays(array $data): int
    {
        return isset($data['start_date'],$data['end_date'])?max(1,(int)CarbonImmutable::parse($data['start_date'])->diffInDays(CarbonImmutable::parse($data['end_date']))+1):count($data['selected_dates']);
    }

    public function budgetContext(array $data): array
    {
        $people=max(1,($data['adults'] ?? 1)+count($data['children'] ?? [])+count($data['infants'] ?? []));$days=$this->tripDays($data);$limit=$data['estimated_budget'];
        return ['total_group_vnd'=>$limit,'people'=>$people,'days'=>$days,'per_group_day_vnd'=>$limit===null?null:(int)floor($limit/$days),
            'per_person_day_vnd'=>$limit===null?null:(int)floor($limit/$days/$people),'accounted_before_vnd'=>$data['_budget_spent'] ?? 0,
            'soft_overrun_percent'=>10,'unknown_prices_are_not_free'=>true,'coverage'=>'Only source-backed ticket amounts; meals, lodging and transport may remain unpriced.'];
    }

    public function rank(array $places,array $data): array
    {
        $context=$this->budgetContext($data);
        $ranked=[];foreach($places as $index=>$place){
            $text=$this->name(implode(' ',[$place['name'],$place['description'] ?? '',...($place['categories'] ?? [])]));$score=0;
            foreach(preg_split('/\s*[,;]\s*/',implode(',',$data['interests'] ?? [])) as $interest)if($interest!=='' && str_contains($text,$this->name($interest)))$score++;
            $quote=$this->quote($place,$data);$pressure=0;
            if($context['per_group_day_vnd']!==null)$pressure=$quote===null?1:($quote['amount']>$context['per_group_day_vnd']?2:0);
            $ranked[]=['place'=>$place,'sort'=>[-$score,$pressure,$index]];
        }
        usort($ranked,fn($a,$b)=>$a['sort']<=>$b['sort']);return array_column($ranked,'place');
    }

    public function warnings(array $activities,?int $limit): array
    {
        $total=0;$unknown=0;foreach($activities as $activity)if(isset($activity['place'])){
            $quote=$activity['ticket_estimate'] ?? null;if($quote)$total+=$quote['amount'];else $unknown++;
        }
        $warnings=[];
        if($limit!==null && $total>$limit)$warnings[]='Phần giá có nguồn của cả nhóm tạm tính '.number_format($total,0,',','.').' ₫, vượt ngân sách '.number_format($total-$limit,0,',','.').' ₫. Chưa cộng các khoản còn thiếu.';
        if($unknown)$warnings[]=$unknown.' địa điểm chưa có giá đủ rõ; chưa thể kết luận tổng chi phí phù hợp ngân sách.';
        $warnings[]='Chi phí ăn uống, lưu trú và di chuyển chưa có giá nguồn thì chưa nằm trong tổng tạm tính.';
        return $warnings;
    }

    public function distance(?array $a,?array $b): ?float
    {
        if(!$a || !$b)return null;
        $h=sin(deg2rad($b[1]-$a[1])/2)**2+cos(deg2rad($a[1]))*cos(deg2rad($b[1]))*sin(deg2rad($b[0]-$a[0])/2)**2;
        return 6371000*2*asin(min(1,sqrt($h)));
    }
    private function name(string $name): string {return trim(preg_replace('/[^a-z0-9]+/',' ',Str::lower(Str::ascii($name))));}
}
