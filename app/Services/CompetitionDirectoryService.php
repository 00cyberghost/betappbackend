<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;

class CompetitionDirectoryService
{
    public const CACHE_KEY = 'app:competition-directory';

    public function __construct(
        protected ApiFootballService $apiFootballService,
    ) {
    }

    public function get(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->build());
    }

    public function refresh(): array
    {
        return $this->get(true);
    }

    protected function build(): array
    {
        $payload = $this->apiFootballService->leagues();
        $items = collect($payload['response'] ?? [])
            ->map(fn (array $league) => $this->mapCompetition($league))
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->filter(fn (array $item) => $this->isCurrentOrFutureCompetition($item))
            ->values();

        return [
            'data' => $items->all(),
            'top_leagues' => $this->topLeagues($items)->all(),
            'countries' => $this->competitionCountries($items)->all(),
            'meta' => [
                'cached_for_seconds' => 86400,
                'refreshed_at' => now('Africa/Lagos')->toIso8601String(),
            ],
        ];
    }

    protected function mapCompetition(array $league): array
    {
        $season = $this->currentOrFutureSeason($league['seasons'] ?? []);

        return [
            'id' => $league['league']['id'] ?? null,
            'name' => $league['league']['name'] ?? null,
            'type' => $league['league']['type'] ?? null,
            'logo' => $league['league']['logo'] ?? null,
            'country' => $league['country']['name'] ?? null,
            'flag' => $league['country']['flag'] ?? null,
            'season' => $season,
        ];
    }

    protected function topLeagues($items)
    {
        $byId = $items->keyBy('id');

        return collect($this->topLeagueIds())
            ->map(function (int $id) use ($byId) {
                $league = $byId->get($id);

                if ($league) {
                    return $league;
                }

                try {
                    $payload = $this->apiFootballService->leagueById($id);

                    return collect($payload['response'] ?? [])
                        ->map(fn (array $item) => $this->mapCompetition($item))
                        ->first();
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->filter(fn (array $item) => $this->isCurrentOrFutureCompetition($item))
            ->values();
    }

    protected function competitionCountries($items)
    {
        $leagueCounts = $items
            ->filter(fn (array $item) => ! empty($item['country']))
            ->groupBy(fn (array $item) => $item['country'])
            ->map(fn ($leagues) => $leagues->count());

        return Country::query()
            ->orderBy('name')
            ->get(['name', 'flag'])
            ->map(fn (Country $country) => [
                'name' => $country->name,
                'flag' => $country->flag,
                'leagues_count' => $leagueCounts->get($country->name, 0),
            ])
            ->sortBy('name')
            ->values();
    }

    protected function topLeagueIds(): array
    {
        return [39, 2, 3, 848, 140, 135, 78, 61, 253, 71, 307, 128, 262, 94, 203];
    }

    protected function currentOrFutureSeason(array $seasons): ?int
    {
        $current = collect($seasons)->firstWhere('current', true);

        if (! empty($current['year'])) {
            return (int) $current['year'];
        }

        $currentYear = (int) now('Africa/Lagos')->year;

        return collect($seasons)
            ->pluck('year')
            ->filter(fn ($year) => is_numeric($year) && (int) $year >= $currentYear)
            ->sort()
            ->map(fn ($year) => (int) $year)
            ->first();
    }

    protected function isCurrentOrFutureCompetition(array $item): bool
    {
        return ! empty($item['season']) && (int) $item['season'] >= (int) now('Africa/Lagos')->year;
    }
}
