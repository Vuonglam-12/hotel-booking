<?php
namespace App\Console\Commands;

use App\Services\Places\SheetPlaceSync;
use Illuminate\Console\Command;

final class SyncPlaceSheet extends Command
{
    protected $signature='places:sync-sheet {--dry-run : Đọc thật và tính thay đổi, không ghi DB}';
    protected $description='Đồng bộ nguồn địa điểm Google Sheet bằng quyền chỉ đọc, giữ nguyên địa điểm cũ.';

    public function handle(SheetPlaceSync $sync): int
    {
        try {
            $report=$sync->run((bool)$this->option('dry-run'));
            $this->line(json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }catch(\RuntimeException $error){$this->error($error->getMessage());return self::FAILURE;}
        catch(\Throwable $error){$this->error('Đồng bộ thất bại; giao dịch đã hoàn tác, dữ liệu lần trước được giữ nguyên.');return self::FAILURE;}
    }
}
