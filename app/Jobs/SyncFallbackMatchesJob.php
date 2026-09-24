<?php

namespace App\Jobs;

use App\Services\FootballDataSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncFallbackMatchesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(
        public int $limit = 50,
    ) {
    }

    public function uniqueId(): string
    {
        return 'football-sync-fallback-matches';
    }

    public function handle(FootballDataSyncService $footballDataSyncService): void
    {
        $created = $footballDataSyncService->syncFallbackMatches($this->limit);

        Log::info('Fallback football match sync completed.', [
            'created' => $created,
            'limit' => $this->limit,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Fallback football match sync failed.', [
            'message' => $exception->getMessage(),
        ]);
    }
}
