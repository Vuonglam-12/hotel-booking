<?php

namespace App\Services\Itinerary;

use App\Models\Itinerary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Keep empty-slot warnings with the saved trip, including after manual edits. */
final class PlanningMetadata
{
    public function rebuild(Itinerary $trip,?array $incoming=null): void
    {
        $old=$trip->planning_metadata ?? [];$meta=$incoming?array_replace($old,$incoming):$old;
        if(($meta['policy'] ?? '')!=='fixed_slots')return;
        $days=$old['days'] ?? [];
        foreach($incoming['days'] ?? [] as $date=>$day){$days[$date]=array_replace($days[$date] ?? [],$day);$days[$date]['slots']=array_replace($old['days'][$date]['slots'] ?? [],$day['slots']);}
        $rows=DB::table('itinerary_item as i')->join('itinerary_calendar_day as d','d.id','=','i.calendar_day_id')
            ->leftJoin('itinerary_place_snapshot as s','s.id','=','i.place_snapshot_id')->where('i.itinerary_id',$trip->id)
            ->get(['i.id','i.item_type','i.start_time','i.end_time','d.date','s.provider_place_id']);
        $warnings=[];$pace=app(DailySlotPolicy::class)->pace($meta['pace'] ?? $trip->pace);
        foreach($days as $date=>&$day){
            if($date<$trip->start_date->toDateString() || $date>$trip->end_date->toDateString()){unset($days[$date]);continue;}
            $number=(int)$trip->start_date->diffInDays(CarbonImmutable::parse($date))+1;$visits=0;
            foreach($day['slots'] as $slot=>&$definition){
                if(!isset(DailySlotPolicy::SLOTS[$slot]))continue;$definition=[...DailySlotPolicy::SLOTS[$slot],'ids'=>[]];
                foreach($rows as $row){
                    if($row->date!==$date || !$row->start_time || !$row->end_time)continue;
                    $start=substr($row->start_time,0,5);$end=substr($row->end_time,0,5);
                    if($start<$definition['start'] || ($definition['end'] && ($end<=$start || $end>$definition['end'])))continue;
                    if(in_array($slot,['lunch','dinner'],true) && $row->item_type!=='restaurant')continue;
                    if(in_array($slot,['morning','afternoon'],true) && !in_array($row->item_type,['attraction','shopping','activity'],true))continue;
                    $definition['ids'][]=$row->provider_place_id ?? 'manual:'.$row->id;
                }
                if(!$definition['ids'])$warnings[]='Ngày '.$number.' · '.$definition['label'].': khung giờ chưa có địa điểm phù hợp.';
                if(in_array($slot,['morning','afternoon'],true))$visits+=count($definition['ids']);
            }unset($definition);
            $day['visits']=$visits;
            if(isset($day['slots']['morning'],$day['slots']['afternoon']) && $visits<$pace['min'])$warnings[]='Ngày '.$number.': chưa đủ điểm tham quan theo pace '.$pace['name'].'.';
        }unset($day);
        $meta['days']=$days;$meta['warnings']=[...array_values(array_unique($warnings)),PlanningPolicy::UNVERIFIED_TRANSFER_NOTICE];
        $trip->forceFill(['planning_metadata'=>$meta])->save();
    }
}
