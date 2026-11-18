<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Writer;
use Illuminate\Support\Str;

/** After-commit handoff. SMTP has no exactly-once guarantee; ambiguous sends require review. */
final class BookingEffectServiceV2
{
    public function deliver(string $connection, int $effectId, callable $sender): bool
    {
        $s=RoomTypeV2Inventory::open($connection);
        $candidate=$s->db->table('booking_effect')->where('id',$effectId)->first();
        if (!$candidate) throw new \RuntimeException('Unknown booking effect.');
        $claimed=RoomTypeV2Writer::run($connection,$candidate->booking_id,function ($s,$b) use ($effectId,$candidate) {
            $db=$s->db;
            $payment=$db->table('payment')->where('id',$candidate->payment_id)->lockForUpdate()->first();
            $effect=$db->table('booking_effect')->where('id',$effectId)->lockForUpdate()->first();
            $safe=config('roomtype-v2.delivery_safety',false);
            if ($safe) {
                if (in_array($effect->state,['processing','sending'],true)) {
                    if (!$effect->lease_expires_at || $effect->lease_expires_at<=now()->format('Y-m-d H:i:s')) {
                        $db->table('booking_effect')->where('id',$effectId)->update(['state'=>'uncertain','lease_token'=>null,'lease_expires_at'=>null,
                            'last_error'=>'Worker lease expired; inspect transport before any retry.']);
                        self::audit($db,$effectId,$effect->state,'uncertain','lease_expired');
                    }
                    return null;
                }
                if ($effect->state!=='pending' || ($effect->next_attempt_at && $effect->next_attempt_at>now()->format('Y-m-d H:i:s'))) return null;
                if ($effect->attempts>=self::maxAttempts()) {
                    $db->table('booking_effect')->where('id',$effectId)->update(['state'=>'failed','last_error'=>'Retry limit reached.']);
                    self::audit($db,$effectId,'pending','failed','retry_limit'); return null;
                }
            } elseif ($effect->state==='delivered' || ($effect->state==='sending' && $effect->lease_expires_at>now()->format('Y-m-d H:i:s'))) return null;
            if (!$payment || $effect->payment_id!==$payment->id || $payment->booking_id!==$b->id || $payment->payment_status!=='success') throw new \RuntimeException('Unverified payment cannot emit a confirmation.');
            if (!in_array($b->status,['confirmed','completed'],true)) {
                $db->table('booking_effect')->where('id',$effectId)->update(['state'=>'blocked','last_error'=>'Booking released; reconciliation required before delivery.']);
                if ($safe) self::audit($db,$effectId,$effect->state,'blocked','booking_released');
                return null;
            }
            // Invoice uses stored snapshots only. Booking lock serializes invoice creation for this booking.
            $invoice=$db->table('invoice')->where('booking_id',$b->id)->first();
            if (!$invoice) {
                $invoiceId=$db->table('invoice')->insertGetId(['invoice_no'=>'INV-V2-'.$effect->event_key,'booking_id'=>$b->id,'payment_id'=>$payment->id,
                    'customer_id'=>$b->customer_id,'subtotal'=>$b->total_price,'total'=>$b->total_price]);
                foreach ($db->table('booking_room')->where('booking_id',$b->id)->orderBy('id')->get() as $line) {
                    $price=RoomTypeV2Inventory::cents($line->price_at_booking)*$line->nights;
                    $type=$db->table('room_type')->where('id',$line->room_type_id)->first();
                    $db->table('invoice_item')->insert(['invoice_id'=>$invoiceId,'description'=>$type->name.' x'.$line->quantity.' phong x'.$line->nights.' dem',
                        'quantity'=>$line->quantity,'unit_price'=>RoomTypeV2Inventory::money($price),'amount'=>RoomTypeV2Inventory::money($price*$line->quantity)]);
                }
            } else $invoiceId=$invoice->id;
            if (config('roomtype-v2.final_integration',false) && !$db->table('booking_effect_notification')->where('effect_id',$effectId)->exists()) {
                $notification=$db->table('customer_notifications')->insertGetId(['customer_id'=>$b->customer_id,'type'=>'booking_success',
                    'data'=>json_encode(['title'=>'Đặt phòng đã xác nhận','message'=>'Đặt phòng #'.$b->id.' đã được xác nhận. Trạng thái gửi email được cập nhật riêng.',
                        'booking_id'=>$b->id,'link'=>'/bookings'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                $db->table('booking_effect_notification')->insert(['effect_id'=>$effectId,'notification_id'=>$notification]);
            }
            $lease=(string)Str::uuid();
            $values=['state'=>$safe?'processing':'sending','attempts'=>$effect->attempts+1,'invoice_id'=>$invoiceId,
                'lease_token'=>$lease,'lease_expires_at'=>now()->addMinutes(5),'last_error'=>null];
            if ($safe) { $values['next_attempt_at']=null; $values['transport_started_at']=null; }
            $db->table('booking_effect')->where('id',$effectId)->update($values);
            if ($safe) self::audit($db,$effectId,'pending','processing','claimed');
            return ['lease'=>$lease,'invoice_id'=>$invoiceId,'booking_id'=>$b->id,'event_key'=>$effect->event_key,'attempt'=>$effect->attempts+1];
        });
        if (!$claimed) return false;
        // All Hotel/Type/Booking/Payment locks have been committed before external work.
        try {
            if (config('roomtype-v2.delivery_safety',false)) $s->db->table('booking_effect')->where('id',$effectId)->where('lease_token',$claimed['lease'])->update(['transport_started_at'=>now()]);
            $sender($claimed); // A normal return means the configured transport accepted the message.
            $acked=$s->db->table('booking_effect')->where('id',$effectId)->where('lease_token',$claimed['lease'])->update([
                'state'=>'delivered','delivered_at'=>now(),'lease_token'=>null,'lease_expires_at'=>null]);
            if ($acked && config('roomtype-v2.delivery_safety',false)) self::audit($s->db,$effectId,'processing','delivered','transport_accepted');
            return $acked===1;
        } catch (\Throwable $e) {
            if (config('roomtype-v2.delivery_safety',false)) {
                $preSend=$e instanceof \App\Support\BookingDeliveryPreSendFailure;
                $state=$preSend?($claimed['attempt']>=self::maxAttempts()?'failed':'pending'):'uncertain';
                $backoff=config('roomtype-v2.delivery_backoff_seconds',[60,300,900]);
                $delay=$backoff[min($claimed['attempt']-1,count($backoff)-1)]??900;
                if (!is_int($delay) || $delay<1 || $delay>86400) $delay=900;
                $s->db->table('booking_effect')->where('id',$effectId)->where('lease_token',$claimed['lease'])->update([
                    'state'=>$state,'lease_token'=>null,'lease_expires_at'=>null,'next_attempt_at'=>$state==='pending'?now()->addSeconds($delay):null,
                    'transport_started_at'=>$preSend?null:$s->db->table('booking_effect')->where('id',$effectId)->value('transport_started_at'),
                    'last_error'=>$preSend?'Preparation failed before transport; bounded retry.':'Delivery uncertain; inspect transport before retry.']);
                self::audit($s->db,$effectId,'processing',$state,$preSend?'pre_send_failure':'transport_uncertain');
            } else $s->db->table('booking_effect')->where('id',$effectId)->where('lease_token',$claimed['lease'])->update([
                'state'=>'pending','lease_token'=>null,'lease_expires_at'=>null,'last_error'=>'Sender failed; retry with the same event key.']);
            throw $e;
        }
    }

    private static function maxAttempts(): int
    {
        $n=config('roomtype-v2.delivery_max_attempts',3);
        if (!is_int($n) || $n<1 || $n>5) throw new \RuntimeException('Delivery retry limit must be between 1 and 5.');
        return $n;
    }

    private static function audit($db,int $effect,string $before,string $after,string $action,?int $actor=null,?string $reason=null): void
    {
        $db->table('booking_delivery_audit')->insert(['effect_id'=>$effect,'actor_id'=>$actor,'action'=>$action,
            'state_before'=>$before,'state_after'=>$after,'reason'=>$reason,'created_at'=>now()]);
    }

    public function review(string $connection,int $effectId,int $adminId,array $input): array
    {
        $s=RoomTypeV2Inventory::open($connection);
        if (!config('roomtype-v2.delivery_safety',false)) throw new \RuntimeException('Safe delivery schema required.');
        $data=\Illuminate\Support\Facades\Validator::make($input,[
            'action'=>'required|in:retry,confirm_delivered','reason'=>'required|string|max:500',
            'provider_checked'=>'required|accepted','duplicate_risk_accepted'=>($input['action']??null)==='retry'?'required|accepted':'nullable|boolean',
        ])->validate();
        $candidate=$s->db->table('booking_effect')->where('id',$effectId)->first();
        if (!$candidate) throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        return RoomTypeV2Writer::run($connection,$candidate->booking_id,function ($s,$b) use ($effectId,$adminId,$data) {
            $db=$s->db;
            if (!$db->table('customer')->where('id',$adminId)->where('role','admin')->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['admin'=>'Admin review required.']);
            $e=$db->table('booking_effect')->where('id',$effectId)->lockForUpdate()->first();
            if (!in_array($e->state,['uncertain','failed'],true)) throw \Illuminate\Validation\ValidationException::withMessages(['state'=>'Only uncertain/failed delivery can be reviewed.']);
            $retry=$data['action']==='retry';
            if ($retry && ($e->attempts>=self::maxAttempts() || !in_array($b->status,['confirmed','completed'],true))) throw \Illuminate\Validation\ValidationException::withMessages(['action'=>'Retry limit or Booking state prevents resending.']);
            if (!$retry && !$e->transport_started_at) throw \Illuminate\Validation\ValidationException::withMessages(['action'=>'No transport attempt to reconcile.']);
            $state=$retry?'pending':'delivered';
            $db->table('booking_effect')->where('id',$effectId)->update(['state'=>$state,'next_attempt_at'=>$retry?now():null,'last_error'=>null,
                'delivered_at'=>$retry?null:now(),'lease_token'=>null,'lease_expires_at'=>null]);
            self::audit($db,$effectId,$e->state,$state,'admin_'.$data['action'],$adminId,$data['reason']);
            return ['effect_id'=>$effectId,'state'=>$state,'event_key'=>$e->event_key];
        });
    }
}
