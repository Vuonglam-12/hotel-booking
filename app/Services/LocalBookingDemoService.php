<?php

namespace App\Services;

use App\Http\Controllers\InvoiceController;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\LocalBookingDemo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Adapter for the existing hotel-scoped catalog and the existing DEMO payment screens. */
final class LocalBookingDemoService
{
    private function epoch(Connection $db): int { return (int)$db->selectOne('SELECT UNIX_TIMESTAMP() n')->n; }
    private function money(string $v): int
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $v)) throw ValidationException::withMessages(['price'=>'Giá phòng không hợp lệ.']);
        [$whole,$fraction]=array_pad(explode('.', $v),2,'');return (int)$whole*100+(int)str_pad($fraction,2,'0');
    }
    private function decimal(int $v): string { return intdiv($v,100).'.'.str_pad((string)($v%100),2,'0',STR_PAD_LEFT); }

    public function create(int $customer, array $input): array
    {
        $db=LocalBookingDemo::connection();
        $data=Validator::make($input,[
            'hotel_id'=>'required|integer|min:1','room_type_id'=>'required|integer|min:1','room_id'=>'nullable|integer|min:1',
            'quantity'=>'required|integer|between:1,10','num_guests'=>'required|integer|between:1,100',
            'check_in'=>'required|date_format:Y-m-d|after_or_equal:'.CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString(),
            'check_out'=>'required|date_format:Y-m-d|after:check_in','special_request'=>'nullable|string|max:500',
            'request_key'=>['required','string','min:16','max:64','regex:/^[a-zA-Z0-9_-]+$/D'],
            'payment_method'=>'sometimes|in:demo',
            'quote_hash'=>'sometimes|nullable|string|size:64',
        ])->validate();
        foreach(['hotel_id','room_type_id','quantity','num_guests'] as $key)$data[$key]=(int)$data[$key];
        $data['room_id']=isset($data['room_id'])?(int)$data['room_id']:null;
        $requestHash=hash('sha256',json_encode(\Illuminate\Support\Arr::except($data,['request_key','payment_method','quote_hash']),JSON_THROW_ON_ERROR));
        return $db->transaction(function()use($db,$customer,$data,$requestHash){
            // All creations for one account serialize, including requests to different hotels.
            abort_unless($db->table('customer')->where('id',$customer)->lockForUpdate()->first(),401);
            $hotel=$db->table('hotel')->where('id',$data['hotel_id'])->lockForUpdate()->first();
            abort_unless($hotel&&$hotel->status==='active',422,'Khách sạn hiện không nhận đặt phòng.');
            $prior=$db->table('booking_demo')->where('customer_id',$customer)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            if($prior){abort_unless(hash_equals($prior->request_hash,$requestHash),409,'Mã yêu cầu đã dùng cho một lựa chọn khác.');return ['booking_id'=>(int)$prior->booking_id,'duplicate'=>true,'demo'=>true];}
            $this->expireHotel($db,$hotel->id);
            $this->assertHoldingLimit($db,$customer,$data['quantity']);
            $type=app(HotelRoomTypeService::class)->effective($hotel->id,$data['room_type_id']);
            abort_if(in_array($type->status,['inactive','archived'],true),422,'Loại phòng đã tạm ẩn.');
            $rooms=$db->table('room')->where('hotel_id',$hotel->id)->where('room_type_id',$data['room_type_id'])->lockForUpdate()->get();
            abort_if($rooms->isEmpty(),422,'Loại phòng không thuộc khách sạn đã chọn.');
            if($data['room_id']!==null){abort_unless($data['quantity']===1&&$rooms->contains(fn($r)=>(int)$r->id===$data['room_id']),422,'Phòng cụ thể không hợp lệ.');}
            app(RoomAvailabilityService::class)->validateCapacity($hotel->id,$type->id,$data['quantity'],$data['num_guests'],$data['room_id']);
            $a=app(RoomAvailabilityService::class)->forTypeWithRooms($hotel->id,$type->id,$data['check_in'],$data['check_out']);
            abort_if($a['availability']['available_rooms']<$data['quantity']||($data['room_id']!==null&&!($a['rooms'][$data['room_id']]['bookable']??false)),422,'Không đủ phòng trống trong toàn bộ kỳ lưu trú.');
            abort_if($db->table('booking')->where('customer_id',$customer)->where('hotel_id',$hotel->id)->where('check_in',$data['check_in'])->where('check_out',$data['check_out'])->whereIn('status',['pending','confirmed'])->exists(),409,'Bạn đã có đơn đang giữ phòng cho khách sạn và ngày này. Hãy mở Lịch sử đặt phòng để tiếp tục.');
            $nights=(int)CarbonImmutable::parse($data['check_in'])->diffInDays(CarbonImmutable::parse($data['check_out']));
            abort_unless($nights>=1&&$nights<=365,422,'Kỳ lưu trú từ 1 đến 365 đêm.');
            $pricing=app(RoomPricingService::class);
            $quote=$pricing->quote($hotel->id,$type->id,$data['check_in'],$data['check_out'],$data['quantity']);
            abort_if(!empty($data['quote_hash'])&&!hash_equals($quote['quote_hash'],$data['quote_hash']),409,'Giá phòng đã thay đổi. Vui lòng xem lại giá từng đêm trước khi đặt.');
            $total=$quote['total_price'];$now=$this->epoch($db);$minutes=max(1,(int)config('booking-demo.hold_minutes',10));
            $id=$db->table('booking')->insertGetId(['customer_id'=>$customer,'hotel_id'=>$hotel->id,'check_in'=>$data['check_in'],'check_out'=>$data['check_out'],'num_guests'=>$data['num_guests'],'total_price'=>$total,'status'=>'pending','expires_at'=>DB::raw('DATE_ADD(CURRENT_TIMESTAMP, INTERVAL '.$minutes.' MINUTE)'),'created_at'=>DB::raw('CURRENT_TIMESTAMP'),'special_request'=>$data['special_request']??null]);
            $lineId=$db->table('booking_room')->insertGetId(['booking_id'=>$id,'room_type_id'=>$type->id,'room_id'=>$data['room_id'],'quantity'=>$data['quantity'],'nights'=>$nights,'price_at_booking'=>$quote['average_nightly_price']]);
            $pricing->saveSnapshot($lineId,$quote);
            $db->table('booking_demo')->insert(['booking_id'=>$id,'customer_id'=>$customer,'request_key'=>$data['request_key'],'request_hash'=>$requestHash,'room_name'=>$type->name,'expires_at_epoch'=>$now+$minutes*60,'created_at_epoch'=>$now]);
            $this->newPayment($db,$id,$total);return ['booking_id'=>$id,'duplicate'=>false,'demo'=>true];
        },3);
    }

    private function assertHoldingLimit(Connection $db,int $customer,int $quantity): void
    {
        $now=$this->epoch($db);
        // Locking reads see committed writes even under InnoDB REPEATABLE READ.
        $pending=$db->table('booking as b')->leftJoin('booking_demo as d','d.booking_id','=','b.id')
            ->where('b.customer_id',$customer)->where('b.status','pending')
            ->where(function($q)use($now){$q->where(fn($q)=>$q->whereNotNull('d.booking_id')->where('d.expires_at_epoch','>',$now))
                ->orWhere(fn($q)=>$q->whereNull('d.booking_id')->where(fn($q)=>$q->whereNull('b.expires_at')->orWhereRaw('b.expires_at > CURRENT_TIMESTAMP')));})
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('payment as p')->whereColumn('p.booking_id','b.id')->where('p.payment_status','success'))
            ->orderBy('b.id')->lockForUpdate()->get(['b.id']);
        $maxOrders=max(1,(int)config('booking-demo.max_pending_bookings',3));
        $maxRooms=max(1,(int)config('booking-demo.max_pending_rooms',10));
        abort_if($pending->count()>=$maxOrders,422,'Mỗi tài khoản chỉ được giữ tối đa '.$maxOrders.' đơn chờ thanh toán.');
        $rooms=$pending->isEmpty()?0:$db->table('booking_room')->whereIn('booking_id',$pending->pluck('id'))->lockForUpdate()->get(['quantity'])->sum('quantity');
        abort_if($rooms+$quantity>$maxRooms,422,'Tổng số phòng trong các đơn chờ thanh toán không được vượt '.$maxRooms.' phòng.');
    }

    private function newPayment(Connection $db,int $id,string $amount): int
    {
        return $db->table('payment')->insertGetId(['booking_id'=>$id,'amount'=>$amount,'payment_method'=>'demo','payment_status'=>'pending','transaction_id'=>'DEMO_LOCAL_'.$id.'_'.Str::random(20),'created_at'=>DB::raw('CURRENT_TIMESTAMP')]);
    }

    private function locked(int $id,?int $actor,bool $admin,callable $work): mixed
    {
        $db=LocalBookingDemo::connection();$candidate=$db->table('booking')->where('id',$id)->first();
        abort_unless($candidate&&($admin||($actor!==null&&(int)$candidate->customer_id===$actor)),404);
        return $db->transaction(function()use($db,$candidate,$id,$work){
            $db->table('hotel')->where('id',$candidate->hotel_id)->lockForUpdate()->sole();
            $b=$db->table('booking')->where('id',$id)->lockForUpdate()->sole();$m=$db->table('booking_demo')->where('booking_id',$id)->lockForUpdate()->first();
            abort_unless($m,404);$p=$db->table('payment')->where('booking_id',$id)->orderByDesc('id')->lockForUpdate()->first();
            abort_unless($p&&$p->payment_method==='demo'&&str_starts_with($p->transaction_id,'DEMO_LOCAL_'),409,'Đơn này không hỗ trợ công cụ xử lý thanh toán hiện tại.');
            return $work($db,$b,$m,$p);
        },3);
    }

    private function expireHotel(Connection $db,int $hotel): void
    {
        $ids=$db->table('booking_demo as d')->join('booking as b','b.id','=','d.booking_id')->where('b.hotel_id',$hotel)->where('b.status','pending')->where('d.expires_at_epoch','<=',$this->epoch($db))->pluck('b.id');
        foreach($ids as $id)$this->locked((int)$id,null,true,fn($db,$b,$m,$p)=>$this->settle($db,$b,$m,$p,'expired',null,'system'));
    }

    public function simulate(int $id,int $actor,string $action,int $payment,bool $admin=false,?string $receivedAmount=null,?string $notification=null): array
    {
        abort_unless($admin,403,'Chỉ Admin được sử dụng công cụ xử lý thanh toán.');
        abort_unless(in_array($action,['success','failed','cancelled','expired'],true),422);
        $result=$this->locked($id,$actor,$admin,function($db,$b,$m,$p)use($actor,$action,$payment,$admin,$receivedAmount,$notification){
            abort_unless((int)$p->id===$payment,409,'Lượt thanh toán đã thay đổi. Hãy cập nhật trạng thái trước.');
            $noticeId=null;
            if($action==='success'){
                $amount=$this->decimal($this->money($receivedAmount??$p->amount));
                $noticeKey=hash('sha256',json_encode([$p->id,$notification!==null?['id',$notification]:['simulation',$amount]],JSON_THROW_ON_ERROR));
                $notice=$db->table('booking_demo_payment_notice')->where('event_key',$noticeKey)->lockForUpdate()->first();
                if($notice){
                    abort_unless($notice->received_amount===$amount,409,'Thông báo thanh toán lặp có số tiền khác với lần đã ghi nhận.');
                    $result=json_decode($notice->result,true,512,JSON_THROW_ON_ERROR);
                    return array_replace($result,['duplicate'=>true]);
                }
                $noticeId=$db->table('booking_demo_payment_notice')->insertGetId(['payment_id'=>$p->id,'event_key'=>$noticeKey,'received_amount'=>$amount]);
            }
            $result=$this->settle($db,$b,$m,$p,$action,$actor,$admin?'admin':'customer',$receivedAmount,$notification);
            if($noticeId)$db->table('booking_demo_payment_notice')->where('id',$noticeId)->update(['result'=>json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        });
        if ($result['outcome']==='success' && config('booking-demo.confirmation_mail_enabled')===true) {
            LocalBookingDemo::connection()->afterCommit(fn()=>app(LocalBookingConfirmationMailService::class)->deliver($id));
        }
        return $result;
    }

    private function settle(Connection $db,object $b,object $m,object $p,string $action,?int $actor,string $kind,?string $receivedAmount=null,?string $notification=null): array
    {
        $now=$this->epoch($db);
        if($b->status==='pending'&&(int)$m->expires_at_epoch<=$now){
            if($action==='success'){
                $this->settle($db,$b,$m,$p,'expired',null,'system');
                $b=$db->table('booking')->find($b->id);$p=$db->table('payment')->find($p->id);
            }else $action='expired';
        }
        if($action==='success'){
            $amount=$this->decimal($this->money($receivedAmount??$p->amount));
            $reconciliation=app(LocalBookingReconciliationService::class);
            if($this->money($p->amount)!==$this->money($b->total_price)||$this->money($amount)!==$this->money($b->total_price)){
                return $reconciliation->record($db,$b,$p,'amount_mismatch',$amount,$notification);
            }
            if(in_array($b->status,['cancelled','expired'],true)){
                $result=$reconciliation->record($db,$b,$p,'late_payment',$amount,$notification);
                if($result['duplicate'])return $result;
                $db->table('payment')->where('id',$p->id)->update(['payment_status'=>'success','paid_at'=>DB::raw('CURRENT_TIMESTAMP')]);
                if(!$db->table('booking_demo_event')->where('payment_id',$p->id)->where('outcome','late_success')->exists())
                    $db->table('booking_demo_event')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'outcome'=>'late_success','actor_kind'=>$kind,'actor_id'=>$actor,'created_at_epoch'=>$now]);
                return $result;
            }
        }
        if($db->table('booking_demo_event')->where('payment_id',$p->id)->where('outcome',$action)->exists())return ['demo'=>true,'duplicate'=>true,'outcome'=>$action];
        abort_unless($b->status==='pending'&&($p->payment_status==='pending'||($p->payment_status==='failed'&&in_array($action,['cancelled','expired'],true))),409,'Giao dịch đã kết thúc; không thể thay đổi kết quả.');
        if($action==='success'){
            abort_unless($db->table('hotel')->where('id',$b->hotel_id)->value('status')==='active',422,'Khách sạn đã dừng nhận phòng.');
            $line=$db->table('booking_room')->where('booking_id',$b->id)->sole();
            $db->table('room')->where('hotel_id',$b->hotel_id)->where('room_type_id',$line->room_type_id)->lockForUpdate()->get();
            app(RoomAvailabilityService::class)->validateCapacity($b->hotel_id,$line->room_type_id,$line->quantity,$b->num_guests,$line->room_id);
            $a=app(RoomAvailabilityService::class)->forTypeWithRooms($b->hotel_id,$line->room_type_id,$b->check_in,$b->check_out,$b->id);
            abort_if($a['availability']['available_rooms']<$line->quantity||($line->room_id!==null&&!($a['rooms'][$line->room_id]['bookable']??false)),422,'Phòng không còn đủ khả dụng. Chưa ghi nhận thành công.');
            abort_if($db->table('payment')->where('booking_id',$b->id)->where('payment_status','success')->exists(),409);
        }
        $status=match($action){'success'=>'confirmed','cancelled','expired'=>'cancelled',default=>'pending'};
        $values=['status'=>$status];if($action==='success')$values['confirmed_at']=DB::raw('CURRENT_TIMESTAMP');
        if(in_array($action,['cancelled','expired'],true))$values+=['cancelled_at'=>DB::raw('CURRENT_TIMESTAMP'),'cancelled_by'=>$action==='expired'?'system':$kind];
        if($action==='expired'){$values['expires_at']=DB::raw('CURRENT_TIMESTAMP');$db->table('booking_demo')->where('booking_id',$b->id)->update(['expires_at_epoch'=>min($now,(int)$m->expires_at_epoch)]);}
        $db->table('booking')->where('id',$b->id)->update($values);
        $db->table('payment')->where('id',$p->id)->update(['payment_status'=>match($action){'success'=>'success','expired'=>'expired',default=>'failed'},'paid_at'=>$action==='success'?DB::raw('CURRENT_TIMESTAMP'):null]);
        $db->table('booking_demo_event')->insert(['booking_id'=>$b->id,'payment_id'=>$p->id,'outcome'=>$action,'actor_kind'=>$kind,'actor_id'=>$actor,'created_at_epoch'=>$now]);
        if($action==='success'){
            InvoiceController::createFromPayment(Booking::findOrFail($b->id),Payment::findOrFail($p->id),$m->room_name);
            $db->table('customer_notifications')->insert(['customer_id'=>$b->customer_id,'type'=>'payment_success','data'=>json_encode(['title'=>'Thanh toán thành công','message'=>'Đơn #'.$b->id.' đã được xác nhận. Bạn có thể tải hóa đơn trong lịch sử đặt phòng.','booking_id'=>$b->id,'link'=>'/bookings','payment_mode'=>'simulation'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>DB::raw('CURRENT_TIMESTAMP'),'updated_at'=>DB::raw('CURRENT_TIMESTAMP')]);
        }
        return ['demo'=>true,'duplicate'=>false,'outcome'=>$action];
    }

    public function detail(int $id,int $actor,bool $admin=false): array
    {
        return $this->locked($id,$actor,$admin,function($db,$b,$m,$p){
            if($b->status==='pending'&&(int)$m->expires_at_epoch<=$this->epoch($db)){$this->settle($db,$b,$m,$p,'expired',null,'system');$b=$db->table('booking')->find($b->id);$p=$db->table('payment')->find($p->id);$m=$db->table('booking_demo')->where('booking_id',$b->id)->first();}
            $line=$db->table('booking_room')->where('booking_id',$b->id)->sole();$booking=(array)$b;
            $booking['check_in']=substr($b->check_in,0,10);$booking['check_out']=substr($b->check_out,0,10);
            $booking['nightly_rates']=app(RoomPricingService::class)->snapshot((int)$line->id);
            $booking['summary']=['hotel'=>$db->table('hotel')->where('id',$b->hotel_id)->value('name'),'lines'=>[['name'=>$m->room_name,'quantity'=>(int)$line->quantity,'nights'=>(int)$line->nights,'unit_price'=>$line->price_at_booking,'nightly_rates'=>$booking['nightly_rates']]]];
            $events=$db->table('booking_demo_event as e')->join('payment as p','p.id','=','e.payment_id')->where('e.booking_id',$b->id)
                ->where('e.outcome','!=','late_success')->orderBy('e.id')->get(['e.outcome','p.transaction_id as merchant_ref','p.amount','e.created_at_epoch']);
            $reconciliation=$db->table('booking_demo_reconciliation as r')->join('payment as p','p.id','=','r.payment_id')->where('r.booking_id',$b->id)
                ->orderBy('r.id')->get(['r.reason','r.received_amount as amount','p.transaction_id as merchant_ref','r.state'])->map(function($row){
                    $row->outcome=$row->reason==='late_payment'?'late_reconciliation':$row->reason.'_reconciliation';
                    return $row;
                });
            $events=$events->concat($reconciliation)->values();
            $payment=(array)$p;
            $payment['display_status']=$p->payment_status==='success'&&in_array($b->status,['cancelled','expired'],true)?'reconciliation':$p->payment_status;
            return ['demo'=>true,'booking'=>$booking,'payment'=>$payment,'invoice'=>$db->table('invoice')->where('booking_id',$b->id)->first(),'transactions'=>$events,'expires_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$m->expires_at_epoch),'confirmation_email'=>app(LocalBookingConfirmationMailService::class)->status((int)$b->id)];
        });
    }

    public function retry(int $id,int $actor,bool $admin=false): array
    {
        return $this->locked($id,$actor,$admin,function($db,$b,$m,$p){
            abort_unless($b->status==='pending'&&(int)$m->expires_at_epoch>$this->epoch($db),409,'Đơn đã hết hạn hoặc kết thúc.');
            if($p->payment_status==='pending')return ['demo'=>true,'payment_id'=>(int)$p->id,'duplicate'=>true];
            abort_unless($p->payment_status==='failed',409,'Giao dịch không thể thử lại.');
            return ['demo'=>true,'payment_id'=>$this->newPayment($db,$b->id,$b->total_price),'duplicate'=>false];
        });
    }

    public function cancel(int $id,int $actor): array
    {
        return $this->locked($id,$actor,false,fn($db,$b,$m,$p)=>$this->settle($db,$b,$m,$p,'cancelled',$actor,'customer'));
    }

    public function completeStay(int $id,int $admin): array
    {
        return $this->locked($id,$admin,true,function($db,$b,$m,$p){
            abort_unless(in_array($b->status,['confirmed','completed'],true) && $p->payment_status==='success' && $db->table('invoice')->where('booking_id',$b->id)->exists(),422,'Chỉ hoàn thành đơn đã xác nhận và thanh toán thành công.');
            abort_unless($this->money($p->amount)===$this->money($b->total_price),422,'Số tiền thanh toán không khớp đơn.');
            \App\Support\BookingLifecyclePolicy::completion($db,$b);
            $duplicate=$b->status==='completed';
            if(!$duplicate)$db->table('booking')->where('id',$b->id)->update(['status'=>'completed']);
            return ['message'=>'Đã hoàn thành lưu trú.','booking_id'=>$b->id,'status'=>'completed','duplicate'=>$duplicate];
        });
    }

    public function listing(int $page=1): array
    {
        $this->expireDue();
        $db=LocalBookingDemo::connection();return $db->table('booking as b')->join('booking_demo as d','d.booking_id','=','b.id')->join('hotel as h','h.id','=','b.hotel_id')
            ->select(['b.id','b.status','b.total_price','b.created_at','h.name as hotel'])
            ->selectSub($db->table('payment')->select('payment_status')->whereColumn('booking_id','b.id')->orderByDesc('id')->limit(1),'payment_status')
            ->selectSub($db->table('payment')->select('transaction_id')->whereColumn('booking_id','b.id')->orderByDesc('id')->limit(1),'transaction_id')->orderByDesc('b.id')->paginate(15,['*'],'page',$page)->toArray();
    }

    public function expireDue(?int $customer=null): void
    {
        $db=LocalBookingDemo::connection();$hotels=$db->table('booking_demo as d')->join('booking as b','b.id','=','d.booking_id')
            ->where('b.status','pending')->where('d.expires_at_epoch','<=',$this->epoch($db))
            ->when($customer!==null,fn($q)=>$q->where('b.customer_id',$customer))->distinct()->limit(100)->pluck('b.hotel_id');
        foreach($hotels as $hotel)$db->transaction(function()use($db,$hotel){$db->table('hotel')->where('id',$hotel)->lockForUpdate()->sole();$this->expireHotel($db,$hotel);},3);
    }
}
