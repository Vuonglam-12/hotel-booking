<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Services\BookingLifecycleServiceV2;
use App\Services\VnpayPaymentServiceV2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Loaded only by the isolated route overlay. Production PaymentController remains untouched. */
class PaymentController extends Controller
{
    public function createPayment(Request $request, VnpayPaymentServiceV2 $service)
    {
        $data=$request->validate(['booking_id'=>'required|integer|min:1']);
        return response()->json($service->start('mysql',(int)$data['booking_id'],(int)$request->user('sanctum')->id,$request->ip()));
    }
    public function retry(Request $request, VnpayPaymentServiceV2 $service) { return $this->createPayment($request,$service); }
    public function createManual(Request $request, BookingLifecycleServiceV2 $service)
    {
        $data=$request->validate(['booking_id'=>'required|integer|min:1','payment_method'=>config('roomtype-v2.final_integration',false)?'required|in:banking':'required|in:cash,banking']);
        return response()->json(['booking'=>$service->manualPayment('mysql',(int)$data['booking_id'],(int)$request->user('sanctum')->id,$data['payment_method'])]);
    }
    public function vnpayReturn(Request $request, VnpayPaymentServiceV2 $service)
    {
        return response()->json($service->result('mysql',$request->query()));
    }
    public function htmlResult(Request $request, VnpayPaymentServiceV2 $service)
    {
        try {
            $result=$service->result('mysql',$request->query());
            $db=\App\Support\RoomTypeV2Inventory::open('mysql')->db;
            $payment=$db->table('payment')->where('id',$result['payment_id'])->first();
            $booking=\App\Models\Booking::with('hotel')->findOrFail($payment->booking_id);
            $effect=$db->table('booking_effect')->where('payment_id',$payment->id)->first();
            $emailSent=$effect && $effect->state==='delivered' && $effect->delivered_at;
            $status=$result['outcome'];
            if ($status==='success' && !\App\Support\RoomTypeV2Inventory::holding($booking->status)) $status='late_reconciliation';
            return view('prepared-v2.payment-result',compact('status','emailSent','booking'));
        } catch (ValidationException $e) {
            return response()->view('prepared-v2.payment-result',['status'=>'invalid','emailSent'=>false,'booking'=>null],422);
        }
    }
    public function ipn(Request $request, VnpayPaymentServiceV2 $service)
    {
        try {
            $result=$service->receive('mysql',$request->query());
            $code=$result['duplicate']?'02':($result['outcome']==='amount_mismatch_reconciliation'?'04':'00');
            $message=match($result['outcome']) {
                'amount_mismatch_reconciliation'=>'Amount mismatch; recorded for reconciliation',
                'reference_unmatched_reconciliation'=>'Transaction reference not matched; recorded for reconciliation',
                default=>'Recorded',
            };
            return response()->json(['RspCode'=>$code,'Message'=>$message,'outcome'=>$result['outcome']]);
        } catch (ValidationException $e) {
            $errors=$e->errors(); $code=isset($errors['signature'])?'97':(isset($errors['amount'])?'04':(isset($errors['transaction'])?'01':'99'));
            return response()->json(['RspCode'=>$code,'Message'=>'Callback refused']);
        } catch (\Throwable $e) {
            Log::error('VNPay IPN processing failed; transaction was not committed.', ['exception' => $e]);
            return response()->json(['RspCode'=>'99','Message'=>'Transaction not committed; retry required']);
        }
    }
}
