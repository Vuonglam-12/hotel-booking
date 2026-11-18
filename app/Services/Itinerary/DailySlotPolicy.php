<?php

namespace App\Services\Itinerary;

use App\Services\Places\OpeningHours;
use App\Services\Places\ReferenceHours;
use App\Services\Places\SheetPlaceMapper;
use Carbon\CarbonImmutable;

final class DailySlotPolicy
{
    public const SLOTS=[
        'morning'=>['label'=>'Sáng','start'=>'08:00','end'=>'11:30','category'=>'visit'],
        'lunch'=>['label'=>'Trưa','start'=>'11:30','end'=>'13:30','category'=>'food'],
        'afternoon'=>['label'=>'Chiều','start'=>'14:00','end'=>'17:30','category'=>'visit'],
        'dinner'=>['label'=>'Tối','start'=>'18:30','end'=>'20:30','category'=>'food'],
        'night'=>['label'=>'Khuya','start'=>'21:00','end'=>null,'category'=>'nightlife'],
    ];

    public function pace(?string $pace): array
    {
        return match($pace){'relaxed'=>['name'=>'relaxed','min'=>2,'max'=>2],'packed'=>['name'=>'packed','min'=>5,'max'=>8],default=>['name'=>'normal','min'=>3,'max'=>4]};
    }

    public function category(array $place): string
    {
        $category=SheetPlaceMapper::normalize($place['category'] ?? '');
        if($category==='food' || preg_match('/an uong|am thuc|nha hang|quan an/',$category))return 'food';
        if(($place['item_type'] ?? '')==='restaurant' && !str_contains($category,'ca phe') && !in_array('catering.cafe',$place['categories'] ?? [],true))return 'food';
        return 'visit';
    }

    public function nightEligible(array $place): bool
    {
        $text=SheetPlaceMapper::normalize(implode(' ',[$place['name'],$place['description'] ?? '',$place['category'] ?? '',...($place['categories'] ?? [])]));
        return (bool)preg_match('/\b(?:dem|night|nightlife|bar|pub|nhac song|am nhac|pho di bo|buoi toi)\b/',$text);
    }

    public function schedule(array $place): ?array
    {
        if(!empty($place['opening_schedule']))return $place['opening_schedule'];
        if(!empty($place['regular_opening'])){
            $opening=$place['regular_opening'];$weekly=[];
            foreach($opening['days'] ?? [] as $day)$weekly[$day]=['state'=>'open','intervals'=>[['start'=>$opening['start'],'end'=>$opening['end'],'end_day_offset'=>0]],'quality'=>'reference'];
            return ['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>$weekly,'exceptions'=>[]];
        }
        return app(ReferenceHours::class)->schedule($place['hours_text'] ?? null,$place['source_url'] ?? null,$place['source_checked_at'] ?? null);
    }

    public function duration(array $place): int
    {
        $suggested=$place['suggested_duration_minutes'] ?? null;
        if(is_numeric($suggested))return app(PlanningPolicy::class)->duration($place,(int)$suggested);
        return $this->category($place)==='food'?75:app(PlanningPolicy::class)->duration($place,90);
    }

    public function inScope(string $slot,array $data): bool
    {
        if(empty($data['_slot_scope']))return true;
        $range=self::SLOTS[$slot];
        return $range['start']<$data['_slot_scope']['end'] && ($range['end'] ?? '24:00')>$data['_slot_scope']['start'];
    }

    public function start(array $place,string $date,string $slot,array $data,?CarbonImmutable $earliest=null): ?CarbonImmutable
    {
        $definition=self::SLOTS[$slot];
        if(!$this->inScope($slot,$data))return null;
        if($slot==='night' && !$this->nightEligible($place))return null;
        if($slot!=='night' && $definition['category']!==$this->category($place))return null;
        $schedule=$this->schedule($place);if(!$schedule || !empty($schedule['issue']))return null;
        $tz=$schedule['timezone'] ?? 'Asia/Ho_Chi_Minh';$begin=CarbonImmutable::parse($date.' '.$definition['start'],$tz);
        $limit=$definition['end']?CarbonImmutable::parse($date.' '.$definition['end'],$tz):CarbonImmutable::parse($date.' 23:59',$tz)->addMinutes(1);
        if(!empty($data['_slot_scope'])){
            $scope=$data['_slot_scope'];$begin=$begin->max(CarbonImmutable::parse($date.' '.$scope['start'],$tz));
            $limit=$limit->min(CarbonImmutable::parse($date.' '.$scope['end'],$tz));
        }
        if($date===($data['start_date'] ?? null) && !empty($data['arrival_time']))$begin=$begin->max(CarbonImmutable::parse($date.' '.$data['arrival_time'],$tz));
        if($date===($data['end_date'] ?? null) && !empty($data['departure_time']))$limit=$limit->min(CarbonImmutable::parse($date.' '.$data['departure_time'],$tz));
        if($earliest)$begin=$begin->max($earliest);
        $duration=$this->duration($place);$dated=!($data['_provisional'] ?? false) || !empty($data['_hours_date_confirmed']);
        $checkDates=[$date];
        if(!$dated){
            $schedule['exceptions']=[];
            $checkDates=array_map(fn($day)=>CarbonImmutable::parse('2026-11-02')->addDays($day)->toDateString(),range(0,6));
        }
        for($clock=$begin;$clock->lt($limit);$clock=$clock->addMinutes(5)){
            $end=$clock->addMinutes($duration);
            if($slot!=='night' && $end->gt($limit))break;
            if($slot==='night' && !empty($data['departure_time']) && $date===($data['end_date'] ?? null) && $end->gt($limit))break;
            if($slot==='night' && !empty($data['_slot_scope']) && $end->gt($limit))break;
            $offset=(int)$clock->startOfDay()->diffInDays($end->startOfDay());$allowed=true;
            foreach($checkDates as $checkDate){
                if(app(OpeningHours::class)->check($schedule,$checkDate,$clock->format('H:i'),$end->format('H:i'),$offset)['status']!=='within_hours'){$allowed=false;break;}
            }
            if($allowed)return $clock;
        }
        return null;
    }
}
