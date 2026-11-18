<?php

namespace App\Services\Places;

use App\Models\Location;

/** Source identity stays stable even when a destination's visible Sheet tab is renamed. */
final class SheetPlaceSources
{
    public function all(): array
    {
        $defaults=[
            'spreadsheet_id'=>config('services.place_sheet.spreadsheet_id'),
            'data_tab'=>config('services.place_sheet.data_tab','Dia_diem'),
            'guide_tab'=>config('services.place_sheet.guide_tab','Huong_dan'),
            'hours_tab'=>config('services.place_sheet.hours_tab','Gio_hoat_dong'),
            'location_name'=>config('services.place_sheet.location_name','Đà Lạt'),
        ];
        $defaults['id_prefix']=SheetPlaceMapper::normalize($defaults['location_name'])==='da lat'?'DL':null;
        $sources=[$defaults];
        foreach(config('services.place_sheet.additional_sources',[]) as $extra){
            $sources[]=array_replace($defaults,['id_prefix'=>null],$extra);
        }
        $keys=[];$locations=[];
        foreach($sources as $source){
            $name=SheetPlaceMapper::normalize($source['location_name']);$key=self::key($source);
            if($name==='' || empty($source['data_tab']) || isset($keys[$key]) || isset($locations[$name])){
                throw new \RuntimeException('Cấu hình nguồn Google Sheet bị thiếu hoặc trùng địa phương/tab; chưa đồng bộ.');
            }
            $keys[$key]=true;$locations[$name]=true;
        }
        return $sources;
    }

    public function forLocation(int $locationId): ?array
    {
        $name=Location::whereKey($locationId)->value('name');
        if(!is_string($name))return null;
        $normalized=SheetPlaceMapper::normalize($name);
        foreach($this->all() as $source){
            if(SheetPlaceMapper::normalize($source['location_name'])===$normalized)return $source;
        }
        return null;
    }

    public static function key(array $source): string
    {
        return 'google_sheet:'.($source['spreadsheet_id'] ?? '').':'.$source['data_tab'];
    }
}
