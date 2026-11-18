<?php
namespace App\Services\Places;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SheetPlaceSync
{
    public function __construct(private SheetsReader $reader,private SheetPlaceMapper $mapper) {}

    public static function source(?int $locationId=null): string
    {
        $sources=app(SheetPlaceSources::class);
        $source=$locationId===null?$sources->all()[0]:$sources->forLocation($locationId);
        return $source?SheetPlaceSources::key($source):'';
    }

    public function run(bool $dryRun): array
    {
        $locks=[];$work=[];
        try{
            foreach(app(SheetPlaceSources::class)->all() as $source){
                $lock=Cache::lock('places.sheet.sync.'.hash('sha256',SheetPlaceSources::key($source)),300);
                if(!$lock->get())throw new \RuntimeException('Đang có một lượt đồng bộ Sheet khác.');
                $locks[]=$lock;$work[]=$this->prepareSource($source,$dryRun);
            }
            // Read and validate every source first; an error must not partially update another destination.
            $apply=fn()=>array_map(fn($callback)=>$callback(),$work);
            $reports=$dryRun?$apply():DB::transaction($apply);
            if(count($reports)===1)return $reports[0];
            $report=['dry_run'=>$dryRun,'source_reports'=>$reports];
            foreach(['rows_read','valid','skipped','inserted','updated','unchanged','disabled'] as $field)$report[$field]=array_sum(array_column($reports,$field));
            return $report;
        }finally{
            foreach(array_reverse($locks) as $lock)$lock->release();
        }
    }

    private function prepareSource(array $config,bool $dryRun): callable
    {
            $source=SheetPlaceSources::key($config);
            $sheet=$this->reader->read($config);$mapped=$this->mapper->map($sheet);
            if(!empty($config['id_prefix']))foreach($mapped['valid'] as $entry){
                if(!str_starts_with($entry['external_id'],$config['id_prefix']))throw new \RuntimeException('ID địa điểm không khớp nguồn '.$config['location_name'].'; chưa ghi DB.');
            }
            $hours=is_array($sheet['hours'] ?? null)?app(OpeningScheduleMapper::class)->map($sheet['hours'],array_column($mapped['valid'],'external_id')):null;
            $locations=Location::all(['id','name'])->filter(fn($l)=>SheetPlaceMapper::normalize($l->name)===SheetPlaceMapper::normalize($config['location_name']));
            if($locations->count()!==1)throw new \RuntimeException('Không xác định duy nhất Location của '.$config['location_name'].'; chưa ghi DB.');
            $location=$locations->first();$exists=Schema::hasTable('travel_source_place');
            if(!$exists && !$dryRun)throw new \RuntimeException('Chưa có bảng nguồn địa điểm. Chạy migration riêng sau dry-run.');
            if($hours!==null && !$dryRun && !Schema::hasColumn('travel_source_place','opening_schedule'))throw new \RuntimeException('Chưa chạy migration giờ hoạt động.');
            $work=function()use($source,$mapped,$location,$dryRun,$exists,$hours,$sheet){
                $old=$exists?DB::table('travel_source_place')->where('source',$source)->get()->keyBy('external_id'):collect();
                $report=['dry_run'=>$dryRun,'guide_rows'=>$mapped['guide_rows'],'rows_read'=>$mapped['rows'],'valid'=>count($mapped['valid']),'skipped'=>count($mapped['skipped']),
                    'source'=>$source,'data_tab'=>$sheet['data_tab'],
                    'checked_at'=>now('Asia/Ho_Chi_Minh')->toIso8601String(),
                    'location'=>['id'=>$location->id,'name'=>$location->name],'inserted'=>0,'updated'=>0,'unchanged'=>0,'disabled'=>0,'skip_reasons'=>$mapped['skipped'],
                    'hours_tab_state'=>$sheet['hours_state'] ?? 'not_configured','hours_rows'=>$hours['rows'] ?? 0,'hours_issues'=>$hours['issues'] ?? []];
                $seen=[];
                foreach($mapped['valid'] as $entry){
                    $id=$entry['external_id'];$seen[]=$id;$previous=$old->get($id);
                    if($previous && !empty($mapped['compact_format']))foreach($entry as $field=>$value){
                        if($field!=='content_hash' && !in_array($field,$mapped['present_fields'],true) && property_exists($previous,$field))$entry[$field]=$previous->$field;
                    }
                    if($hours!==null){$entry['opening_schedule']=isset($hours['schedules'][$id])?json_encode($hours['schedules'][$id],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;}
                    else{
                        $oldSchedule=!empty($previous->opening_schedule)?json_decode($previous->opening_schedule,true):null;
                        if($oldSchedule && ($oldSchedule['origin'] ?? '')!=='sheet_text_reference')$entry['opening_schedule']=$previous->opening_schedule;
                        else{$reference=app(ReferenceHours::class)->schedule($entry['hours_text'],$entry['source_url'],$entry['source_checked_at']);$entry['opening_schedule']=$reference?json_encode($reference,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;}
                    }
                    if(array_key_exists('opening_schedule',$entry)){$hash=$entry;unset($hash['content_hash']);$entry['content_hash']=hash('sha256',json_encode($hash,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
                    $changed=!$previous || $previous->content_hash!==$entry['content_hash'] || $previous->guide_hash!==$mapped['guide_hash'] || (int)$previous->location_id!==$location->id;
                    $report[$previous?($changed?'updated':'unchanged'):'inserted']++;
                    if(!$entry['is_enabled'])$report['disabled']++;
                    if($dryRun || !$changed)continue;
                    $values=[...$entry,'location_id'=>$location->id,'guide_hash'=>$mapped['guide_hash'],'updated_at'=>now()];
                    if($previous)DB::table('travel_source_place')->where('id',$previous->id)->where('source',$source)->update($values);
                    else DB::table('travel_source_place')->insert([...$values,'source'=>$source,'created_at'=>now()]);
                }
                // Rows removed/invalidated by a successful full read are disabled only in this source. Nothing is deleted.
                foreach($old as $id=>$previous)if(!in_array($id,$seen,true) && $previous->is_enabled){
                    $report['disabled']++;if(!$dryRun)DB::table('travel_source_place')->where('id',$previous->id)->where('source',$source)->update(['is_enabled'=>false,'content_hash'=>'','updated_at'=>now()]);
                }
                return $report;
            };
            return $work;
    }
}
