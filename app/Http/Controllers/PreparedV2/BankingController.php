<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Services\ManualBankingPaymentServiceV2;
use App\Services\BookingReadServiceV2;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class BankingController extends Controller
{
    public function verify(Request $r,int $booking,int $payment,ManualBankingPaymentServiceV2 $service)
    {
        return response()->json($service->verify('mysql',$booking,$payment,(int)$r->user('admin')->id,$r->all()));
    }
    public function detail(Request $r,int $booking,BookingReadServiceV2 $reader)
    {
        $b=$reader->detail($booking,(int)$r->user('sanctum')->id);
        $instructions=\App\Support\RoomTypeV2PaymentConfiguration::banking();
        $gatewayReady=\App\Support\RoomTypeV2PaymentConfiguration::vnpay()['status']==='CONFIGURED';
        return response()->json(['booking'=>$b,'payment'=>$b->payment,'payments'=>$b->payments,'banking_instructions'=>$instructions,
            'banking_instructions_ready'=>$instructions!==null,'verification_required'=>true,'hold_minutes'=>15,'vnpay_ready'=>$gatewayReady,'banking_policy'=>$b->banking_policy]);
    }
    public function bookings(Request $r,BookingReadServiceV2 $reader) { return response()->json($reader->listing($r->all())); }
    public function audit(int $booking)
    {
        $db=RoomTypeV2Inventory::open('mysql')->db;
        return response()->json(['verifications'=>$db->table('manual_payment_verification')->where('booking_id',$booking)->get(),
            'states'=>$db->table('booking_state_audit')->where('booking_id',$booking)->orderBy('id')->get(),
            'effects'=>$db->table('booking_effect')->where('booking_id',$booking)->get(['id','state','attempts','last_error','delivered_at'])]);
    }
    public function adminPage() { RoomTypeV2Inventory::open('mysql'); return view('prepared-v2.admin-bookings'); }
    public function customerPage(int $booking) { RoomTypeV2Inventory::open('mysql'); return view('prepared-v2.booking-payment',['bookingId'=>$booking]); }
    public function customerDetail(int $id,BookingReadServiceV2 $reader)
    {
        RoomTypeV2Inventory::open('mysql');
        $customer=\App\Models\Customer::withCount(['bookings','reviews'])->withSum(['payments as total_spent'=>fn($q)=>$q->where('payment_status','success')],'amount')->findOrFail($id);
        return response()->json(['customer'=>$customer,'bookings'=>$reader->listing(['per_page'=>10],$id)->items()]);
    }
}
