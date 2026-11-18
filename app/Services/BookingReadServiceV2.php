<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\RoomTypeV2Inventory;

class BookingReadServiceV2
{
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        app(HotelCatalogServiceV2::class)->guard();
        return Booking::with(['hotel.images','customer','bookingRooms.roomType','invoice:id,booking_id,invoice_no,issued_at,total']);
    }
    public function decorate(Booking $b): Booking
    {
        return $this->decorateMany(collect([$b]))->first();
    }
    private function decorateMany(\Illuminate\Support\Collection $bookings): \Illuminate\Support\Collection
    {
        if ($bookings->isEmpty()) return $bookings;
        $db=RoomTypeV2Inventory::open('mysql')->db;
        $paymentGroups=$db->table('payment')->whereIn('booking_id',$bookings->pluck('id'))->orderByDesc('id')->get()->groupBy('booking_id');
        $paymentIds=$paymentGroups->flatten(1)->pluck('id');
        $manuals=$db->table('manual_payment_verification')->whereIn('payment_id',$paymentIds)->orderBy('id')->get()->keyBy('payment_id');
        $receipts=$db->table('payment_gateway_receipt')->whereIn('id',function ($q) use ($paymentIds) {
            $q->from('payment_gateway_receipt')->selectRaw('MAX(id)')->whereIn('payment_id',$paymentIds)->groupBy('payment_id');
        })->get()->keyBy('payment_id');
        $effectGroups=$db->table('booking_effect')->whereIn('booking_id',$bookings->pluck('id'))->orderBy('id')
            ->get(['id','booking_id','invoice_id','state','attempts','last_error','delivered_at'])->groupBy('booking_id');
        return $bookings->map(function (Booking $b) use ($paymentGroups,$manuals,$receipts,$effectGroups) {
        $payments=$paymentGroups->get($b->id,collect())->map(function ($p) use ($manuals,$receipts,$b) {
            $manual=$manuals->get($p->id);
            $receipt=$receipts->get($p->id);
            $outcome=$manual?->outcome ?? $receipt?->outcome;
            $releasedFunds=$p->payment_status==='success' && in_array($b->status,['cancelled','expired'],true);
            $p->reconciliation=$releasedFunds || ($outcome!==null && str_contains($outcome,'reconciliation')); $p->verification_outcome=$outcome;
            $p->reconciliation_reason=$releasedFunds?'funded_released_reconciliation':($p->reconciliation?$outcome:null);
            $p->display_status=$p->reconciliation?'reconciliation':$p->payment_status;
            return $p;
        });
        $selected=$payments->firstWhere('payment_status','success')??$payments->first();
        $current=null; if ($selected) { $current=new \App\Models\Payment(); $current->setRawAttributes((array)$selected); }
        $b->setRelation('payment',$current);
        $b->setRelation('payments',$payments); $b->payment_method=$selected?->payment_method;
        $b->banking_policy=\App\Support\RoomTypeV2BankingPolicy::describe($b,$selected?->reconciliation_reason??$selected?->verification_outcome);
        $b->hotel?->append('cover_image_url');
        $b->room_quantity=(int)$b->bookingRooms->sum('quantity');
        $b->room_type_summary=$b->bookingRooms->map(fn($line)=>($line->roomType?->name??'Loại phòng').' × '.$line->quantity.' phòng')->join(', ');
        $b->room_numbers=''; // Compatibility alias only; never reads the Room relation.
        $b->summary=['hotel_id'=>$b->hotel_id,'hotel'=>$b->hotel?->name,'status'=>$b->status,'payment_method'=>$b->payment_method,
            'total'=>$b->total_price,'lines'=>$b->bookingRooms->map(fn($l)=>['room_type_id'=>$l->room_type_id,'name'=>$l->roomType?->name,
                'quantity'=>$l->quantity,'nights'=>$l->nights,'unit_price'=>$l->price_at_booking])->all()];
        $b->effect_delivery=$effectGroups->get($b->id,collect())->map(function ($e) {
            unset($e->booking_id); // Preserve the existing response shape.
            $e->display_state=$e->state==='pending'&&$e->last_error?'failed':$e->state; return $e;
        });
        return $b;
        });
    }
    public function detail(int $id,int $customer): Booking { return $this->decorate($this->query()->where('customer_id',$customer)->findOrFail($id)); }
    public function listing(array $filters,?int $customer=null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q=$this->query(); if ($customer!==null) $q->where('customer_id',$customer);
        foreach (['hotel_id','customer_id','status'] as $field) if (!empty($filters[$field])) $q->where($field,$filters[$field]);
        if (!empty($filters['date_from'])) $q->where('check_in','>=',$filters['date_from']);
        if (!empty($filters['date_to'])) $q->where('check_out','<=',$filters['date_to']);
        if (!empty($filters['search'])) $q->where(fn($x)=>$x->where('id','like','%'.$filters['search'].'%')->orWhereHas('hotel',fn($h)=>$h->where('name','like','%'.$filters['search'].'%'))
            ->orWhereHas('customer',fn($c)=>$c->where('name','like','%'.$filters['search'].'%')->orWhere('email','like','%'.$filters['search'].'%')));
        $p=$q->orderByDesc('created_at')->paginate(min(100,max(1,(int)($filters['per_page']??20))));
        $p->setCollection($this->decorateMany($p->getCollection())); return $p;
    }
}
