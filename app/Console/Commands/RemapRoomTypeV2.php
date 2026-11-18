<?php

namespace App\Console\Commands;

use App\Services\RoomTypeV2RemapService;
use App\Support\RoomTypeV2Plan;
use Illuminate\Console\Command;

class RemapRoomTypeV2 extends Command
{
    protected $signature = 'room-types:v2-remap {--database=mysql} {--manifest=} {--prepare} {--dry-run}';
    protected $description = 'Prepare or resume an offline, isolated-test-only RoomType remap';

    public function handle(RoomTypeV2RemapService $service): int
    {
        try {
            if (!$this->option('manifest') || ($this->option('prepare') && $this->option('dry-run'))) throw new \RuntimeException('Pass a private manifest path; prepare and dry-run are separate actions.');
            $result = $this->option('prepare')
                ? $service->prepare($this->option('database'), RoomTypeV2Plan::prepared(), $this->option('manifest'))
                : $service->run($this->option('database'), $this->option('manifest'), (bool) $this->option('dry-run'));
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
