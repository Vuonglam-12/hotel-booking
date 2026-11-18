<?php
namespace App\Services\Places;

use Carbon\CarbonImmutable;

/** Calendar comparison only. Never an observation that a business is actually open. */
final class OpeningHours
{
    public const NOTICE='Đối chiếu theo giờ tham khảo từ nguồn công khai; cần kiểm tra lại với địa điểm trước khi đi.';
    private const DAYS=[1=>'Thứ hai',2=>'Thứ ba',3=>'Thứ tư',4=>'Thứ năm',5=>'Thứ sáu',6=>'Thứ bảy',7=>'Chủ nhật'];

    public function check(?array $schedule,?string $date,?string $from=null,?string $to=null,int $endDayOffset=0): array
    {
        $tz=$schedule['timezone'] ?? 'Asia/Ho_Chi_Minh';
        $result=['status'=>'unknown','visit_date'=>$date,'timezone'=>$tz,'label'=>'Chưa có thông tin giờ hoạt động','notice'=>self::NOTICE,'suggestions'=>[],'exception'=>false];
        if(!$schedule || !empty($schedule['issue']) || !in_array($tz,\DateTimeZone::listIdentifiers(),true))return $result;
        if(!$date)return [...$result,'status'=>'date_required','label'=>'Chọn ngày ghé để xem giờ hoạt động tham khảo'];
        $day=CarbonImmutable::createFromFormat('!Y-m-d',$date,$tz);
        if(!$day || $day->format('Y-m-d')!==$date)return $result;
        $info=$this->day($schedule,$day);$prefix=self::DAYS[$day->dayOfWeekIso].', '.$day->format('d/m').': ';
        $rule=$info['rule'];$label=match($rule['state']){'closed'=>'Nghỉ cả ngày','24h'=>'Mở 24 giờ','open'=>implode(', ',array_map(fn($slot)=>$slot['start'].'–'.$slot['end'].($slot['end_day_offset']?' (ngày hôm sau)':''),$rule['intervals'])),default=>'Chưa có thông tin giờ hoạt động'};
        if($info['carry']){
            $carry=implode(', ',array_map(fn($range)=>CarbonImmutable::createFromTimestamp($range[0],$tz)->format('H:i').'–'.CarbonImmutable::createFromTimestamp($range[1],$tz)->format('H:i').' (từ ca qua đêm hôm trước)',$info['carry']));
            $label=$carry.($rule['state']==='unknown'?'; các giờ khác chưa có thông tin':', '.$label);
        }
        $result=[...$result,'label'=>$prefix.$label,'exception'=>$info['exception'],'intervals'=>$rule['intervals'] ?? [],'day_state'=>$rule['state']];
        if(!$from || !$to)return [...$result,'status'=>$rule['state']==='unknown'?'unknown':($rule['state']==='closed'?'closed':'hours_only')];
        $start=CarbonImmutable::parse($date.' '.$from,$tz);$end=CarbonImmutable::parse($date.' '.$to,$tz)->addDays($endDayOffset);
        if($end->lte($start))return [...$result,'status'=>'invalid_visit','message'=>'Giờ kết thúc phải sau giờ bắt đầu của khoảng ghé.'];
        $status=$this->coverage($schedule,$start,$end);
        $result['status']=$status;
        $result['message']=match($status){'within_hours'=>'Toàn bộ khoảng ghé nằm trong giờ tham khảo.','closed'=>'Lịch tham khảo ghi nhận nghỉ trong ngày ghé.','outside_hours'=>'Khoảng ghé vượt ca hoạt động hoặc vắt qua giờ nghỉ.',default=>'Chưa có thông tin giờ hoạt động đủ rõ cho toàn bộ khoảng ghé.'};
        if(in_array($status,['closed','outside_hours'],true)){
            $result['suggestions']=$this->suggestions($schedule,$start,(int)$start->diffInMinutes($end));
            $result['alternative']='Bạn có thể chọn giờ khác hoặc đổi địa điểm. Lịch hiện tại được giữ nguyên.';
        }
        return $result;
    }

