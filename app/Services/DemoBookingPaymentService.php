<?php

namespace App\Services;

use App\Support\BookingDemo;
use App\Support\RoomTypeV2Writer;
use App\Support\RoomTypeV2BookingAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** Local simulation adapter. Inventory, locking, invoice and delivery reuse the existing V2 lifecycle. */
final class DemoBookingPaymentService
{
    private function owns(object $booking, ?int $actor, bool $admin, bool $automatic = false): void
    {
        if ($automatic) return; // Only expireDue invokes this private branch, with a checked deadline.
        $db=BookingDemo::connection();
        abort_unless($actor && ($admin ? $db->table('customer')->where('id',$actor)->where('role','admin')->exists()
            : $booking->customer_id===$actor),404);
    }

    public function detail(int $id, int $actor, bool $admin=false): array
    {
        $db=BookingDemo::connection(); $booking=$db->table('booking')->where('id',$id)->first(); abort_unless($booking,404);
        $this->owns($booking,$actor,$admin);
        abort_unless($db->table('payment')->where('booking_id',$id)->where('transaction_id','like','DEMO\_%')->exists(),404);
        $b=app(BookingReadServiceV2::class)->detail($id,$booking->customer_id);
        return ['demo'=>true,'booking'=>$b,'payment'=>$b->payment,
            'transactions'=>$db->table('payment_gateway_receipt')->whereIn('payment_id',$b->payments->pluck('id'))->where('provider','demo')->orderBy('id')->get(),
            'invoice'=>$db->table('invoice')->where('booking_id',$id)->first(),
            'expires_at_utc'=>\Carbon\Carbon::parse($booking->expires_at,'UTC')->toIso8601String(),
            'qr_label'=>'QR minh họa DEMO — không dùng để chuyển tiền'];
    }

    public function simulate(int $id, int $actor, string $action, bool $admin=false): array
    {
        abort_unless(in_array($action,['success','failed','cancelled','expired'],true),422);
        return $this->settle($id,$actor,$admin,$action);
    }

