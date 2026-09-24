<?php

namespace App\Services;

use App\Models\Country;
use App\Models\PopularLeague;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FootballDataSyncService
{
    public function __construct(
        protected ApiFootballService $apiFootballService,
        protected FootballSnapshotService $snapshotService,
        protected CompetitionDirectoryService $competitionDirectoryService,
    ) {
    }

    public function syncAll(): array
    {
        $competitions = $this->attempt(fn () => $this->syncCompetitionsSnapshot());
        $updates = $this->attempt(fn () => $this->syncUpdatesSnapshot());
        $aiPredictions = $this->attempt(fn () => $this->syncAiPredictions());
        $countries = $this->attempt(fn () => $this->syncCountries());
        $metadata = $this->attempt(fn () => $this->syncPredictionMetadata());
        $competitionDirectory = $this->attempt(fn () => $this->syncCompetitionDirectory());

        return [
            'competitions' => $competitions,
            'updates' => $updates,
            'ai_predictions' => $aiPredictions,
            'countries' => $countries,
            'metadata' => $metadata,
            'competition_directory' => $competitionDirectory,
        ];
    }

    public function syncCompetitionsSnapshot(): int
    {
        $payload = $this->apiFootballService->leagues();

        $items = collect($payload['response'] ?? [])
            ->map(fn (array $league) => [
                'id' => $league['league']['id'] ?? null,
                'name' => $league['league']['name'] ?? null,
                'type' => $league['league']['type'] ?? null,
                'logo' => $league['league']['logo'] ?? null,
                'country' => $league['country']['name'] ?? null,
                'flag' => $league['country']['flag'] ?? null,
                'season' => collect($league['seasons'] ?? [])->firstWhere('current', true)['year'] ?? null,
            ])
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->take(80)
            ->values()
            ->all();

        $this->snapshotService->put('competitions', $items);

        return count($items);
    }

    public function syncUpdatesSnapshot(): int
    {
        $items = collect($this->apiFootballService->footballUpdates($this->apiFootballService->resolveTeamIdsForHighlights()))
            ->take(50)
            ->values()
            ->all();

        $this->snapshotService->put('updates', $items);

        return count($items);
    }

    public function syncCountries(): int
    {
        $payload = $this->apiFootballService->countries();
        $items = collect($payload['response'] ?? []);

        foreach ($items as $item) {
            Country::updateOrCreate(
                ['name' => $item['name'] ?? 'Unknown'],
                [
                    'code' => $item['code'] ?? null,
                    'flag' => $item['flag'] ?? null,
                ]
            );
        }

        return $items->count();
    }

    public function syncCompetitionDirectory(): int
    {
        $payload = $this->competitionDirectoryService->refresh();

        return count($payload['data'] ?? []);
    }

    public function syncPredictionMetadata(): int
    {
        $competitions = collect($this->snapshotService->get('competitions'));
        $countries = $this->countriesByNormalizedName();
        $updated = 0;

        Prediction::query()->chunkById(100, function ($predictions) use ($competitions, $countries, &$updated) {
            foreach ($predictions as $prediction) {
                $league = $competitions->firstWhere('id', $prediction->league_id);
                $country = $this->countryFromName($countries, $prediction->country_name);

                $attributes = array_filter([
                    'league_logo' => $prediction->league_logo ?: ($league['logo'] ?? null) ?: ($country?->flag ?? null),
                    'country_code' => $prediction->country_code ?: ($country?->code ?? null),
                ], fn ($value) => $value !== null && $value !== '');

                if ($attributes === []) {
                    continue;
                }

                $prediction->update($attributes);
                $updated++;
            }
        });

        return $updated;
    }

    public function syncAiPredictions(): int
    {
        $admin = User::query()->where('email', 'ozorclinton@gmail.com')->first();

        if (! $admin) {
            return 0;
        }

        $this->pruneCurrentDayUpcomingMatches();

        $fixtures = $this->popularLeagueFixtures();

        $created = 0;

        foreach ($fixtures as $fixture) {
            try {
                $fixtureId = $fixture['fixture']['id'] ?? null;

                if (! $fixtureId) {
                    continue;
                }

                $predictionPayload = collect($this->apiFootballService->fixturePrediction($fixtureId)['response'] ?? [])->first();

                if (! $this->hasUsefulPrediction($predictionPayload)) {
                    continue;
                }

                $mapped = $this->mapAiPrediction($fixture, $predictionPayload);

                if ($mapped['prediction_value'] === '0') {
                    continue;
                }

                $this->saveSyncedPrediction($fixtureId, $mapped, $admin->id, 'ai_prediction');
                $created++;

                if (Carbon::parse($mapped['match_starts_at'], 'Africa/Lagos')->isAfter(now('Africa/Lagos')->endOfDay())) {
                    $this->saveSyncedPrediction($fixtureId, $mapped, $admin->id, 'upcoming_matches');
                    $created++;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $created;
    }

    public function syncFallbackMatches(int $limit = 50): int
    {
        $admin = User::query()->where('email', 'ozorclinton@gmail.com')->first();

        if (! $admin) {
            return 0;
        }

        $fixtures = $this->fallbackFixtures($limit);
        $created = 0;

        foreach ($fixtures as $fixture) {
            try {
                $fixtureId = $fixture['fixture']['id'] ?? null;

                if (! $fixtureId) {
                    continue;
                }

                $predictionPayload = collect($this->apiFootballService->fixturePrediction($fixtureId)['response'] ?? [])->first();

                if (! $this->hasUsefulPrediction($predictionPayload)) {
                    continue;
                }

                $mapped = $this->mapAiPrediction($fixture, $predictionPayload);

                if ($mapped['prediction_value'] === '0') {
                    continue;
                }

                $this->saveSyncedPrediction($fixtureId, $mapped, $admin->id, 'ai_prediction');
                $created++;
            } catch (\Throwable) {
                continue;
            }
        }

        return $created;
    }

    protected function pruneCurrentDayUpcomingMatches(): void
    {
        Prediction::query()
            ->where('source', 'api_football')
            ->where('category', 'upcoming_matches')
            ->whereDate('match_starts_at', '<=', now('Africa/Lagos')->toDateString())
            ->delete();
    }

    protected function saveSyncedPrediction(int $fixtureId, array $mapped, int $adminId, string $category): Prediction
    {
        return Prediction::updateOrCreate(
            [
                'fixture_id' => $fixtureId,
                'source' => 'api_football',
                'category' => $category,
            ],
            [
                ...$mapped,
                'user_id' => $adminId,
                'status' => 'published',
                'scope' => 'editorial',
                'source' => 'api_football',
                'category' => $category,
                'published_at' => now(),
            ]
        );
    }

    protected function popularLeagueFixtures(): Collection
    {
        $dates = collect(range(0, 2))
            ->map(fn (int $offset) => now('Africa/Lagos')->addDays($offset)->toDateString());

        $popularLeagues = PopularLeague::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($popularLeagues->isEmpty()) {
            return collect();
        }

        return $popularLeagues
            ->flatMap(function (PopularLeague $league) use ($dates) {
                return $dates->flatMap(function (string $date) use ($league) {
                    try {
                        $payload = $this->apiFootballService->fixtures(array_filter([
                            'league' => $league->league_id,
                            'season' => $league->season ?: now('Africa/Lagos')->year,
                            'date' => $date,
                            'timezone' => 'Africa/Lagos',
                        ]));
                    } catch (\Throwable) {
                        return [];
                    }

                    return $payload['response'] ?? [];
                });
            })
            ->unique(fn (array $fixture) => $fixture['fixture']['id'] ?? null)
            ->filter(fn (array $fixture) => ! empty($fixture['fixture']['id']))
            ->values();
    }

    protected function fallbackFixtures(int $limit): Collection
    {
        $dates = collect(range(0, 2))
            ->map(fn (int $offset) => now('Africa/Lagos')->addDays($offset)->toDateString());

        return $dates
            ->flatMap(function (string $date) {
                try {
                    $payload = $this->apiFootballService->fixtures([
                        'date' => $date,
                        'timezone' => 'Africa/Lagos',
                    ]);
                } catch (\Throwable) {
                    return [];
                }

                return $payload['response'] ?? [];
            })
            ->unique(fn (array $fixture) => $fixture['fixture']['id'] ?? null)
            ->filter(fn (array $fixture) => ! empty($fixture['fixture']['id']))
            ->sortBy(fn (array $fixture) => $fixture['fixture']['date'] ?? '')
            ->take(max(1, min(50, $limit)))
            ->values();
    }

    protected function hasUsefulPrediction(?array $prediction): bool
    {
        if (! $prediction || empty($prediction['predictions'])) {
            return false;
        }

        $predictions = $prediction['predictions'];
        $winner = trim((string) ($predictions['winner']['name'] ?? ''));
        $winnerComment = trim((string) ($predictions['winner']['comment'] ?? ''));
        $underOver = trim((string) ($predictions['under_over'] ?? ''));
        $percent = collect($predictions['percent'] ?? [])
            ->map(fn ($value) => (int) rtrim((string) $value, '%'))
            ->max();

        return $winner !== '' || $winnerComment !== '' || $underOver !== '' || ((int) $percent) > 0;
    }

    protected function mapAiPrediction(array $fixture, ?array $prediction): array
    {
        $countries = $this->countriesByNormalizedName();
        $country = $this->countryFromName($countries, $fixture['league']['country'] ?? null);
        $winner = $prediction['predictions']['winner']['name'] ?? null;
        $winnerComment = $prediction['predictions']['winner']['comment'] ?? null;
        $advice = $prediction['predictions']['advice'] ?? 'AI recommendation is not available yet.';
        $underOver = $prediction['predictions']['under_over'] ?? null;
        $percentages = collect($prediction['predictions']['percent'] ?? []);
        $homePercent = (int) rtrim((string) $percentages->get('home', '0'), '%');
        $awayPercent = (int) rtrim((string) $percentages->get('away', '0'), '%');
        $drawPercent = (int) rtrim((string) $percentages->get('draw', '0'), '%');
        $probability = max($homePercent, $awayPercent, $drawPercent);
        [$predictionType, $predictionValue] = $this->resolveAiMarket(
            fixture: $fixture,
            winner: $winner,
            winnerComment: $winnerComment,
            underOver: $underOver,
        );

        $analysisLines = array_filter([
            $advice,
            $winner && $winnerComment ? "AI winner lean: {$winner} ({$winnerComment})." : null,
            $underOver ? "Suggested total market: {$underOver}." : null,
            isset($prediction['comparison']['form']['home'], $prediction['comparison']['form']['away'])
                ? 'Form edge: '.($fixture['teams']['home']['name'] ?? 'Home').' '.$prediction['comparison']['form']['home']
                .' vs '.($fixture['teams']['away']['name'] ?? 'Away').' '.$prediction['comparison']['form']['away'].'.'
                : null,
        ]);

        return [
            'league_id' => $fixture['league']['id'] ?? null,
            'league_name' => str((string) ($fixture['league']['name'] ?? 'League'))->limit(255)->toString(),
            'league_logo' => $fixture['league']['logo'] ?: ($country?->flag ?? null),
            'country_name' => str((string) ($fixture['league']['country'] ?? ''))->limit(255)->toString(),
            'country_code' => $country?->code,
            'home_team_id' => $fixture['teams']['home']['id'] ?? null,
            'home_team_name' => str((string) ($fixture['teams']['home']['name'] ?? 'Home'))->limit(255)->toString(),
            'home_team_logo' => $fixture['teams']['home']['logo'] ?? null,
            'away_team_id' => $fixture['teams']['away']['id'] ?? null,
            'away_team_name' => str((string) ($fixture['teams']['away']['name'] ?? 'Away'))->limit(255)->toString(),
            'away_team_logo' => $fixture['teams']['away']['logo'] ?? null,
            'match_starts_at' => Carbon::parse($fixture['fixture']['date'] ?? now()),
            'prediction_type' => $predictionType,
            'prediction_value' => $predictionValue,
            'probability' => $probability ?: null,
            'odds' => null,
            'analysis' => str(implode(' ', $analysisLines))->limit(5000)->toString(),
            'likes_count' => 0,
            'comments_count' => 0,
        ];
    }

    protected function resolveAiMarket(array $fixture, ?string $winner, ?string $winnerComment, ?string $underOver): array
    {
        $homeTeam = trim((string) ($fixture['teams']['home']['name'] ?? ''));
        $awayTeam = trim((string) ($fixture['teams']['away']['name'] ?? ''));
        $comment = strtolower(trim((string) $winnerComment));

        if ($underOver) {
            $normalizedUnderOver = str((string) $underOver)->squish()->toString();
            if (preg_match('/\b(under|over)\s+(\d+(?:\.\d+)?)\b/i', $normalizedUnderOver, $matches) === 1) {
                return ["Under/Over {$matches[2]}", str(ucfirst(strtolower($matches[1])))." {$matches[2]}"];
            }
        }

        if ($winner && $homeTeam !== '' && strcasecmp($winner, $homeTeam) === 0) {
            if (str_contains($comment, 'draw')) {
                return ['Double Chance', '1X'];
            }

            return ['1X2', '1'];
        }

        if ($winner && $awayTeam !== '' && strcasecmp($winner, $awayTeam) === 0) {
            if (str_contains($comment, 'draw')) {
                return ['Double Chance', 'X2'];
            }

            return ['1X2', '2'];
        }

        if ($winner && strcasecmp($winner, 'draw') === 0) {
            return ['1X2', 'X'];
        }

        if ($comment !== '') {
            if (str_contains($comment, 'home') && str_contains($comment, 'draw')) {
                return ['Double Chance', '1X'];
            }

            if (str_contains($comment, 'away') && str_contains($comment, 'draw')) {
                return ['Double Chance', 'X2'];
            }

            if (str_contains($comment, 'home')) {
                return ['1X2', '1'];
            }

            if (str_contains($comment, 'away')) {
                return ['1X2', '2'];
            }

            if (str_contains($comment, 'draw')) {
                return ['1X2', 'X'];
            }
        }

        return ['AI Prediction', $this->normalizePredictionValue($winner ?? $underOver ?? '0')];
    }

    protected function normalizePredictionValue(string|int|float|null $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $normalized = trim($normalized);

        if ($normalized === '' || $normalized === 'ai tip' || $normalized === 'ai prediction') {
            return '0';
        }

        return str((string) $value)->limit(100)->toString();
    }

    protected function attempt(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function countriesByNormalizedName(): Collection
    {
        return Country::query()
            ->get()
            ->keyBy(fn (Country $country) => $this->normalizeCountryName($country->name));
    }

    protected function countryFromName(Collection $countries, ?string $countryName): ?Country
    {
        if (! $countryName) {
            return null;
        }

        return $countries->get($this->normalizeCountryName($countryName));
    }

    protected function normalizeCountryName(?string $countryName): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim((string) $countryName)));
    }
}
