<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\RoomTypeV2Inventory;
use App\Services\BookingLifecycleServiceV2;

class ExpirePreparedV2Bookings extends Command
{
    protected $signature='bookings:v2-expire-prepared {--database=mysql}';
    protected $description='Isolated-test expiry using the shared Hotel/Type/Booking writer; not scheduled in production';
    public function handle(BookingLifecycleServiceV2 $service): int
    {
        $s=RoomTypeV2Inventory::open($this->option('database'));
        $ids=$s->db->table('booking')->where('status','pending')->whereNotNull('expires_at')->where('expires_at','<',now())->orderBy('hotel_id')->orderBy('id')->pluck('id');
        $count=0; foreach ($ids as $id) if ($service->expire($this->option('database'),$id)) $count++;
        $this->line((string)$count); return self::SUCCESS;
    }
}
