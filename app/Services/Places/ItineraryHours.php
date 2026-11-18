<?php
namespace App\Services\Places;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ItineraryHours
{
    private array $locations=[];
    public function __construct(private OpeningHours $hours) {}

    public function source(int $locationId,array $item): ?object
    {
        $rows=$this->locations[$locationId] ??= Schema::hasTable('travel_source_place')?DB::table('travel_source_place')->where('source',SheetPlaceSync::source($locationId))->where('location_id',$locationId)->get():collect();
        if(!empty($item['source_place_id'])){
            $row=$rows->firstWhere('id',(int)$item['source_place_id']);
            abort_unless($row,422,'Địa điểm nguồn không thuộc điểm đến của lịch trình.');return $row;
        }
        $name=SheetPlaceMapper::normalize($item['title'] ?? $item['name'] ?? '');$address=SheetPlaceMapper::normalize($item['place_name'] ?? $item['address'] ?? '');
        $matches=$rows->filter(function($row)use($name,$address){
            if(SheetPlaceMapper::normalize($row->name)!==$name)return false;
            $stored=SheetPlaceMapper::normalize($row->address ?? '');return !$address || !$stored || str_contains($address,$stored) || str_contains($stored,$address);
        });
        return $matches->count()===1?$matches->first():null;
    }

    public function checkItem(int $locationId,array $item,?string $date): ?array
    {
        $source=$this->source($locationId,$item);
        if(!$source && ($item['item_type'] ?? 'activity')==='activity' && empty($item['place_name']) && empty($item['address']))return null;
        $schedule=$source && !empty($source->opening_schedule)?json_decode($source->opening_schedule,true):null;
        $result=$this->hours->check($schedule,$date,$item['start_time'] ?? null,$item['end_time'] ?? null,(int)($item['end_day_offset'] ?? 0));
        $result['display']=$this->displayForPlace($source?(array)$source:($item['hours_metadata'] ?? []));
        if($source){$result['source_place_id']=$source->id;$result['source_name']=$source->name;$result['hours_text']=$source->hours_text;
            if(!$source->is_enabled){$result=[...$result,'status'=>'place_disabled','label'=>'Địa điểm đã bị ngừng gợi ý; bạn nên chọn địa điểm khác.','suggestions'=>[]];
                $result['display']['status']='unknown';if($result['display']['has_data'])$result['display']['summary']='Giờ hoạt động: Cần xác nhận lại với địa điểm';}}
        return $result;
    }

    public function displayForPlace(array $place): array
    {
        $schedule=$place['opening_schedule'] ?? null;if(is_string($schedule))$schedule=json_decode($schedule,true);
        if(!$schedule && !empty($place['regular_opening'])){
            $opening=$place['regular_opening'];$weekly=[];
            foreach($opening['days'] ?? [] as $day)$weekly[$day]=['state'=>'open','intervals'=>[['start'=>$opening['start'],'end'=>$opening['end'],'end_day_offset'=>0]],'source_url'=>$opening['source_url'] ?? null];
            foreach($place['closed_weekdays'] ?? [] as $day)$weekly[$day]=['state'=>'closed','intervals'=>[],'source_url'=>$opening['source_url'] ?? null];
            $schedule=['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>$weekly,'exceptions'=>[]];
        }
        $raw=$place['hours_text'] ?? $place['opening_hours'] ?? null;
        $display=$this->hours->display($schedule,is_string($raw)?$raw:null);
        $reference=['source_url'=>$place['source_url'] ?? null,'checked_at'=>$place['source_checked_at'] ?? null];
        if($display['has_data'] && ($reference['source_url'] || $reference['checked_at']) && !in_array($reference,$display['references'],true))$display['references'][]=$reference;
        return $display;
    }

    public function checkPayload(array $data,?\App\Models\Customer $customer=null): array
    {
        $stored=collect();$ids=collect($data['days'] ?? [])->flatMap(fn($day)=>array_column($day['items'],'id'))->filter()->unique()->all();
        if($customer && $ids)$stored=DB::table('itinerary_item as items')->join('itinerary as trips','trips.id','=','items.itinerary_id')
            ->join('itinerary_place_snapshot as snapshots','snapshots.id','=','items.place_snapshot_id')->where('trips.customer_id',$customer->id)->where('trips.location_id',$data['location_id'])
            ->whereIn('items.id',$ids)->get(['items.id','items.title','snapshots.snapshot'])->keyBy('id');
        $start=$data['visit_start_date'] ?? null;$days=[];
        foreach($data['days'] ?? [] as $i=>$day){$date=$start?CarbonImmutable::parse($start,'Asia/Ho_Chi_Minh')->addDays($i)->toDateString():null;
            $days[]=['date'=>$date,'items'=>array_map(function($item)use($data,$date,$stored){
                $row=$stored->get($item['id'] ?? null);$meta=$row?json_decode($row->snapshot,true):[];
                $place=$meta['manual_place_name'] ?? $meta['name'] ?? $meta['address'] ?? '';
                if($row && $row->title===$item['title'] && trim($place)===trim($item['place_name'] ?? '') && empty($meta['place_changed']))$item['hours_metadata']=$meta;
                return ['opening_check'=>$this->checkItem($data['location_id'],$item,$date)];
            },$day['items'])];}
        return ['days'=>$days];
    }
}
