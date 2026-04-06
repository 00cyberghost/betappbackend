<?php

namespace App\Console\Commands;

use App\Services\FootballDataSyncService;
use Illuminate\Console\Command;

class SyncFootballData extends Command
{
    protected $signature = 'football:sync-data';

    protected $description = 'Refresh API-Football snapshots and AI prediction records for the app.';

    public function handle(FootballDataSyncService $footballDataSyncService): int
    {
        $result = $footballDataSyncService->syncAll();

        $this->info('Football data synced successfully.');
        $this->table(
            ['Feed', 'Count'],
            collect($result)->map(fn ($count, $feed) => [$feed, (string) $count])->all()
        );

        return self::SUCCESS;
    }
}
