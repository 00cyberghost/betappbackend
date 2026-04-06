<?php

namespace App\Services;

use App\Models\FootballSnapshot;

class FootballSnapshotService
{
    public function put(string $key, array $payload): FootballSnapshot
    {
        return FootballSnapshot::updateOrCreate(
            ['key' => $key],
            [
                'payload' => $payload,
                'refreshed_at' => now(),
            ]
        );
    }

    public function get(string $key, array $fallback = []): array
    {
        return FootballSnapshot::query()
            ->where('key', $key)
            ->value('payload') ?? $fallback;
    }
}
