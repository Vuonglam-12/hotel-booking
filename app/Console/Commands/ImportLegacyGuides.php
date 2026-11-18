<?php

namespace App\Console\Commands;

use App\Services\LegacyBlogImporter;
use Illuminate\Console\Command;

final class ImportLegacyGuides extends Command
{
    protected $signature = 'guides:import-legacy {--apply : Insert new drafts into the configured database; default is filesystem inspection only} {--allow-primary : Explicitly permit hotel_booking after deployment review}';
    protected $description = 'Preserve the nine original Blog posts as drafts; never publish or overwrite existing imports.';

    public function handle(LegacyBlogImporter $importer): int
    {
        $rows = $importer->inspect();
        $this->table(['Blog ID', 'Content', 'Cover file', 'New import status'], array_map(fn ($row) => [
            $row['metadata']['id'], $row['state'], $row['cover_exists'] ? 'present' : 'missing', 'draft',
        ], $rows));
        if (!$this->option('apply')) { $this->info('Inspection only; no database accessed. Use --apply after reviewing the target database.'); return self::SUCCESS; }
        $database = config('database.connections.'.config('database.default').'.database');
        if (strtolower(basename(str_replace('\\', '/', (string) $database))) === 'hotel_booking' && !$this->option('allow-primary')) {
            $this->error('Refusing hotel_booking. Deployment review and explicit --allow-primary are required.'); return self::FAILURE;
        }
        $this->line('Target: '.config('database.default').' / '.$database);
        $this->info('New posts remain drafts pending Admin approval; existing posts and publication states are kept unchanged.');
        $this->table(['Blog ID', 'Result', 'Status'], array_map(fn ($row) => array_values($row), $importer->apply()));
        return self::SUCCESS;
    }
}
