<?php

namespace App\Console\Commands;

use App\Services\BookingLifecycleServiceV2;
use App\Support\RoomTypeV2Inventory;

/** Replaces the existing scheduler command name ONLY in the explicitly activated test overlay. */
class PreparedV2CancelExpiredBookings extends CancelExpiredBookings
{
    protected $signature = 'bookings:v2-cancel-expired-alias';

    public function handle(): int
    {
        $db = RoomTypeV2Inventory::open('mysql')->db;
        $ids = $db->table('booking')->where('status', 'pending')->whereNotNull('expires_at')
            ->where('expires_at', '<', now())->orderBy('hotel_id')->orderBy('id')->pluck('id');
        $count = 0;
        foreach ($ids as $id) if (app(BookingLifecycleServiceV2::class)->expire('mysql', $id)) $count++;
        $this->line((string) $count);
        return self::SUCCESS;
    }
}
