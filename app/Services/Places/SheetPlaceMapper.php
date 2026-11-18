<?php
namespace App\Services\Places;

use Illuminate\Support\Str;

final class SheetPlaceMapper
{
    private const HEADERS=[
        'ID'=>'external_id','Tên địa điểm / chi nhánh'=>'name','Nhóm trải nghiệm'=>'category','Khu vực'=>'area','Địa chỉ tra cứu'=>'address',
        'Số điện thoại'=>'phone','Giờ hoạt động tham khảo'=>'hours_text','Giá thấp (VND)'=>'price_min','Giá cao (VND)'=>'price_max',
        'Đơn vị / phạm vi giá'=>'price_unit','Mô tả ngắn'=>'description','Thẻ sở thích'=>'tags','Thời gian ghé gợi ý (phút)'=>'visit_minutes',
        'Google Maps URL'=>'maps_url','Loại link Maps'=>'maps_link_type','Trạng thái theo nguồn'=>'status_text','Bằng chứng hoạt động'=>'operation_evidence',
        'Nguồn chính'=>'source_url','Nguồn đối chiếu'=>'secondary_sources','Ngày bằng chứng có ngày'=>'evidence_date','Ngày kiểm tra nguồn'=>'source_checked_at',
        'Lưu ý dữ liệu'=>'data_notes','Vĩ độ'=>'latitude','Kinh độ'=>'longitude','Ảnh có quyền sử dụng'=>'licensed_image_url',
    ];

    public static function normalize(string $text): string { return trim(preg_replace('/\s+/u',' ',Str::lower(Str::ascii($text)))); }

