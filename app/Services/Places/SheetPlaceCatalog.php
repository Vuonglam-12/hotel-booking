<?php
namespace App\Services\Places;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SheetPlaceCatalog
{
    public const NOTICE='Theo thông tin công khai, cần kiểm tra lại với địa điểm trước khi đi. Đây không phải xác nhận đang mở cửa ngay lúc hỏi.';

    public function isPrimary(int $locationId): bool
    {
        return app(SheetPlaceSources::class)->forLocation($locationId)!==null;
    }

    /** Planning needs the full synchronized catalog, independently of the 12-card chat limit. */
    public function forPlanning(int $locationId,string $focus='general'): array
    {
        if(!Schema::hasTable('travel_source_place'))return [];
        return DB::table('travel_source_place')->where('location_id',$locationId)->where('source',SheetPlaceSync::source($locationId))
            ->where('is_enabled',true)->orderBy('external_id')->get()->filter(fn($row)=>MapsUrl::validate($row->maps_url))
            ->map($this->present(...))->filter(function($place)use($focus){
                if($focus==='food')return $place['item_type']==='restaurant';
                if($focus==='entertainment')return str_contains(SheetPlaceMapper::normalize($place['category']),'vui choi');
                return true;
            })->values()->all();
    }

    public function find(int $locationId,int $sourceId): ?array
    {
        if(!Schema::hasTable('travel_source_place'))return null;
        $row=DB::table('travel_source_place')->where('source',SheetPlaceSync::source($locationId))->where('location_id',$locationId)
            ->where('id',$sourceId)->where('is_enabled',true)->first();
        return $row && MapsUrl::validate($row->maps_url)?$this->present($row):null;
    }

    public function candidates(int $locationId,string $focus='general',array $state=[],string $message=''): array
    {
        if(!Schema::hasTable('travel_source_place'))return [];
        $query=DB::table('travel_source_place')->where('location_id',$locationId)->where('is_enabled',true)->where('source',SheetPlaceSync::source($locationId));
        $text=SheetPlaceMapper::normalize($message);
        if(preg_match('/\b(?:ca phe|cafe|coffee)\b/',$text))$query->where('category','like','%Cà phê%');
        elseif($focus==='food')$query->where(fn($q)=>$q->where('category','like','%Ăn uống%')->orWhere('category','like','%Cà phê%'));
        elseif($focus==='entertainment')$query->where('category','like','%Vui chơi%');
        elseif(preg_match('/\b(?:tham quan|kien truc|di tich)\b/',$text))$query->where('category','like','%Tham quan%');
        if(preg_match('/\b(?:an chay|mon chay)\b/',$text))$query->where(fn($q)=>$q->where('category','like','%chay%')->orWhere('tags','like','%chay%'));
        $preferences=SheetPlaceMapper::normalize(implode(' ',[...($state['interests'] ?? []),$message]));
        return $query->orderBy('external_id')->limit(100)->get()->filter(fn($p)=>MapsUrl::validate($p->maps_url))
            ->sortByDesc(function($p)use($preferences){$score=0;foreach(json_decode($p->tags,true) ?? [] as $tag)if(str_contains($preferences,SheetPlaceMapper::normalize($tag)))$score++;return $score;})
            ->take(min(12,max(1,(int)config('services.place_sheet.candidate_limit',12))))->map($this->present(...))->values()->all();
    }

    public function refreshPlaces(array $items,?int $locationId): array
    {
        $ids=collect($items)->filter(fn($p)=>($p['provider'] ?? '')==='google_sheet')->pluck('id')->filter(fn($id)=>preg_match('/^sheet:[1-9][0-9]*$/D',$id))->map(fn($id)=>(int)substr($id,6));
        if($ids->isEmpty())return array_values(array_filter($items,fn($p)=>($p['provider'] ?? '')!=='google_sheet'));
        $rows=Schema::hasTable('travel_source_place')?DB::table('travel_source_place')->where('source',SheetPlaceSync::source($locationId))->where('location_id',$locationId)->where('is_enabled',true)->whereIn('id',$ids)->get()->keyBy('id'):collect();
        $result=[];foreach($items as $item){if(($item['provider'] ?? '')!=='google_sheet'){$result[]=$item;continue;}
            $row=$rows->get((int)substr($item['id'],6));if($row && MapsUrl::validate($row->maps_url)){$fresh=$this->present($row);$fresh['opening_check']=app(OpeningHours::class)->check($fresh['opening_schedule'],$item['opening_check']['visit_date'] ?? null);$result[]=$fresh;}}
        return $result;
    }

    private function present(object $p): array
    {
        $category=SheetPlaceMapper::normalize($p->category);
        $tags=json_decode($p->tags,true) ?? [];
        if(str_contains($category,'ca phe'))$tags[]='catering.cafe';
        return [
                'id'=>'sheet:'.$p->id,'provider'=>'google_sheet','provider_place_id'=>$p->external_id,'name'=>$p->name,'description'=>$p->description,
                'source_place_id'=>$p->id,'opening_schedule'=>!empty($p->opening_schedule)?json_decode($p->opening_schedule,true):null,
                'address'=>$p->address,'area'=>$p->area,'category'=>$p->category,'categories'=>$tags,'phone'=>$p->phone,'hours_text'=>$p->hours_text,'source_checked_at'=>$p->source_checked_at,
                'price_reference'=>['min'=>$p->price_min===null?null:(int)$p->price_min,'max'=>$p->price_max===null?null:(int)$p->price_max,'unit'=>$p->price_unit],
                'maps_url'=>$p->maps_url,'maps_link_type'=>$p->maps_link_type,'status_text'=>$p->status_text,'status_notice'=>self::NOTICE,
                'source'=>'Thông tin công khai · cần kiểm tra lại trước khi đi','source_url'=>$p->source_url,'data_notes'=>$p->data_notes,
                'coordinates'=>$p->latitude===null||$p->longitude===null?null:[(float)$p->longitude,(float)$p->latitude],
                'image'=>null,'item_type'=>str_contains($category,'an uong')||str_contains($category,'ca phe')?'restaurant':'attraction',
                'suggested_duration_minutes'=>$p->visit_minutes,'price_status'=>'reference','opening_hours_verified'=>false,'region_verified'=>false,
            ];
    }
}
