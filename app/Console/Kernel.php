<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Chạy mỗi phút — check booking hết hạn
        $schedule->command('bookings:cancel-expired')->everyMinute()->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/booking-expiry-scheduler.log'));
        $schedule->command('hotel-room-types:cleanup-assets')->hourly()->withoutOverlapping();
        $schedule->command('places:sync-sheet')->everyFifteenMinutes()->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/place-sheet-sync.log'));
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
