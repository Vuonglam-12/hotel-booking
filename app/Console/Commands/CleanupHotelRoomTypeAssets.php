<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AdminAccommodationImageService;

class CleanupHotelRoomTypeAssets extends Command
{
    protected $signature = 'hotel-room-types:cleanup-assets';
    protected $description = 'Retry deletion of unlinked, uniquely owned RoomType uploads.';
    public function handle(): int { $this->info('Removed assets: ' . app(AdminAccommodationImageService::class)->cleanup()); return self::SUCCESS; }
}
