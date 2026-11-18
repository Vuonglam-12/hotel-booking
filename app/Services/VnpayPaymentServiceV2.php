<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Writer;
use App\Support\VnpayProtocol;
use Illuminate\Validation\ValidationException;

/** Isolated preparation only: both legacy and V2 callbacks use the same lock boundary. */
final class VnpayPaymentServiceV2
{
    private function protocol(): VnpayProtocol { return new VnpayProtocol(config('services.vnpay',[])); }

    public function start(string $connection, int $bookingId, int $customerId, string $ip): array
    {
        if (config('roomtype-v2.final_integration',false)
            && \App\Support\RoomTypeV2PaymentConfiguration::vnpay()['status']!=='CONFIGURED') {
            throw ValidationException::withMessages(['payment'=>'VNPay sandbox chưa được xác minh hoặc chưa sẵn sàng.']);
        }
        RoomTypeV2Inventory::open($connection);
        $protocol=$this->protocol();
        $protocol->assertTarget($ip);
        $result=RoomTypeV2Writer::run($connection,$bookingId,function ($s,$b,$lines,$types) use ($customerId) {
            $db=$s->db;
            if ($b->customer_id !== $customerId || $b->status !== 'pending') throw ValidationException::withMessages(['booking_id'=>'Không thể thanh toán lại Booking này.']);
            if (!$b->expires_at || $b->expires_at <= now()->format('Y-m-d H:i:s')) {
                $db->table('booking')->where('id',$b->id)->update(['status'=>'cancelled','cancelled_by'=>'system','cancelled_at'=>now()]);
                return ['expired'=>true];
            }
            RoomTypeV2Writer::commitment($s,$b,$lines,$types,false);
            $payments=$db->table('payment')->where('booking_id',$b->id)->orderBy('id')->lockForUpdate()->get();
            if (config('roomtype-v2.final_integration',false)
                && !$payments->contains(fn ($payment) => $payment->payment_method==='vnpay')) {
                throw ValidationException::withMessages(['payment'=>'Booking không được tạo với phương thức VNPay.']);
            }
            if ($payments->contains('payment_status','success')) throw ValidationException::withMessages(['payment'=>'Booking đã có thanh toán thành công.']);
            $pending=$payments->where('payment_method','vnpay')->where('payment_status','pending')->values();
            if ($pending->count()>1) throw ValidationException::withMessages(['payment'=>'Nhiều giao dịch pending cần đối soát.']);
            $payment=$pending->first();
            if (!$payment) {
                // Preserve old cash/banking rows. A failed attempt is never overwritten or reused.
                $id=$db->table('payment')->insertGetId(['booking_id'=>$b->id,'amount'=>$b->total_price,'payment_method'=>'vnpay','payment_status'=>'pending',
                    'transaction_id'=>'V2_'.$b->id.'_'.bin2hex(random_bytes(12))]);
                $payment=$db->table('payment')->where('id',$id)->first();
            }
            return ['booking'=>$b,'payment'=>$payment];
        });
        if (!empty($result['expired'])) throw ValidationException::withMessages(['booking_id'=>'Booking đã hết hạn; không gia hạn hoặc khôi phục tự động.']);
        return ['payment_url'=>$protocol->url($result['booking'],$result['payment'],$ip),'txn_ref'=>$result['payment']->transaction_id,
            'amount'=>$result['payment']->amount,'payment_id'=>$result['payment']->id];
    }