    public function map(array $sheet): array
    {
        $rows=$sheet['data'] ?? [];if(count($rows)<2)throw new \RuntimeException('Sheet không có địa điểm; giữ nguyên dữ liệu lần trước.');
        $normalizedHeaders=array_map(fn($value)=>self::normalize((string)$value),$rows[0]);
        $compact=in_array('gio hoat dong',$normalizedHeaders,true) && in_array('mo ta ngan',$normalizedHeaders,true) && in_array('nguon chinh',$normalizedHeaders,true);
        $guide=[];foreach($sheet['guide'] ?? [] as $row)if(isset($row[0],$row[1]))$guide[self::normalize((string)$row[0])]=(string)$row[1];
        if(!$compact || $guide)foreach(['Giới hạn xác minh','Link Google Maps','Tọa độ và ảnh','Giá','Điểm đến trong DB'] as $key)if(empty($guide[self::normalize($key)]))throw new \RuntimeException('Huong_dan thiếu giới hạn dữ liệu bắt buộc; chưa đồng bộ.');
        $aliases=[];foreach(self::HEADERS as $label=>$field){$aliases[self::normalize($label)]=$field;$aliases[$field]=$field;}
        $aliases=[...$aliases,'gio hoat dong'=>'hours_text','price_min_vnd'=>'price_min','price_max_vnd'=>'price_max','visit_minutes_estimate'=>'visit_minutes','source_checked_at'=>'source_checked_at'];
        $columns=[];foreach($rows[0] as $index=>$header){$normalized=self::normalize((string)$header);if($compact && $normalized==='gio hoat dong tham khao')continue;$field=$aliases[$normalized] ?? null;if($field){if(in_array($field,$columns,true))throw new \RuntimeException('Header Sheet bị trùng.');$columns[$index]=$field;}}
        foreach(['external_id','name','category','area','maps_url',...($compact?['source_url','description']:['status_text'])] as $field)if(!in_array($field,$columns,true))throw new \RuntimeException('Header Sheet thiếu trường '.$field.'.');
        $presentFields=array_values($columns);
        if(in_array('status_text',$presentFields,true) || array_intersect($normalizedHeaders,['is_active','is_enabled','enabled']))$presentFields[]='is_enabled';
        $valid=[];$skipped=[];$seen=[];
        foreach(array_slice($rows,1) as $index=>$row){
            if(!array_filter($row,fn($cell)=>trim((string)$cell)!==''))continue;
            $entry=array_fill_keys(array_values(self::HEADERS),null);foreach($columns as $column=>$field){$value=trim((string)($row[$column] ?? ''));$entry[$field]=$value===''?null:$value;}
            $id=$entry['external_id'];if($id && isset($seen[$id]))throw new \RuntimeException('Sheet trùng ID địa điểm; không ghi DB.');if($id)$seen[$id]=true;
            try{
                if(!$id || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$id) || !$entry['name'] || mb_strlen($entry['name'])>255 || !$entry['category'] || !$entry['area'])throw new \RuntimeException('Thiếu ID/tên/nhóm/khu vực hợp lệ');
                if(!($entry['maps_url']=MapsUrl::validate($entry['maps_url'])))throw new \RuntimeException('Link Google Maps không hợp lệ');
                foreach(['price_min','price_max','visit_minutes'] as $field)$entry[$field]=$this->number($entry[$field]);
                if($entry['price_min']!==null && $entry['price_max']!==null && $entry['price_min']>$entry['price_max'])throw new \RuntimeException('Khoảng giá không hợp lệ');
                if(($entry['visit_minutes'] ?? 0)>1440)throw new \RuntimeException('Thời lượng không hợp lệ');
                foreach(['latitude'=>90,'longitude'=>180] as $field=>$limit){if($entry[$field]!==null && (!is_numeric($entry[$field]) || !is_finite((float)$entry[$field]) || abs((float)$entry[$field])>$limit))throw new \RuntimeException('Tọa độ không hợp lệ');$entry[$field]=$entry[$field]===null?null:(float)$entry[$field];}
                if(($entry['latitude']===null)!==($entry['longitude']===null))throw new \RuntimeException('Tọa độ thiếu một thành phần');
                foreach(['evidence_date','source_checked_at'] as $field)$entry[$field]=$this->date($entry[$field]);
                $entry['phone']=$entry['phone']===null?null:preg_replace('/\s+/u','',$entry['phone']);
                $entry['tags']=json_encode($entry['tags']?array_values(array_filter(array_map('trim',explode(';',$entry['tags'])))):[],JSON_UNESCAPED_UNICODE);
                // Eligible for a sourced suggestion does not mean verified operating/open now.
                $entry['is_enabled']=in_array(self::normalize($entry['status_text'] ?? ''),['co thong tin hoat dong tren nguon cong khai','co trong nguon cong khai; can kiem tra hoat dong','active','enabled','dang hoat dong','hoat dong'],true);
                // Future source columns can explicitly disable a row; absent columns never imply verified openNow.
                foreach($rows[0] as $column=>$header)if(in_array(self::normalize((string)$header),['is_active','is_enabled','enabled'],true) && in_array(self::normalize((string)($row[$column] ?? '')),['0','false','no','disabled','inactive'],true))$entry['is_enabled']=false;
                $entry['content_hash']=hash('sha256',json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$valid[]=$entry;
            }catch(\RuntimeException $error){$skipped[]=['row'=>$index+2,'external_id'=>$id,'reason'=>$error->getMessage()];}
        }
        if(!$valid)throw new \RuntimeException('Không có địa điểm hợp lệ; giữ nguyên lần đồng bộ trước.');
        return ['rows'=>count($rows)-1,'valid'=>$valid,'skipped'=>$skipped,'present_fields'=>$presentFields,'compact_format'=>$compact,'guide_rows'=>count($sheet['guide'] ?? []),'guide_hash'=>hash('sha256',json_encode($sheet['guide'] ?? []))];
    }

    private function number(?string $value): ?int
    {
        if($value===null)return null;
        $value=preg_replace('/\s/u','',$value);
        if(!preg_match('/^(?:\d+|\d{1,3}(?:\.\d{3})+|\d{1,3}(?:,\d{3})+)$/D',$value))throw new \RuntimeException('Giá hoặc thời lượng không hợp lệ');
        $value=str_replace(['.',','],'',$value);
        if(!ctype_digit($value) || (float)$value>4294967295)throw new \RuntimeException('Giá hoặc thời lượng không hợp lệ');
        return (int)$value;
    }
    private function date(?string $value): ?string
    {
        if($value===null)return null;
        foreach(['!d/m/Y','!Y-m-d'] as $format){$date=\DateTimeImmutable::createFromFormat($format,$value);$errors=\DateTimeImmutable::getLastErrors();if($date && (!$errors || !$errors['warning_count'] && !$errors['error_count']))return $date->format('Y-m-d');}
        throw new \RuntimeException('Ngày dữ liệu không hợp lệ');
    }
}