    public function nextSlot(array $schedule,string $date,string $earliest,int $minutes,string $windowEnd): ?string
    {
        $tz=$schedule['timezone'] ?? 'Asia/Ho_Chi_Minh';$start=CarbonImmutable::parse($date.' '.$earliest,$tz);$limit=CarbonImmutable::parse($date.' '.$windowEnd,$tz);
        foreach($this->day($schedule,$start->startOfDay())['ranges'] as [$a,$b]){
            $candidate=$start->max(CarbonImmutable::createFromTimestamp($a,$tz));$end=$candidate->addMinutes($minutes);
            if($end->lte($limit) && $this->coverage($schedule,$candidate,$end)==='within_hours')return $candidate->format('H:i');
        }
        return null;
    }

    /** Presentation at the current local time; never inferred from a scheduled visit. */
    public function display(?array $schedule,?string $text=null,?CarbonImmutable $now=null): array
    {
        $text=trim($text ?? '');$tz=$schedule['timezone'] ?? 'Asia/Ho_Chi_Minh';
        $valid=$schedule && empty($schedule['issue']) && in_array($tz,\DateTimeZone::listIdentifiers(),true);
        $known=$valid && collect([...array_values($schedule['weekly'] ?? []),...array_values($schedule['exceptions'] ?? [])])->contains(fn($r)=>($r['state'] ?? 'unknown')!=='unknown');
        $result=['status'=>'unknown','has_data'=>$known || $text!=='','summary'=>$text!==''?'Giờ hoạt động: '.$text:'Chưa có thông tin giờ hoạt động',
            'timezone'=>$valid?$tz:null,'weekly'=>[],'exceptions'=>[],'references'=>[],'hours_text'=>$text ?: null,'notice'=>self::NOTICE];
        if(!$valid || !$known)return $result;
        $now=($now ?? CarbonImmutable::now($tz))->setTimezone($tz);$day=$now->startOfDay();$today=$this->day($schedule,$day);
        $result['evaluated_at']=$now->toIso8601String();$result['local_date']=$now->toDateString();
        foreach(self::DAYS as $number=>$label){$rule=$schedule['weekly'][$number] ?? ['state'=>'unknown','intervals'=>[]];
            $result['weekly'][]=['weekday'=>$number,'label'=>$label,'hours'=>$this->ruleLabel($rule),'today'=>$number===$now->dayOfWeekIso];
        }
        foreach($schedule['exceptions'] ?? [] as $date=>$rule)if($date>=$day->toDateString() && $date<=$day->addDays(30)->toDateString())$result['exceptions'][]=['date'=>$date,'hours'=>$this->ruleLabel($rule)];
        foreach([...array_values($schedule['weekly'] ?? []),...array_values($schedule['exceptions'] ?? [])] as $rule){
            $reference=['source_url'=>$rule['source_url'] ?? null,'checked_at'=>$rule['checked_at'] ?? null];
            if(($reference['source_url'] || $reference['checked_at']) && !in_array($reference,$result['references'],true))$result['references'][]=$reference;
        }
        $result['summary']='Giờ hoạt động: '.$this->ruleLabel($today['rule']);
        foreach($today['ranges'] as [$opens,$closes])if($now->timestamp>=$opens && $now->timestamp<$closes){
            $result['status']='open';$closing=CarbonImmutable::createFromTimestamp($closes,$tz);
            // Continue an overnight shift across midnight; tomorrow's exception still wins.
            for($i=0;$i<7 && $closing->isStartOfDay();$i++){
                $next=$this->day($schedule,$closing);$continuation=collect($next['ranges'])->first(fn($r)=>$r[0]===$closing->timestamp);
                if(!$continuation)break;$closing=CarbonImmutable::createFromTimestamp($continuation[1],$tz);
            }
            $suffix=$closing->toDateString()===$now->toDateString()?'':($closing->toDateString()===$day->addDay()->toDateString()?' ngày mai':' ngày '.$closing->format('d/m'));
            $result['summary']=$today['rule']['state']==='24h'?'Đang mở cửa · Mở 24 giờ hôm nay'
                :'Đang mở cửa · Đóng cửa vào '.$closing->format('H:i').$suffix;
            return $result;
        }
        if($today['known']){
            $result['status']='closed';$result['summary']='Đang đóng cửa';
            foreach($today['ranges'] as [$opens,$closes])if($opens>$now->timestamp){$result['summary'].=' · Mở cửa vào '.CarbonImmutable::createFromTimestamp($opens,$tz)->format('H:i');break;}
        }
        return $result;
    }