    public function receive(string $connection, array $query): array
    {
        $s=RoomTypeV2Inventory::open($connection); $fields=$this->protocol()->verify($query);
        $candidate=$s->db->table('payment')->where('transaction_id',$fields['vnp_TxnRef'])->first();
        if (!$candidate || $candidate->payment_method !== 'vnpay') return $this->recordUnmatched($s->db,$fields);
        return RoomTypeV2Writer::run($connection,$candidate->booking_id,function ($s,$b,$lines,$types) use ($candidate,$fields) {
            $db=$s->db; $p=$db->table('payment')->where('id',$candidate->id)->where('booking_id',$b->id)->lockForUpdate()->first();
            if (!$p || $p->transaction_id !== $fields['vnp_TxnRef']) throw ValidationException::withMessages(['transaction'=>'Callback transaction does not match the stored Payment.']);
            $event=hash('sha256',json_encode($fields,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            $existing=$db->table('payment_gateway_receipt')->where('payment_id',$p->id)->where('event_hash',$event)->first();
            if ($existing) return ['duplicate'=>true,'outcome'=>$existing->outcome,'booking_status'=>$b->status];
            $receivedAmount=RoomTypeV2Inventory::money((int)$fields['vnp_Amount']);
            if ($p->amount !== $b->total_price || RoomTypeV2Inventory::cents($p->amount) !== (int)$fields['vnp_Amount']) {
                $db->table('payment_gateway_receipt')->insert(['payment_id'=>$p->id,'provider'=>'vnpay',
                    'provider_transaction_id'=>$fields['vnp_TransactionNo']!=='0'?$fields['vnp_TransactionNo']:null,
                    'merchant_ref'=>$p->transaction_id,'amount'=>$receivedAmount,'response_code'=>$fields['vnp_ResponseCode'],
                    'transaction_status'=>$fields['vnp_TransactionStatus'],'outcome'=>'amount_mismatch_reconciliation',
                    'payload'=>json_encode($fields,JSON_THROW_ON_ERROR),'event_hash'=>$event]);
                return ['duplicate'=>false,'outcome'=>'amount_mismatch_reconciliation','booking_status'=>$b->status];
            }
            $success=$fields['vnp_ResponseCode']==='00' && $fields['vnp_TransactionStatus']==='00';
            // A signed redelivery may contain additional bank metadata. Identify an already funded
            // transaction by its financial identity, not by the complete optional query payload.
            if ($success && $p->payment_status==='success') {
                $recorded=$db->table('payment_gateway_receipt')->where('payment_id',$p->id)->where('provider','vnpay')
                    ->where('provider_transaction_id',$fields['vnp_TransactionNo'])->where('response_code','00')->where('transaction_status','00')->first();
                if ($recorded) return ['duplicate'=>true,'outcome'=>$recorded->outcome,'booking_status'=>$b->status];
            }
            $late=!RoomTypeV2Inventory::holding($b->status) || ($b->status==='pending' && (!$b->expires_at || $b->expires_at <= now()->format('Y-m-d H:i:s')));
            $outcome=$success ? ($late?'late_reconciliation':'success') : 'failed';
            $otherSuccess=$db->table('payment')->where('booking_id',$b->id)->where('id','!=',$p->id)->where('payment_status','success')->lockForUpdate()->first();
            if ($success && $otherSuccess) $outcome='duplicate_payment_reconciliation';
            if ($p->payment_status === 'success') throw ValidationException::withMessages(['payment'=>'Conflicting callback after a recorded success requires reconciliation.']);
            if ($success && !$late && !$otherSuccess) {
                RoomTypeV2Writer::commitment($s,$b,$lines,$types,false);
                if ($b->status==='pending') {
                    RoomTypeV2Writer::duplicate($db,$b,'confirmed');
                    $db->table('booking')->where('id',$b->id)->update(['status'=>'confirmed','confirmed_at'=>now()]);
                    \App\Support\RoomTypeV2BookingAudit::record($db,$b->id,$b->status,'confirmed','provider',null,'vnpay_ipn_verified');
                }
            }
            // Financial truth is retained even when the released reservation cannot be confirmed.
            $db->table('payment')->where('id',$p->id)->update(['payment_status'=>$success?'success':'failed','paid_at'=>$success?now():null]);
            $db->table('payment_gateway_receipt')->insert(['payment_id'=>$p->id,'provider'=>'vnpay',
                'provider_transaction_id'=>$success?$fields['vnp_TransactionNo']:null,'merchant_ref'=>$p->transaction_id,'amount'=>$receivedAmount,
                'response_code'=>$fields['vnp_ResponseCode'],'transaction_status'=>$fields['vnp_TransactionStatus'],'outcome'=>$outcome,
                'payload'=>json_encode($fields,JSON_THROW_ON_ERROR),'event_hash'=>$event]);
            if ($success && !$late && !$otherSuccess && !$db->table('booking_effect')->where('event_key','payment-confirmed:'.$p->id)->exists()) {
                $db->table('booking_effect')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'event_key'=>'payment-confirmed:'.$p->id]);
            }
            return ['duplicate'=>false,'outcome'=>$outcome,'booking_status'=>$success&&!$late&&!$otherSuccess&&$b->status==='pending'?'confirmed':$b->status];
        });
    }

    private function recordUnmatched($db,array $fields): array
    {
        $event=hash('sha256',json_encode($fields,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $record=function () use ($db,$fields,$event) {
            $existing=$db->table('payment_gateway_unmatched_event')->where('provider','vnpay')->where('event_hash',$event)->lockForUpdate()->first();
            if ($existing) return ['duplicate'=>true,'outcome'=>$existing->outcome,'booking_status'=>null];
            $db->table('payment_gateway_unmatched_event')->insert([
                'provider'=>'vnpay','merchant_ref'=>$fields['vnp_TxnRef'],
                'provider_transaction_id'=>$fields['vnp_TransactionNo']!=='0'?$fields['vnp_TransactionNo']:null,
                'amount'=>RoomTypeV2Inventory::money((int)$fields['vnp_Amount']),
                'response_code'=>$fields['vnp_ResponseCode'],'transaction_status'=>$fields['vnp_TransactionStatus'],
                'outcome'=>'reference_unmatched_reconciliation','payload'=>json_encode($fields,JSON_THROW_ON_ERROR),
                'event_hash'=>$event,
            ]);
            return ['duplicate'=>false,'outcome'=>'reference_unmatched_reconciliation','booking_status'=>null];
        };
        try {
            return $db->transaction($record,3);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1]??null)!==1062) throw $e;
            $existing=$db->table('payment_gateway_unmatched_event')->where('provider','vnpay')->where('event_hash',$event)->first();
            if (!$existing) throw $e;
            return ['duplicate'=>true,'outcome'=>$existing->outcome,'booking_status'=>null];
        }
    }

    /** ReturnURL is read-only. Only the verified IPN handler records payment results. */
    public function result(string $connection, array $query): array
    {
        $s=RoomTypeV2Inventory::open($connection); $fields=$this->protocol()->verify($query);
        $p=$s->db->table('payment')->where('transaction_id',$fields['vnp_TxnRef'])->first();
        if (!$p || $p->payment_method !== 'vnpay' || RoomTypeV2Inventory::cents($p->amount)!==(int)$fields['vnp_Amount']) throw ValidationException::withMessages(['amount'=>'Unknown/mismatched transaction.']);
        $receipt=$s->db->table('payment_gateway_receipt')->where('payment_id',$p->id)->orderByDesc('id')->first();
        return ['payment_id'=>$p->id,'verified'=>true,'recorded'=>$receipt!==null,'outcome'=>$receipt?->outcome ?? 'awaiting_ipn'];
    }
}
