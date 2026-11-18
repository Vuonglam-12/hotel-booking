<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Shared read-only hotel search. Inventory and capacity retain the booking module's rules. */
class HotelSearchService
{
    public function validate(array $input): array
    {
        $validator=Validator::make($input,[
            'search'=>'nullable|string|max:100','city'=>'nullable|string|max:100',
            'location_id'=>'nullable|integer|min:1','hotel_id'=>'nullable|integer|min:1',
            'check_in'=>'nullable|required_with:check_out|date_format:Y-m-d|after_or_equal:'.CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString(),
            'check_out'=>'nullable|required_with:check_in|date_format:Y-m-d|after:check_in',
            'guests'=>'nullable|integer|between:1,20','adults'=>'nullable|integer|between:1,20',
            'children'=>'nullable|integer|between:0,19','infants'=>'nullable|integer|between:0,19',
            'star'=>'nullable|integer|between:1,5','max_price'=>'nullable|numeric|gt:0',
            'amenity'=>'nullable|string|max:100','sort'=>'nullable|in:avg_rating,star_rating,name','dir'=>'nullable|in:asc,desc',
            'per_page'=>'nullable|integer|between:1,100','page'=>'nullable|integer|between:1,100000',
        ]);
        $validator->after(function($validator)use($input){
            if(!$validator->errors()->hasAny(['check_in','check_out'])&&!empty($input['check_in'])&&!empty($input['check_out'])
                && CarbonImmutable::parse($input['check_in'])->diffInDays(CarbonImmutable::parse($input['check_out']))>365) {
                $validator->errors()->add('check_out','Kỳ lưu trú tối đa 365 đêm.');
            }
            if(collect(['adults','children','infants'])->contains(fn($key)=>isset($input[$key]))) {
                $total=(int)($input['adults']??0)+(int)($input['children']??0)+(int)($input['infants']??0);
                if($total<1||$total>20||!isset($input['guests'])||(int)$input['guests']!==$total||(int)($input['adults']??0)<1) {
                    $validator->errors()->add('guests','Tổng số khách phải khớp nhóm khách, có ít nhất 1 người lớn và tối đa 20 khách.');
                }
            }
        });
        return array_filter($validator->validate(),fn($value)=>$value!==null&&$value!=='');
    }

    public function locations(): Builder
    {
        $query=Location::query();
        if(Schema::hasColumn('location','is_active'))$query->where('is_active',true);
        return $query;
    }

