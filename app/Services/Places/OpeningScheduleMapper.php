<?php
namespace App\Services\Places;

/** Only explicit structured rows are interpreted. hours_text is never parsed or overwritten. */
final class OpeningScheduleMapper
{
    public function map(array $rows,array $placeIds): array
    {
        if(!$rows)return ['schedules'=>[],'issues'=>[],'rows'=>0];
        $headers=array_map(fn($h)=>SheetPlaceMapper::normalize((string)$h),$rows[0]);
        foreach(['place_id','rule_type','weekday','date','state','opens_at','closes_at','end_day_offset','timezone','data_quality'] as $key)if(!in_array($key,$headers,true))throw new \RuntimeException('Tab giờ hoạt động thiếu header '.$key.'; giữ nguyên dữ liệu lần trước.');
        $schedules=[];$issues=[];$invalid=[];
        foreach(array_slice($rows,1) as $index=>$cells){
            if(!array_filter($cells,fn($v)=>trim((string)$v)!==''))continue;
            $row=[];foreach($headers as $i=>$header)$row[$header]=trim((string)($cells[$i] ?? ''));
            $id=$row['place_id'];
            if(!in_array($id,$placeIds,true)){$issues[]=['row'=>$index+2,'reason'=>'ID địa điểm không tồn tại trong nguồn'];continue;}
            try {
                $tz=$row['timezone'];if(!in_array($tz,\DateTimeZone::listIdentifiers(),true))throw new \RuntimeException('Múi giờ không hợp lệ');
                if(isset($schedules[$id]) && $schedules[$id]['timezone']!==$tz)throw new \RuntimeException('Một địa điểm có múi giờ mâu thuẫn');
                $schedules[$id] ??= ['version'=>1,'timezone'=>$tz,'weekly'=>[],'exceptions'=>[]];
                $type=$row['rule_type'];if(!in_array($type,['weekly','exception'],true))throw new \RuntimeException('rule_type phải là weekly hoặc exception');
                if($type==='weekly'){
                    if(!preg_match('/^[1-7]$/D',$row['weekday']) || $row['date']!=='')throw new \RuntimeException('Thứ trong tuần phải là 1–7 và date để trống');
                    $key=$row['weekday'];$group='weekly';
                }else{
                    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$row['date']);
                    if(!$date || $date->format('Y-m-d')!==$row['date'] || $row['weekday']!=='')throw new \RuntimeException('Ngoại lệ cần date YYYY-MM-DD và weekday để trống');
                    $key=$row['date'];$group='exceptions';
                }
                $state=$row['state'];if(!in_array($state,['open','closed','24h','unknown'],true) || !in_array($row['data_quality'],['reference','unclear'],true))throw new \RuntimeException('Trạng thái hoặc chất lượng dữ liệu không hợp lệ');
                if($row['data_quality']==='unclear')$state='unknown';
                $interval=null;
                if($state==='open'){
                    if(!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/D',$row['opens_at']) || !preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/D',$row['closes_at']) || !in_array($row['end_day_offset'],['0','1'],true))throw new \RuntimeException('Ca mở cần HH:MM và end_day_offset 0/1');
                    $row['opens_at']=str_pad($row['opens_at'],5,'0',STR_PAD_LEFT);$row['closes_at']=str_pad($row['closes_at'],5,'0',STR_PAD_LEFT);
                    $from=$this->minutes($row['opens_at']);$to=$this->minutes($row['closes_at'])+1440*(int)$row['end_day_offset'];
                    if($to<=$from || $to-$from>1440)throw new \RuntimeException('Ca giờ không hợp lệ; ca qua đêm cần end_day_offset=1');
                    $interval=['start'=>$row['opens_at'],'end'=>$row['closes_at'],'end_day_offset'=>(int)$row['end_day_offset']];
                }elseif($state!=='unknown' && ($row['opens_at']!=='' || $row['closes_at']!=='' || !in_array($row['end_day_offset'],['','0'],true)))throw new \RuntimeException('Ngày nghỉ/24h không được có ca giờ');
                $previous=$schedules[$id][$group][$key] ?? null;
                if($previous && $previous['state']!==$state)throw new \RuntimeException('Các dòng cùng ngày có trạng thái mâu thuẫn');
                $rule=$previous ?? ['state'=>$state,'intervals'=>[],'quality'=>$row['data_quality'],'source_url'=>$row['source_url'] ?? null,'checked_at'=>$row['checked_at'] ?? null];
                if($interval && !in_array($interval,$rule['intervals'],true))$rule['intervals'][]=$interval;
                usort($rule['intervals'],fn($a,$b)=>strcmp($a['start'],$b['start']));
                for($i=1;$i<count($rule['intervals']);$i++)if($this->minutes($rule['intervals'][$i]['start'])<$this->minutes($rule['intervals'][$i-1]['end'])+1440*$rule['intervals'][$i-1]['end_day_offset'])throw new \RuntimeException('Các ca giờ bị chồng nhau');
                $schedules[$id][$group][$key]=$rule;
            }catch(\RuntimeException $error){$invalid[$id]=true;$issues[]=['row'=>$index+2,'reason'=>$error->getMessage()];}
        }
        foreach($invalid as $id=>$_)$schedules[$id]=['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>[],'exceptions'=>[],'issue'=>'unclear_source'];
        if(!$schedules && $issues)throw new \RuntimeException('Tab giờ không có ID hợp lệ thuộc nguồn địa điểm; giữ nguyên dữ liệu lần trước.');
        foreach($schedules as &$schedule){ksort($schedule['weekly']);ksort($schedule['exceptions']);}unset($schedule);
        return ['schedules'=>$schedules,'issues'=>$issues,'rows'=>count($rows)-1];
    }
    private function minutes(string $time): int {[$h,$m]=explode(':',$time);return (int)$h*60+(int)$m;}
}
