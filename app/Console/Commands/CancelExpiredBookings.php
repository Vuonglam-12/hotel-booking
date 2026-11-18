<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelExpiredBookings extends Command
{
    protected $signature = 'bookings:cancel-expired';
    protected $description = 'Tự động hủy booking quá hạn thanh toán và giải phóng phòng (DEMO mặc định 10 phút)';

    public function handle()
    {
        $localDemo = \App\Support\LocalBookingDemo::enabled();
        if ($localDemo) app(\App\Services\LocalBookingDemoService::class)->expireDue();
        $candidates = Booking::where('status', 'pending')->whereNotNull('expires_at')
            ->when($localDemo, fn($q) => $q->whereNotExists(fn($d) => $d->selectRaw('1')->from('booking_demo')->whereColumn('booking_demo.booking_id','booking.id')))
            ->where('expires_at', '<', now())->get(['id', 'hotel_id']);
        if ($candidates->isEmpty()) {
            $this->heartbeat();
            $this->info('Không có booking nào hết hạn.');
            return;
        }
        $count = 0;
        foreach ($candidates as $candidate) {
            $cancelled = DB::transaction(function () use ($candidate) {
                // Same lock order as booking creation/restoration; recheck the current row after waiting.
                Hotel::whereKey($candidate->hotel_id)->lockForUpdate()->firstOrFail();
                $booking = Booking::whereKey($candidate->id)->lockForUpdate()->first();
                if (!$booking || $booking->status !== 'pending' || !$booking->expires_at || !$booking->expires_at->isPast()) {
                    return false;
                }
                $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'system']);
                // Inventory is derived from active reservations. Creation does not debit room_availability.
                return true;
            });
            if ($cancelled) {
                $count++;
                $this->info("Đã hủy booking #{$candidate->id}");
            }
        }
        $this->info("Tổng cộng hủy {$count} booking hết hạn.");
        $this->heartbeat();
    }

    private function heartbeat(): void
    {
        if (!app()->environment('local')) return;
        $path=storage_path('app/private/booking-scheduler-heartbeat.json');
        if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
        file_put_contents($path,json_encode(['ran_at_utc'=>gmdate('c'),'trigger'=>getenv('HOTEL_BOOKING_SCHEDULER')==='1'?'windows_scheduler':'manual',
            'pid'=>getmypid(),'status'=>'success'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
    }
}