    private function normalized(string $value): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/','',Str::ascii($value)));
    }

    private function matches(string $value,string $term,bool $location=false): bool
    {
        $value=$this->normalized($value);$term=$this->normalized($term);
        if($term==='')return false;
        return str_contains($value,$term)||($location&&in_array($value,['hochiminh','thanhphohochiminh','tphochiminh'],true)&&in_array($term,['hcm','tphcm','saigon'],true));
    }

    private function baseQuery(): Builder
    {
        return Hotel::with(['location','images'=>fn($q)=>$q->orderByDesc('is_primary')->orderBy('id')])
            ->where('status','active')->whereIn('location_id',$this->locations()->select('id'));
    }

    public function query(array $filters): Builder
    {
        $query=$this->baseQuery()->with('amenities')->withMin(['rooms'=>fn($q)=>$q->where('status','available')->offered()],'price');
        if(isset($filters['location_id']))$query->where('location_id',$filters['location_id']);
        if(isset($filters['hotel_id']))$query->whereKey($filters['hotel_id']);
        if(isset($filters['star']))$query->where('star_rating',$filters['star']);
        if(isset($filters['amenity']))$query->whereHas('amenities',fn($q)=>$q->where('amenity',$filters['amenity']));
        foreach(['city','search'] as $field)if(isset($filters[$field])) {
            $locations=$this->locations()->get(['id','name'])->filter(fn($location)=>$this->matches($location->name,$filters[$field],true))->pluck('id');
            $hotels=$field==='search'?$this->baseQuery()->get(['hotel.id','hotel.name'])->filter(fn($hotel)=>$this->matches($hotel->name,$filters[$field]))->pluck('id'):collect();
            $query->where(fn($q)=>$q->whereIn('location_id',$locations)->orWhereIn('hotel.id',$hotels));
        }
        return $query->orderBy($filters['sort']??'avg_rating',$filters['dir']??'desc')->orderBy('hotel.id');
    }

    public function search(array $filters,int $defaultPerPage=10): LengthAwarePaginator
    {
        $query=$this->query($filters);
        $perPage=(int)($filters['per_page']??$defaultPerPage);
        if(!isset($filters['check_in'])&&!isset($filters['guests'])&&!isset($filters['max_price'])) {
            $page=$query->paginate($perPage)->withQueryString();
        } else {
            $hotels=$query->with(['rooms'=>fn($q)=>$q->where('status','available')->offered()->with('roomType')])->get();
            $catalog=app(HotelRoomTypeService::class);
            $rooms=$catalog->applyToRooms($hotels->flatMap->rooms)->groupBy('hotel_id');
            $availability=app(RoomAvailabilityService::class);
            $guests=(int)($filters['guests']??1);
            $hotels=$hotels->filter(function($hotel)use($rooms,$filters,$guests,$availability){
                $matches=[];
                foreach(($rooms[$hotel->id]??collect())->groupBy('room_type_id') as $id=>$stock) {
                    $type=$stock->first()->roomType;
                    if(!$type||$type->capacity<1||$type->base_price<=0)continue;
                    if(isset($filters['max_price'])&&$type->base_price>$filters['max_price'])continue;
                    $required=(int)ceil($guests/$type->capacity);
                    $remaining=isset($filters['check_in'])?$availability->forType($hotel->id,(int)$id,$filters['check_in'],$filters['check_out'])['available_rooms']:$stock->count();
                    if($required>10||$remaining<$required)continue;
                    $matches[]=['room_type_id'=>(int)$id,'required_rooms'=>$required,'available_rooms'=>$remaining,
                        'capacity'=>(int)$type->capacity,'guests'=>$guests,'nightly_price'=>$type->base_price,'dates_checked'=>isset($filters['check_in'])];
                }
                $hotel->unsetRelation('rooms');
                if(!$matches)return false;
                usort($matches,fn($a,$b)=>$a['nightly_price']<=>$b['nightly_price']);
                $hotel->setAttribute('rooms_min_price',$matches[0]['nightly_price']);
                $hotel->setAttribute('search_availability',$matches[0]);
                return true;
            })->values();
            $pageNumber=(int)($filters['page']??1);
            $page=new LengthAwarePaginator($hotels->forPage($pageNumber,$perPage)->values(),$hotels->count(),$perPage,$pageNumber,
                ['path'=>LengthAwarePaginator::resolveCurrentPath(),'query'=>request()->query()]);
        }
        $page->getCollection()->each(fn($hotel)=>$hotel->append('cover_image_url'));
        return $page;
    }

    public function suggestions(string $term): array
    {
        $locations=$this->locations()->whereHas('hotels',fn($q)=>$q->where('status','active'))
            ->withCount(['hotels as active_hotels_count'=>fn($q)=>$q->where('status','active')])->orderByDesc('active_hotels_count')->orderBy('name')->get();
        if($term!=='')$locations=$locations->filter(fn($location)=>$this->matches($location->name,$term,true));
        $hotels=$term===''?collect():$this->baseQuery()->orderBy('name')->get()
            ->filter(fn($hotel)=>$this->matches($hotel->name,$term)||$this->matches($hotel->location->name,$term,true))->take(5);
        return ['locations'=>$locations->take(6)->map(fn($location)=>['id'=>$location->id,'name'=>$location->name,'country'=>$location->country,'hotels_count'=>$location->active_hotels_count])->values(),
            'hotels'=>$hotels->map(fn($hotel)=>['id'=>$hotel->id,'name'=>$hotel->name,'location'=>['id'=>$hotel->location_id,'name'=>$hotel->location->name],'cover_image_url'=>$hotel->cover_image_url])->values()];
    }
}
