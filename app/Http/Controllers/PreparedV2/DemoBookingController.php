<?php

namespace App\Http\Controllers\PreparedV2;

use App\Support\BookingDemo;
use App\Services\DemoBookingPaymentService;
use App\Services\BookingServiceV2;
use Illuminate\Http\Request;

final class DemoBookingController extends BookingController
{
    public function store(Request $r,BookingServiceV2 $service)
    {
        BookingDemo::connection(); $r->validate(['payment_method'=>'nullable|in:banking']);
        $r->merge(['payment_method'=>'banking']); app(DemoBookingPaymentService::class)->expireDue();
        return parent::store($r,$service);
    }
    public function detail(Request $r,int $id,DemoBookingPaymentService $service) { return response()->json($service->detail($id,(int)$r->user('sanctum')->id)); }
    public function adminDetail(Request $r,int $id,DemoBookingPaymentService $service) { return response()->json($service->detail($id,(int)$r->user('admin')->id,true)); }
    public function simulate(Request $r,int $id,DemoBookingPaymentService $service) { $v=$r->validate(['action'=>'required|in:success,failed,cancelled,expired']); return response()->json($service->simulate($id,(int)$r->user('sanctum')->id,$v['action'])); }
    public function adminSimulate(Request $r,int $id,DemoBookingPaymentService $service) { $v=$r->validate(['action'=>'required|in:success,failed,cancelled,expired']); return response()->json($service->simulate($id,(int)$r->user('admin')->id,$v['action'],true)); }
    public function retry(Request $r,int $id,DemoBookingPaymentService $service) { return response()->json($service->retry($id,(int)$r->user('sanctum')->id)); }
    public function page(int $booking) { BookingDemo::connection(); return view('demo.payment',['bookingId'=>$booking]); }
    public function adminPage() { BookingDemo::connection(); return view('demo.transactions'); }
    public function adminPaymentPage(int $id) { BookingDemo::connection(); return view('demo.admin-payment',['bookingId'=>$id]); }
    public function listing(Request $r,DemoBookingPaymentService $service)
    {
        $db=BookingDemo::connection(); $service->expireDue();
        $rows=$db->table('booking as b')->join('hotel as h','h.id','=','b.hotel_id')->whereExists(fn($q)=>$q->selectRaw('1')->from('payment')->whereColumn('payment.booking_id','b.id')->where('transaction_id','like','DEMO\_%'))
            ->select(['b.id','b.status','b.total_price','b.created_at','h.name as hotel'])
            ->selectSub($db->table('payment')->select('payment_status')->whereColumn('booking_id','b.id')->orderByDesc('id')->limit(1),'payment_status')
            ->selectSub($db->table('payment')->select('transaction_id')->whereColumn('booking_id','b.id')->orderByDesc('id')->limit(1),'transaction_id')
            ->orderByDesc('b.id')->paginate(15);
        return response()->json($rows);
    }
}