    private function settle(int $id, ?int $actor, bool $admin, string $action, bool $automatic=false): array
    {
        BookingDemo::connection();
        $result=RoomTypeV2Writer::run('mysql',$id,function ($s,$b,$lines,$types) use ($actor,$admin,$action,$automatic) {
            $this->owns($b,$actor,$admin,$automatic); $db=$s->db;
            $p=$db->table('payment')->where('booking_id',$b->id)->orderByDesc('id')->lockForUpdate()->first();
            abort_unless($p && str_starts_with($p->transaction_id,'DEMO_'),404);
            if ($automatic && ($b->status!=='pending'||!$b->expires_at||$b->expires_at>now()->format('Y-m-d H:i:s'))) return ['changed'=>false];
            if ($b->status==='pending' && (!$b->expires_at||$b->expires_at<=now()->format('Y-m-d H:i:s'))) $action='expired';
            $receipts=$db->table('payment_gateway_receipt')->where('payment_id',$p->id)->where('provider','demo')->orderByDesc('id')->get();
            $receipt=$receipts->firstWhere('outcome',$action);
            if ($receipt) {
                abort_unless($receipt->outcome===$action,409,'Giao dịch đã có kết quả. Dùng Thử lại để tạo lượt thanh toán mới.');
                return ['changed'=>false,'duplicate'=>true,'outcome'=>$receipt->outcome,'payment_id'=>$p->id];
            }
            $releaseFailed=$p->payment_status==='failed' && in_array($action,['cancelled','expired'],true);
            abort_unless($b->status==='pending' && ($p->payment_status==='pending'||$releaseFailed),409,'Đặt phòng hoặc thanh toán không còn chờ xử lý.');
            if ($p->amount!==$b->total_price) throw ValidationException::withMessages(['amount'=>'Số tiền không khớp dữ liệu backend.']);
            if ($action==='success') {
                abort_if($db->table('payment')->where('booking_id',$b->id)->where('payment_status','success')->exists(),409);
                RoomTypeV2Writer::commitment($s,$b,$lines,$types,false); RoomTypeV2Writer::duplicate($db,$b,'confirmed');
            }
            $after=$action==='success'?'confirmed':($action==='failed'?'pending':'cancelled');
            $values=['status'=>$after];
            if ($after==='confirmed') $values['confirmed_at']=now();
            if ($after==='cancelled') $values+=['cancelled_at'=>now(),'cancelled_by'=>$action==='expired'?'system':'customer'];
            $db->table('booking')->where('id',$b->id)->update($values);
            RoomTypeV2BookingAudit::record($db,$b->id,$b->status,$after,$automatic?'system':($admin?'admin':'customer'),$actor,'demo_'.$action);
            $db->table('payment')->where('id',$p->id)->update(['payment_status'=>$action==='success'?'success':($action==='expired'?'expired':'failed'), 'paid_at'=>$action==='success'?now():null]);
            $db->table('payment_gateway_receipt')->insert(['payment_id'=>$p->id,'provider'=>'demo','provider_transaction_id'=>'DEMO_EVENT_'.$p->id.'_'.$action,
                'merchant_ref'=>$p->transaction_id,'amount'=>$p->amount,'response_code'=>['success'=>'00','failed'=>'99','cancelled'=>'24','expired'=>'15'][$action],
                'transaction_status'=>$action==='success'?'00':'02','outcome'=>$action,'payload'=>json_encode(['demo'=>true,'action'=>$action,'actor'=>$actor]),
                'event_hash'=>hash('sha256','demo:'.$p->id.':'.$action)]);
            if ($action==='success') $db->table('booking_effect')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'event_key'=>'payment-confirmed:'.$p->id]);
            return ['changed'=>true,'duplicate'=>false,'outcome'=>$action,'payment_id'=>$p->id];
        });
        if (($result['outcome']??null)==='success') {
            $db=BookingDemo::connection(); $effect=$db->table('booking_effect')->where('payment_id',$result['payment_id'])->first();
            // Array transport captures the actual confirmation and PDF; never SMTP or a real recipient.
            app(BookingEffectServiceV2::class)->deliver('mysql',$effect->id,function ($claim) {
                $booking=\App\Models\Booking::with('customer')->findOrFail($claim['booking_id']);
                Mail::mailer('array')->to('demo-booking-'.$booking->id.'@example.test')->send(new \App\Mail\BookingConfirmedV2($booking));
            });
        }
        return $result+['demo'=>true];
    }

    public function retry(int $id,int $actor): array
    {
        BookingDemo::connection();
        return RoomTypeV2Writer::run('mysql',$id,function ($s,$b) use ($actor) {
            $this->owns($b,$actor,false); $db=$s->db;
            abort_unless($b->status==='pending' && $b->expires_at>now()->format('Y-m-d H:i:s'),409,'Đơn đã hủy hoặc hết thời hạn giữ phòng.');
            $all=$db->table('payment')->where('booking_id',$b->id)->orderByDesc('id')->lockForUpdate()->get();
            abort_unless($all->isNotEmpty() && str_starts_with($all->first()->transaction_id,'DEMO_'),404);
            abort_if($all->contains('payment_status','success'),409);
            if ($pending=$all->firstWhere('payment_status','pending')) return ['payment_id'=>$pending->id,'duplicate'=>true];
            $id=$db->table('payment')->insertGetId(['booking_id'=>$b->id,'amount'=>$b->total_price,'payment_method'=>'banking','payment_status'=>'pending',
                'transaction_id'=>'DEMO_'.$b->id.'_'.bin2hex(random_bytes(12)),'created_at'=>now()]);
            return ['payment_id'=>$id,'duplicate'=>false];
        });
    }

    public function expireDue(): void
    {
        $db=BookingDemo::connection();
        $ids=$db->table('booking')->where('status','pending')->where('expires_at','<=',now())->whereExists(function ($q) {
            $q->selectRaw('1')->from('payment')->whereColumn('payment.booking_id','booking.id')->where('transaction_id','like','DEMO\_%');
        })->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) $this->settle($id,null,false,'expired',true);
    }
}
