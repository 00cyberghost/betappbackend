<?php

namespace App\Jobs;

use App\Services\FootballDataSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncFootballDataJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function uniqueId(): string
    {
        return 'football-sync-data';
    }

    public function handle(FootballDataSyncService $footballDataSyncService): void
    {
        $result = $footballDataSyncService->syncAll();

        Log::info('Manual football data sync completed.', [
            'counts' => $result,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Manual football data sync failed.', [
            'message' => $exception->getMessage(),
        ]);
    }
}