    private function ruleLabel(array $rule): string
    {
        return match($rule['state'] ?? 'unknown'){'closed'=>'Nghỉ cả ngày','24h'=>'Mở 24 giờ',
            'open'=>implode(', ',array_map(fn($slot)=>$slot['start'].'–'.$slot['end'].(!empty($slot['end_day_offset'])?' (ngày hôm sau)':''),$rule['intervals'] ?? [])),
            default=>'Chưa xác nhận'};
    }

    private function rule(array $schedule,CarbonImmutable $day): array
    {
        return $schedule['exceptions'][$day->toDateString()] ?? $schedule['weekly'][(string)$day->dayOfWeekIso] ?? ['state'=>'unknown','intervals'=>[]];
    }

    private function day(array $schedule,CarbonImmutable $day): array
    {
        $day=$day->startOfDay();$next=$day->addDay();$rule=$this->rule($schedule,$day);$exception=array_key_exists($day->toDateString(),$schedule['exceptions'] ?? []);$ranges=[];$carry=[];
        if($rule['state']==='24h')$ranges[]=[$day->timestamp,$next->timestamp];
        if($rule['state']==='open')foreach($rule['intervals'] as $slot){
            $a=CarbonImmutable::parse($day->toDateString().' '.$slot['start'],$day->timezone);$b=CarbonImmutable::parse($day->toDateString().' '.$slot['end'],$day->timezone)->addDays($slot['end_day_offset']);
            $ranges[]=[$a->max($day)->timestamp,$b->min($next)->timestamp];
        }
        // A date exception governs the whole local calendar day, including any previous night's carry.
        if(!$exception && !in_array($rule['state'],['closed','24h'],true)){
            $previous=$day->subDay();$prior=$this->rule($schedule,$previous);
            if($prior['state']==='open')foreach($prior['intervals'] as $slot)if($slot['end_day_offset']){
                $a=CarbonImmutable::parse($previous->toDateString().' '.$slot['start'],$day->timezone);$b=CarbonImmutable::parse($previous->toDateString().' '.$slot['end'],$day->timezone)->addDay();
                if($b->gt($day)){$range=[$a->max($day)->timestamp,$b->min($next)->timestamp];$ranges[]=$range;$carry[]=$range;}
            }
        }
        usort($ranges,fn($a,$b)=>$a[0]<=>$b[0]);$merged=[];
        foreach($ranges as [$a,$b]){if($b<=$a)continue;$i=count($merged)-1;if($i>=0 && $a<=$merged[$i][1])$merged[$i][1]=max($b,$merged[$i][1]);else $merged[]=[$a,$b];}
        return ['rule'=>$rule,'exception'=>$exception,'ranges'=>$merged,'carry'=>$carry,'known'=>$rule['state']!=='unknown'];
    }

    private function coverage(array $schedule,CarbonImmutable $start,CarbonImmutable $end): string
    {
        $issues=[];
        for($day=$start->startOfDay();$day->lt($end);$day=$day->addDay()){
            $a=$start->max($day)->timestamp;$b=$end->min($day->addDay())->timestamp;if($b<=$a)continue;
            $info=$this->day($schedule,$day);$covered=false;foreach($info['ranges'] as [$opens,$closes])if($a>=$opens && $b<=$closes){$covered=true;break;}
            if(!$covered)$issues[]=$info['rule']['state']==='closed'?'closed':($info['known']?'outside_hours':'unknown');
        }
        foreach(['closed','outside_hours','unknown'] as $status)if(in_array($status,$issues,true))return $status;
        return 'within_hours';
    }

    private function suggestions(array $schedule,CarbonImmutable $start,int $minutes): array
    {
        $suggestions=[];$tz=$start->timezone;
        for($day=$start->startOfDay(),$i=0;$i<8 && count($suggestions)<3;$i++,$day=$day->addDay())foreach($this->day($schedule,$day)['ranges'] as [$a,$b]){
            $candidate=CarbonImmutable::createFromTimestamp($a,$tz)->max($start);$end=$candidate->addMinutes($minutes);
            if($this->coverage($schedule,$candidate,$end)!=='within_hours')continue;
            $offset=$candidate->startOfDay()->diffInDays($end->startOfDay());
            if($offset>1)continue;
            $suggestions[]=['date'=>$candidate->toDateString(),'start_time'=>$candidate->format('H:i'),'end_time'=>$end->format('H:i'),'end_day_offset'=>$offset];
            if(count($suggestions)===3)break;
        }
        return $suggestions;
    }
}
