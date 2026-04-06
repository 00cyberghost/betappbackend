<?php

use App\Services\FootballDataSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('football:sync-now', function (FootballDataSyncService $footballDataSyncService) {
    $result = $footballDataSyncService->syncAll();

    $this->table(
        ['Feed', 'Count'],
        collect($result)->map(fn ($count, $feed) => [$feed, (string) $count])->all()
    );
})->purpose('Run football snapshot and AI prediction sync immediately.');

Schedule::command('football:sync-data')->everyFifteenMinutes();
