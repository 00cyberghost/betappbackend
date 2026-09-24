<?php

namespace App\Console\Commands;

use App\Services\FootballDataSyncService;
use Illuminate\Console\Command;

class SyncFallbackMatches extends Command
{
    protected $signature = 'football:sync-fallback-matches {--limit=50 : Maximum fixtures to inspect}';

    protected $description = 'Pull broad API-Football fixtures as a manual fallback to fill homepage AI predictions.';

    public function handle(FootballDataSyncService $footballDataSyncService): int
    {
        $limit = max(1, min(50, (int) $this->option('limit')));
        $created = $footballDataSyncService->syncFallbackMatches($limit);

        $this->info("Fallback match sync completed. {$created} AI prediction records created or updated.");

        return self::SUCCESS;
    }
}
