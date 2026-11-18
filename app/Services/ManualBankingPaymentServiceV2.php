<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Writer;
use App\Support\RoomTypeV2BookingAudit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Manual funds attestation by an authorized Admin; no bank network, refund or implicit restore. */
final class ManualBankingPaymentServiceV2
{
    public function verify(string $connection,int $bookingId,int $paymentId,int $adminId,array $input): array
    {
        RoomTypeV2Inventory::open($connection);
        $data=Validator::make($input,[
            'reference'=>'required|string|max:100|regex:/^[A-Za-z0-9_-]+$/D',
            'amount'=>['required',function ($k,$v,$fail) { if (!is_string($v)||!preg_match('/^\d{1,10}(\.\d{1,2})?$/D',$v)) $fail('Supply an exact DECIMAL amount as text.'); }],
            'received_at'=>'required|date_format:Y-m-d\TH:i:sP', 'funds_received'=>'required|accepted',
            'evidence_source'=>'required|in:bank_statement','booking_id'=>'prohibited','payment_id'=>'prohibited',
        ])->validate();
        [$whole,$fraction]=array_pad(explode('.',$data['amount']),2,''); $amount=$whole.'.'.str_pad($fraction,2,'0');
        $received=\Carbon\Carbon::parse($data['received_at'])->utc();
        if ($received->isFuture()) throw ValidationException::withMessages(['received_at'=>'Receipt time cannot be in the future.']);
        $reference=strtoupper($data['reference']);
        try {
            return RoomTypeV2Writer::run($connection,$bookingId,function ($s,$b,$lines,$types) use ($paymentId,$adminId,$data,$amount,$received,$reference) {
                $db=$s->db;
                if (!$db->table('customer')->where('id',$adminId)->where('role','admin')->exists()) throw ValidationException::withMessages(['admin'=>'Only an Admin may attest actual received funds.']);
                $p=$db->table('payment')->where('id',$paymentId)->where('booking_id',$b->id)->lockForUpdate()->first();
                if (!$p || $p->payment_method!=='banking') throw ValidationException::withMessages(['payment'=>'This banking Payment does not belong to the Booking.']);
                if ($amount!==$p->amount || $p->amount!==$b->total_price) throw ValidationException::withMessages(['amount'=>'Received amount must match the stored snapshot.']);
                if ($received->lt(\Carbon\Carbon::parse($p->created_at,config('app.timezone'))->utc())) throw ValidationException::withMessages(['received_at'=>'Receipt predates this Payment.']);
                $old=$db->table('manual_payment_verification')->where('payment_id',$p->id)->first();
                if ($old) {
                    if ($old->bank_reference!==$reference || $old->received_amount!==$amount || $old->received_at!==$received->format('Y-m-d H:i:s')) throw ValidationException::withMessages(['reference'=>'Conflicting verification; original audit is immutable.']);
                    return ['duplicate'=>true,'outcome'=>$old->outcome,'booking_status'=>$b->status,'payment_id'=>$p->id];
                }
                if (!in_array($p->payment_status,['pending','failed','expired'],true)) throw ValidationException::withMessages(['payment'=>'Do not overwrite a funded/refunded Payment.']);
                if ($db->table('manual_payment_verification')->where('bank_reference',$reference)->exists()) throw ValidationException::withMessages(['reference'=>'Reference already verified for another Payment.']);
                $late=!\App\Support\RoomTypeV2BankingPolicy::describe($b)['confirmation_allowed'];
                $other=$db->table('payment')->where('booking_id',$b->id)->where('id','!=',$p->id)->where('payment_status','success')->lockForUpdate()->first();
                $outcome=$other?'duplicate_payment_reconciliation':($late?'late_reconciliation':'success');
                $after=$b->status;
                if (!$late && !$other) {
                    RoomTypeV2Writer::commitment($s,$b,$lines,$types,false); RoomTypeV2Writer::duplicate($db,$b,'confirmed');
                    $db->table('booking')->where('id',$b->id)->update(['status'=>'confirmed','confirmed_at'=>now()]); $after='confirmed';
                    RoomTypeV2BookingAudit::record($db,$b->id,$b->status,$after,'admin',$adminId,'banking_funds_verified');
                }
                $db->table('payment')->where('id',$p->id)->update(['payment_status'=>'success','paid_at'=>$received->format('Y-m-d H:i:s')]);
                $db->table('manual_payment_verification')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'verified_by'=>$adminId,
                    'bank_reference'=>$reference,'received_amount'=>$amount,'received_at'=>$received->format('Y-m-d H:i:s'),'verified_at'=>now(),
                    'evidence_source'=>$data['evidence_source'],'outcome'=>$outcome,'booking_status_before'=>$b->status,'booking_status_after'=>$after,
                    'payment_status_before'=>$p->payment_status,'payment_status_after'=>'success']);
                if ($outcome==='success') $db->table('booking_effect')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'event_key'=>'payment-confirmed:'.$p->id]);
                return ['duplicate'=>false,'outcome'=>$outcome,'booking_status'=>$after,'payment_id'=>$p->id];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1]??null)===1062) throw ValidationException::withMessages(['reference'=>'Concurrent duplicate reference; inspect the existing verification.']);
            throw $e;
        }
    }
}
