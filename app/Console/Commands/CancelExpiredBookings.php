<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;

class CancelExpiredBookings extends Command
{
    // Tên lệnh — chạy thủ công bằng: php artisan bookings:cancel-expired
    protected $signature   = 'bookings:cancel-expired';
    protected $description = 'Tự động hủy booking hết hạn 15 phút chưa thanh toán và release slot';

    public function handle()
    {
        // Lấy tất cả booking pending đã hết hạn
        $expiredBookings = Booking::with('bookingRooms')
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info('Không có booking nào hết hạn.');
            return;
        }

        $count = 0;

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {

                // Hủy booking
                $booking->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => 'system', // hệ thống tự hủy
                ]);

                // Cộng lại slot room_availability
                $quantity   = $booking->bookingRooms->sum('quantity');
                $roomTypeId = $booking->bookingRooms->first()?->room_type_id;

                if ($roomTypeId && $quantity > 0) {
                    $dates = CarbonPeriod::create(
                        $booking->check_in->format('Y-m-d'),
                        $booking->check_out->copy()->subDay()->format('Y-m-d')
                    );

                    foreach ($dates as $date) {
                        DB::table('room_availability')
                            ->where('hotel_id',     $booking->hotel_id)
                            ->where('room_type_id', $roomTypeId)
                            ->where('date',         $date->format('Y-m-d'))
                            ->increment('available_count', $quantity);

                        DB::table('room_availability')
                            ->where('hotel_id',     $booking->hotel_id)
                            ->where('room_type_id', $roomTypeId)
                            ->where('date',         $date->format('Y-m-d'))
                            ->decrement('booked_count', $quantity);
                    }
                }
            });

            $count++;
            $this->info("Đã hủy booking #{$booking->id}");
        }

        $this->info("Tổng cộng hủy {$count} booking hết hạn.");
    }
}