<?php
namespace App\Http\Controllers;

use App\Services\LocalBookingDemoService;
use Illuminate\Http\Request;

final class LocalBookingDemoController extends Controller
{
    public function detail(Request $r,int $id,LocalBookingDemoService $s) { return response()->json($s->detail($id,(int)$r->user('sanctum')->id)); }
    public function adminDetail(Request $r,int $id,LocalBookingDemoService $s) { return response()->json($s->detail($id,(int)$r->user('admin')->id,true)); }
    public function simulate(Request $r,int $id,LocalBookingDemoService $s) { $v=$r->validate(['action'=>'required|in:success,failed,cancelled,expired','payment_id'=>'required|integer|min:1']);return response()->json($s->simulate($id,(int)$r->user('sanctum')->id,$v['action'],(int)$v['payment_id'])); }
    public function adminSimulate(Request $r,int $id,LocalBookingDemoService $s)
    {
        $v=$r->validate(['action'=>'required|in:success,failed,cancelled,expired','payment_id'=>'required|integer|min:1',
            'received_amount'=>['nullable','regex:/^\d{1,10}(?:\.\d{1,2})?$/D'], 'event_key'=>'nullable|string|min:8|max:100']);
        return response()->json($s->simulate($id,(int)$r->user('admin')->id,$v['action'],(int)$v['payment_id'],true,
            isset($v['received_amount'])?(string)$v['received_amount']:null,$v['event_key']??null));
    }
    public function retry(Request $r,int $id,LocalBookingDemoService $s) { return response()->json($s->retry($id,(int)$r->user('sanctum')->id)); }
    public function adminRetry(Request $r,int $id,LocalBookingDemoService $s) { return response()->json($s->retry($id,(int)$r->user('admin')->id,true)); }
    public function listing(Request $r,LocalBookingDemoService $s) { $v=$r->validate(['page'=>'sometimes|integer|min:1']);return response()->json($s->listing((int)($v['page']??1))); }
    public function page(int $booking) { return view('demo.payment',['bookingId'=>$booking,'localPayment'=>true]); }
    public function adminPage(int $id) { return view('demo.admin-payment',['bookingId'=>$id,'localPayment'=>true]); }
}
