<?php

namespace App\Services\Places;

/** Parse explicit clock ranges only; this never verifies real-world operation. */
final class ReferenceHours
{
    public function schedule(?string $text,?string $url=null,?string $checked=null): ?array
    {
        if(!$text)return null;
        $raw=SheetPlaceMapper::normalize($text);
        if(preg_match('/chua|xac minh|kiem tra|tuy|thay doi|su kien|khoang|lien he|theo lich/',$raw))return null;
        $raw=str_replace(['–','—','−','→','->'], '-', $raw);
        $raw=preg_replace('/\s*\((?:tham khao|reference)\)\s*/',' ',$raw);
        $weekly=[];
        foreach(explode(';',$raw) as $part){
            $part=trim($part);if($part==='')continue;$days=range(1,7);
            if(preg_match('/^((?:t[2-7]|cn)(?:\s*-\s*(?:t[2-7]|cn))?)\s*:\s*(.+)$/',$part,$match)){
                $labels=preg_split('/\s*-\s*/',$match[1]);$from=$labels[0]==='cn'?7:(int)substr($labels[0],1)-1;
                $to=count($labels)===1?$from:($labels[1]==='cn'?7:(int)substr($labels[1],1)-1);
                if($to<$from)return null;$days=range($from,$to);$part=$match[2];
            }
            $rule=['state'=>'open','intervals'=>[],'quality'=>'reference','source_url'=>$url,'checked_at'=>$checked];
            if(in_array(trim($part),['24h','24/24','24/7','00:00-24:00'],true))$rule['state']='24h';
            elseif(in_array(trim($part),['nghi','dong cua'],true))$rule['state']='closed';
            else foreach(preg_split('/\s*[,\/]\s*/',$part) as $range){
                if(!preg_match('/^(\d{1,2}):([0-5]\d)\s*-\s*(\d{1,2}):([0-5]\d)$/D',trim($range),$match))return null;
                $a=(int)$match[1]*60+(int)$match[2];$b=(int)$match[3]*60+(int)$match[4];
                if((int)$match[1]>23 || (int)$match[3]>24 || $b>1440 || $a===$b)return null;
                $offset=$b<=$a?1:0;
                if($b===1440){$b=0;$offset=1;}
                $rule['intervals'][]=['start'=>sprintf('%02d:%02d',intdiv($a,60),$a%60),'end'=>sprintf('%02d:%02d',intdiv($b,60),$b%60),'end_day_offset'=>$offset];
            }
            foreach($days as $day){if(isset($weekly[$day]))return null;$weekly[$day]=$rule;}
        }
        if(!$weekly)return null;ksort($weekly);
        return ['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','origin'=>'sheet_text_reference','raw_text'=>$text,'weekly'=>$weekly,'exceptions'=>[]];
    }
}
