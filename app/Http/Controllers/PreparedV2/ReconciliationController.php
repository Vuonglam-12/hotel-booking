<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index()
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        $manual=$db->table('manual_payment_verification as v')->join('booking as b','b.id','=','v.booking_id')->join('payment as p','p.id','=','v.payment_id')
            ->where(fn($q)=>$q->where('v.outcome','like','%reconciliation%')->orWhere(fn($q)=>$q->whereIn('b.status',['cancelled','expired'])->where('p.payment_status','success')))->orderByDesc('v.id')->limit(100)
            ->get(['v.id','v.booking_id','v.payment_id','v.bank_reference','v.received_amount','v.received_at','v.verified_at','v.outcome','b.status']);
        $gateway=$db->table('payment_gateway_receipt as r')->join('payment as p','p.id','=','r.payment_id')->join('booking as b','b.id','=','p.booking_id')
            ->where(fn($q)=>$q->where('r.outcome','like','%reconciliation%')->orWhere(fn($q)=>$q->whereIn('b.status',['cancelled','expired'])->where('p.payment_status','success')))->orderByDesc('r.id')->limit(100)
            ->get(['r.id','p.booking_id','r.payment_id','r.provider','r.provider_transaction_id','r.merchant_ref','r.amount',
                'r.response_code','r.transaction_status','r.outcome','b.status']);
        $unmatchedGateway=$db->table('payment_gateway_unmatched_event')->where('provider','vnpay')->orderByDesc('id')->limit(100)
            ->get(['id','provider','merchant_ref','provider_transaction_id','amount','response_code','transaction_status','outcome','created_at']);
        $sepay=collect();
        if ($db->getSchemaBuilder()->hasTable('sepay_webhook_event')) {
            $sepay=$db->table('sepay_webhook_event as e')->leftJoin('payment as p','p.id','=','e.payment_id')
                ->leftJoin('booking as b','b.id','=','p.booking_id')->whereNull('e.payment_id')->where('e.outcome','!=','outgoing_ignored')
                ->orderByDesc('e.id')->limit(100)->get(['e.id','p.booking_id','e.payment_id','e.provider_transaction_id',
                    'e.merchant_ref','e.amount','e.outcome','e.received_at','b.status']);
        }
        foreach ([$manual,$gateway] as $rows) foreach ($rows as $row) {
            $row->original_outcome=$row->outcome;
            if (in_array($row->status,['cancelled','expired'],true) && !str_contains($row->outcome,'reconciliation')) $row->outcome='funded_released_reconciliation';
        }
        $delivery=$db->table('booking_effect')->whereIn('state',['uncertain','failed','blocked'])->orderByDesc('id')->limit(100)
            ->get(['id','booking_id','state','attempts','last_error','next_attempt_at','delivered_at']);
        return response()->json(['manual'=>$manual,'gateway'=>$gateway,'unmatched_gateway'=>$unmatchedGateway,'sepay_events'=>$sepay,'delivery'=>$delivery,'limit'=>100,
            'policy'=>'Review only. No automatic refund or inventory restoration.']);
    }
    public function review(Request $r,int $effect,\App\Services\BookingEffectServiceV2 $service)
    {
        return response()->json($service->review('mysql',$effect,(int)$r->user('admin')->id,$r->all()));
    }
    public function history(int $effect)
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        return response()->json($db->table('booking_delivery_audit')->where('effect_id',$effect)->orderBy('id')->get());
    }
    public function page() { RoomTypeV2Inventory::open('mysql'); return view('prepared-v2.reconciliation'); }
}
