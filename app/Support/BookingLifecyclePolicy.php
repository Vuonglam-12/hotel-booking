<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Connection;

final class BookingLifecyclePolicy
{
    /** Administrative status editing never constitutes payment verification. */
    public static function transition(Connection $db, object $booking, string $target): void
    {
        abort_unless(in_array($target,['pending','confirmed','completed','cancelled'],true),422,'Trạng thái không hợp lệ.');
        if($booking->status===$target)return;
        abort_if(in_array($booking->status,['cancelled','expired','completed'],true),422,'Đơn đã kết thúc không được khôi phục hoặc đổi trạng thái. Vui lòng tạo đơn mới.');
        abort_if($target==='pending',422,'Không được đưa đơn đã xác nhận trở lại chờ thanh toán.');
        abort_if($target==='confirmed',422,'Chỉ xác nhận đơn qua kết quả thanh toán hợp lệ, không đổi trạng thái trực tiếp.');
        if($target==='completed')self::completion($db,$booking);
    }

    public static function completion(Connection $db, object $booking): void
    {
        abort_unless(in_array($booking->status,['confirmed','completed'],true),422,'Chỉ hoàn thành đơn đã xác nhận và thanh toán thành công.');
        $payment=$db->table('payment')->where('booking_id',$booking->id)->where('payment_status','success')
            ->where('amount',$booking->total_price)->orderByDesc('id')->lockForUpdate()->first();
        abort_unless($payment&&$db->table('invoice')->where('booking_id',$booking->id)->where('payment_id',$payment->id)->exists(),422,'Chưa có thanh toán hợp lệ và hóa đơn tương ứng.');
        if($payment->payment_method==='demo')abort_unless($db->table('booking_demo_event')->where('payment_id',$payment->id)->where('outcome','success')->exists(),422,'Chưa có kết quả thanh toán hợp lệ.');
        $hotel=$db->table('hotel')->where('id',$booking->hotel_id)->first();
        $date=$booking->check_out instanceof \DateTimeInterface?$booking->check_out->format('Y-m-d'):substr($booking->check_out,0,10);
        $checkout=Carbon::parse($date.' '.($hotel->check_out_time??'12:00:00'),'Asia/Ho_Chi_Minh');
        abort_if(Carbon::now('Asia/Ho_Chi_Minh')->lt($checkout),422,'Chỉ hoàn thành đơn sau giờ trả phòng của khách sạn vào ngày trả phòng.');
    }
}
