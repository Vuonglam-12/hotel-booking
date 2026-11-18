<?php

namespace App\Console\Commands;

use App\Services\RoomTypeV2BackfillService;
use App\Support\RoomTypeV2Plan;
use Illuminate\Console\Command;

class PrepareRoomTypeV2 extends Command
{
    protected $signature = 'room-types:v2-prepare {--database=mysql : Explicit isolated test connection} {--dry-run : Validate only; do not backfill}';
    protected $description = 'Phase 2B.1: prepare owned RoomTypes on the isolated test server only; never remap legacy relations';

    public function handle(): int
    {
        try {
            $result = app(RoomTypeV2BackfillService::class)->run($this->option('database'), RoomTypeV2Plan::prepared(), (bool) $this->option('dry-run'));
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
