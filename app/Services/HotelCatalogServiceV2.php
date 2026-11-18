<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Location;
use App\Models\RoomTypeV2;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Support\Facades\Validator;

/** Canonical read source. Legacy compatibility aliases never fall back to physical Room data. */
class HotelCatalogServiceV2
{
    public function guard(): void
    {
        $s=RoomTypeV2Inventory::open('mysql');
        if ($s->db->table('booking_room as br')->join('booking as b','b.id','=','br.booking_id')->leftJoin('room_type as rt','rt.id','=','br.room_type_id')
            ->where(fn($q)=>$q->whereNull('rt.hotel_id')->orWhereColumn('rt.hotel_id','!=','b.hotel_id'))->exists()) throw new \RuntimeException('Unmapped Booking history; V2 catalog refused.');
        foreach ($s->db->table('room_type')->whereNotNull('hotel_id')->get(['id','hotel_id','total_rooms','capacity','status','base_price']) as $t) RoomTypeV2Inventory::validateType($t);
    }
    public function query(array $filters=[],bool $activeOnly=true): \Illuminate\Database\Eloquent\Builder
    {
        $this->guard();
        $q=\App\Models\HotelReadV2::with(['location','amenities','images'])->select('hotel.*')->selectSub(function ($sub) {
            $sub->from('room_type')->selectRaw('MIN(base_price)')->whereColumn('hotel_id','hotel.id')->where('status','active');
        },'rooms_min_price')->selectSub(function ($sub) {
            $sub->from('room_type')->selectRaw('COALESCE(SUM(total_rooms),0)')->whereColumn('hotel_id','hotel.id');
        },'rooms_count');
        if ($activeOnly) $q->where('hotel.status','active');
        if (!empty($filters['status'])) $q->where('hotel.status',$filters['status']);
        if (!empty($filters['city'])) $q->whereHas('location',fn($l)=>$l->where('name','like','%'.$filters['city'].'%'));
        if (!empty($filters['location_id'])) $q->where('location_id',$filters['location_id']);
        if (!empty($filters['star'])) $q->where('star_rating',$filters['star']);
        if (isset($filters['max_price']) && $filters['max_price']!=='') $q->whereExists(fn($t)=>$t->from('room_type')->whereColumn('hotel_id','hotel.id')->where('status','active')->where('base_price','<=',$filters['max_price']));
        if (!empty($filters['amenity'])) $q->whereHas('amenities',fn($a)=>$a->where('amenity',$filters['amenity']));
        if (!empty($filters['search'])) $q->where('hotel.name','like','%'.$filters['search'].'%');
        return $q;
    }
    public function getList(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Validator::make($filters,['per_page'=>'sometimes|integer|between:1,100','max_price'=>'sometimes|nullable|numeric|min:0','dir'=>'sometimes|in:asc,desc'])->validate();
        $sort=in_array($filters['sort']??'', ['avg_rating','star_rating','name','rooms_min_price'],true)?$filters['sort']:'avg_rating';
        $p=$this->query($filters)->orderBy($sort,$filters['dir']??'desc')->paginate($filters['per_page']??10);
        $p->getCollection()->each(fn($h)=>$h->append('cover_image_url')); return $p;
    }
    public function getDetail(int $id): Hotel
    {
        $hotel=$this->query()->findOrFail($id);
        $hotel->setRelation('roomTypes',RoomTypeV2::with('images')->where('hotel_id',$id)->where('status','active')->get());
        $hotel->review_count=RoomTypeV2Inventory::open('mysql')->db->table('review')->where('hotel_id',$id)->count();
        $hotel->rating_breakdown=RoomTypeV2Inventory::open('mysql')->db->table('review')->where('hotel_id',$id)
            ->selectRaw('AVG(rating) as avg_overall, AVG(rating_cleanliness) as avg_cleanliness, AVG(rating_service) as avg_service, AVG(rating_location) as avg_location')->first();
        $hotel->append('cover_image_url'); return $hotel;
    }
    public function getRooms(int $id,array $filters): \Illuminate\Support\Collection
    {
        $s=RoomTypeV2Inventory::open('mysql'); $this->getDetail($id);
        $data=Validator::make($filters,['check_in'=>'required_with:check_out|date_format:Y-m-d','check_out'=>'required_with:check_in|date_format:Y-m-d|after:check_in',
            'quantity'=>'sometimes|integer|between:1,10','num_guests'=>'sometimes|integer|min:1','guests'=>'sometimes|integer|min:1','room_id'=>'prohibited'])->validate();
        return RoomTypeV2::with('images')->where('hotel_id',$id)->where('status','active')->get()->map(function ($t) use ($s,$data) {
            $a=isset($data['check_in'])?$s->inspect($t,$data['check_in'],$data['check_out']):null;
            $quantity=$data['quantity']??1; $guests=$data['num_guests']??($data['guests']??1);
            return array_merge($t->toArray(),['cover_image_url'=>$t->cover_image_url,'gallery_image_urls'=>$t->gallery_image_urls,
                'available_rooms'=>$a['available_rooms']??null,'availability'=>$a,'bookable'=>$a!==null&&$a['available_rooms']>=$quantity&&$t->capacity*$quantity>=$guests]);
        });
    }
    public function getLocations(): \Illuminate\Database\Eloquent\Collection
    {
        $this->guard();
        return Location::withCount(['hotels'=>fn($q)=>$q->where('status','active')])->selectSub(function ($q) {
            $q->from('room_type as rt')->join('hotel as h','h.id','=','rt.hotel_id')->selectRaw('MIN(rt.base_price)')
                ->whereColumn('h.location_id','location.id')->where('h.status','active')->where('rt.status','active');
        },'rooms_min_price')->orderBy('name')->get();
    }
}
