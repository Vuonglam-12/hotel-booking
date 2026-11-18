<?php

namespace App\Support;

final class RoomTypeV2BookingAudit
{
    public static function record($db,int $bookingId,?string $before,string $after,string $kind,?int $actor,string $reason): void
    {
        if (!config('roomtype-v2.final_integration',false) || $before===$after) return;
        $db->table('booking_state_audit')->insert(['booking_id'=>$bookingId,'status_before'=>$before,'status_after'=>$after,
            'actor_kind'=>$kind,'actor_id'=>$actor,'reason'=>$reason,'created_at'=>now()]);
    }
}
