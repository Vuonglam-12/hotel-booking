<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingEffectServiceV2;
use App\Services\PreparedV2\BookingMailSender;
use App\Support\RoomTypeV2Inventory;

class DeliverPreparedV2Effects extends Command
{
    protected $signature = 'bookings:v2-deliver-prepared';
    protected $description = 'Deliver prepared confirmation effects to the isolated in-memory mail transport';

    public function handle(BookingEffectServiceV2 $effects, BookingMailSender $sender): int
    {
        $db = RoomTypeV2Inventory::open('mysql')->db;
        $ids = $db->table('booking_effect')->where('state', 'pending')->orWhere(function ($q) {
            $q->whereIn('state', ['sending','processing'])->where('lease_expires_at', '<=', now());
        })->orderBy('id')->pluck('id');
        $failed = 0; $sent = 0;
        foreach ($ids as $id) {
            try { if ($effects->deliver('mysql', $id, $sender)) $sent++; }
            catch (\Throwable $e) { $failed++; $this->error('Effect '.$id.' not acknowledged; inspect delivery state before retry.'); }
        }
        $this->line('Delivered '.$sent.'; failed '.$failed);
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
