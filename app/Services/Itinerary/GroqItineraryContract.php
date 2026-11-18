<?php

namespace App\Services\Itinerary;

/** A fixed key for each requested date prevents missing days and separator objects. */
final class GroqItineraryContract
{
    public function fixedSlotFormat(string $model,array $dates,int $candidateCount): array
    {
        if(!in_array($model,['openai/gpt-oss-20b','openai/gpt-oss-120b'],true))return ['type'=>'json_object'];
        $choice=['type'=>'object','additionalProperties'=>false,'properties'=>['candidate_id'=>['type'=>'integer','enum'=>range(0,$candidateCount-1)],'reason'=>['type'=>'string']],'required'=>['candidate_id','reason']];
        $slots=[];foreach(array_keys(DailySlotPolicy::SLOTS) as $slot)$slots[$slot]=['type'=>'array','items'=>['$ref'=>'#/$defs/choice']];
        $slots['warnings']=['type'=>'array','items'=>['type'=>'string']];
        $day=['type'=>'object','additionalProperties'=>false,'properties'=>$slots,'required'=>array_keys($slots)];
        $days=[];foreach($dates as $date)$days[$date]=['$ref'=>'#/$defs/day'];
        return ['type'=>'json_schema','json_schema'=>['name'=>'fixed_slot_itinerary','strict'=>true,'schema'=>[
            'type'=>'object','additionalProperties'=>false,'$defs'=>['choice'=>$choice,'day'=>$day],
            'properties'=>['is_valid'=>['type'=>'boolean'],'title'=>['type'=>'string'],'reason'=>['type'=>['string','null']],
                'days'=>['type'=>'object','additionalProperties'=>false,'properties'=>$days,'required'=>array_values($dates)]],
            'required'=>['is_valid','title','reason','days']]]];
    }

    public function responseFormat(string $model,array $dates,int $candidateCount): array
    {
        if(!in_array($model,['openai/gpt-oss-20b','openai/gpt-oss-120b'],true))return ['type'=>'json_object'];
        $item=['type'=>'object','additionalProperties'=>false,
            'properties'=>[
                'candidate_id'=>['type'=>'integer','enum'=>range(0,$candidateCount-1)],
                'duration_minutes'=>['type'=>'integer'],
                'description'=>['type'=>'string'],
            ],'required'=>['candidate_id','duration_minutes','description']];
        $day=['type'=>'object','additionalProperties'=>false,
            'properties'=>['items'=>['type'=>'array','items'=>['$ref'=>'#/$defs/activity']]],'required'=>['items']];
        $days=[];
        foreach($dates as $date)$days[$date]=['$ref'=>'#/$defs/day'];
        return ['type'=>'json_schema','json_schema'=>['name'=>'itinerary_plan','strict'=>true,
            'schema'=>['type'=>'object','additionalProperties'=>false,'$defs'=>['activity'=>$item,'day'=>$day],
                'properties'=>[
                    'is_valid'=>['type'=>'boolean'],'title'=>['type'=>'string'],
                    'reason'=>['type'=>['string','null']],
                    'days'=>['type'=>'object','properties'=>$days,'required'=>array_values($dates),'additionalProperties'=>false],
                ],'required'=>['is_valid','title','reason','days']]]];
    }

    public function normalize(mixed $plan,array $dates,bool $structured): mixed
    {
        if(!$structured || !is_array($plan) || !is_array($plan['days'] ?? null))return $plan;
        $actual=array_keys($plan['days']);$expected=$dates;sort($actual);sort($expected);
        // Never discard unexpected entries or invent a missing day's activities.
        if($actual!==$expected)return $plan;
        $days=[];
        foreach($dates as $date){
            $day=$plan['days'][$date];
            if(!is_array($day) || array_keys($day)!==['items'] || !is_array($day['items']))return $plan;
            $days[]=['date'=>$date,'items'=>$day['items']];
        }
        $plan['days']=$days;
        return $plan;
    }
}
