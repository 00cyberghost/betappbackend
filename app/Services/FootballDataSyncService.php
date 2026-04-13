<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FootballDataSyncService
{
    public function __construct(
        protected ApiFootballService $apiFootballService,
        protected FootballSnapshotService $snapshotService,
    ) {
    }

    public function syncAll(): array
    {
        $live = $this->attempt(fn () => $this->syncLiveSnapshot());
        $competitions = $this->attempt(fn () => $this->syncCompetitionsSnapshot());
        $updates = $this->attempt(fn () => $this->syncUpdatesSnapshot());
        $aiPredictions = $this->attempt(fn () => $this->syncAiPredictions());
        $countries = $this->attempt(fn () => $this->syncCountries());
        $metadata = $this->attempt(fn () => $this->syncPredictionMetadata());
        $this->attempt(fn () => $this->syncEditorialHighlightsFromLive());

        return [
            'live' => $live,
            'competitions' => $competitions,
            'updates' => $updates,
            'ai_predictions' => $aiPredictions,
            'countries' => $countries,
            'metadata' => $metadata,
        ];
    }

    public function syncLiveSnapshot(): int
    {
        $payload = $this->apiFootballService->fixtures([
            'live' => 'all',
            'timezone' => 'Africa/Lagos',
        ]);

        $items = collect($payload['response'] ?? [])
            ->map(fn (array $fixture) => [
                'id' => $fixture['fixture']['id'] ?? null,
                'league' => $fixture['league']['name'] ?? 'League',
                'league_id' => $fixture['league']['id'] ?? null,
                'league_logo' => $fixture['league']['logo'] ?? null,
                'season' => $fixture['league']['season'] ?? null,
                'home_team' => $fixture['teams']['home']['name'] ?? 'Home',
                'away_team' => $fixture['teams']['away']['name'] ?? 'Away',
                'home_team_id' => $fixture['teams']['home']['id'] ?? null,
                'away_team_id' => $fixture['teams']['away']['id'] ?? null,
                'home_logo' => $fixture['teams']['home']['logo'] ?? null,
                'away_logo' => $fixture['teams']['away']['logo'] ?? null,
                'elapsed' => $fixture['fixture']['status']['elapsed'] ?? null,
                'short_status' => $fixture['fixture']['status']['short'] ?? null,
                'home_score' => $fixture['goals']['home'] ?? 0,
                'away_score' => $fixture['goals']['away'] ?? 0,
            ])
            ->filter(fn (array $item) => $item['id'])
            ->values()
            ->all();

        $this->snapshotService->put('live', $items);

        return count($items);
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
        $items = collect($this->apiFootballService->transfers($this->apiFootballService->resolveTeamIdsForHighlights()))
            ->take(18)
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

    public function syncPredictionMetadata(): int
    {
        $competitions = collect($this->snapshotService->get('competitions'));
        $countries = Country::query()->get()->keyBy(fn (Country $country) => strtolower($country->name));
        $updated = 0;

        Prediction::query()->chunkById(100, function ($predictions) use ($competitions, $countries, &$updated) {
            foreach ($predictions as $prediction) {
                $league = $competitions->firstWhere('id', $prediction->league_id);
                $country = $prediction->country_name
                    ? $countries->get(strtolower($prediction->country_name))
                    : null;

                $attributes = array_filter([
                    'league_logo' => $prediction->league_logo ?: ($league['logo'] ?? null),
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

        $fixtures = collect($this->apiFootballService->fixtures([
            'live' => 'all',
            'timezone' => 'Africa/Lagos',
        ])['response'] ?? [])
            ->unique(fn (array $fixture) => $fixture['fixture']['id'] ?? null)
            ->filter(fn (array $fixture) => ! empty($fixture['fixture']['id']))
            ->take(12)
            ->values();

        $created = 0;

        foreach ($fixtures as $fixture) {
            try {
                $fixtureId = $fixture['fixture']['id'] ?? null;

                if (! $fixtureId) {
                    continue;
                }

                $predictionPayload = collect($this->apiFootballService->fixturePrediction($fixtureId)['response'] ?? [])->first();
                $mapped = $this->mapAiPrediction($fixture, $predictionPayload);

                Prediction::updateOrCreate(
                    [
                        'fixture_id' => $fixtureId,
                        'source' => 'api_football',
                        'category' => 'ai_prediction',
                    ],
                    [
                        ...$mapped,
                        'user_id' => $admin->id,
                        'status' => 'published',
                        'scope' => 'editorial',
                        'source' => 'api_football',
                        'category' => 'ai_prediction',
                        'published_at' => now(),
                    ]
                );

                $created++;
            } catch (\Throwable) {
                continue;
            }
        }

        return $created;
    }

    public function syncEditorialHighlightsFromLive(): int
    {
        $admin = User::query()->where('email', 'ozorclinton@gmail.com')->first();

        if (! $admin) {
            return 0;
        }

        $fixtures = collect($this->apiFootballService->fixtures([
            'live' => 'all',
            'timezone' => 'Africa/Lagos',
        ])['response'] ?? [])->take(4)->values();

        $categories = ['today_prediction', 'popular_matches', 'football_trend'];
        $written = 0;

        foreach ($categories as $index => $category) {
            $fixture = $fixtures->get($index);

            if (! $fixture || empty($fixture['fixture']['id'])) {
                continue;
            }

            try {
                $predictionPayload = collect($this->apiFootballService->fixturePrediction($fixture['fixture']['id'])['response'] ?? [])->first();
                $mapped = $this->mapAiPrediction($fixture, $predictionPayload);

                Prediction::updateOrCreate(
                    [
                        'fixture_id' => $fixture['fixture']['id'],
                        'category' => $category,
                    ],
                    [
                        ...$mapped,
                        'user_id' => $admin->id,
                        'status' => 'published',
                        'scope' => 'editorial',
                        'source' => 'api_football',
                        'category' => $category,
                        'published_at' => now(),
                    ]
                );

                $written++;
            } catch (\Throwable) {
                continue;
            }
        }

        return $written;
    }

    protected function mapAiPrediction(array $fixture, ?array $prediction): array
    {
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
            'league_logo' => $fixture['league']['logo'] ?? null,
            'country_name' => str((string) ($fixture['league']['country'] ?? ''))->limit(255)->toString(),
            'country_code' => null,
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

        return ['AI Prediction', str((string) ($winner ?? $underOver ?? 'AI Tip'))->limit(100)->toString()];
    }

    protected function attempt(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable) {
            return 0;
        }
    }
}
