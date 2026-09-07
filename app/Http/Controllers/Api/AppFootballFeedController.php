<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ApiFootballService;
use App\Services\FootballSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppFootballFeedController extends Controller
{
    public function live(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $limit = max(1, min(20, $request->integer('limit') ?: 20));
        $payload = $apiFootballService->freshFixtures(array_filter([
            'live' => 'all',
            'league' => $request->integer('league') ?: null,
            'timezone' => $request->string('timezone')->toString() ?: 'Africa/Lagos',
        ]));

        $countries = Country::query()
            ->get()
            ->keyBy(fn (Country $country) => $this->normalizeCountryName($country->name));

        $items = collect($payload['response'] ?? [])->map(function (array $fixture) use ($countries) {
            $country = $countries->get($this->normalizeCountryName($fixture['league']['country'] ?? null));

            return [
                'id' => $fixture['fixture']['id'] ?? null,
                'league' => $fixture['league']['name'] ?? 'League',
                'league_id' => $fixture['league']['id'] ?? null,
                'league_logo' => $fixture['league']['logo'] ?: ($country?->flag ?? null),
                'country' => $fixture['league']['country'] ?? null,
                'country_code' => $country?->code,
                'country_flag' => $country?->flag,
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
            ];
        })
            ->filter(fn (array $item) => $item['id'])
            ->take($limit)
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'cached' => false,
                'fetched_at' => now('Africa/Lagos')->toIso8601String(),
                'limit' => $limit,
                'returned' => $items->count(),
            ],
        ]);
    }

    public function liveDetail(int $fixture, Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $fixturePayload = $apiFootballService->fixtures([
            'id' => $fixture,
            'timezone' => $request->string('timezone')->toString() ?: null,
        ]);

        $fixtureItem = collect($fixturePayload['response'] ?? [])->first();

        if (! $fixtureItem) {
            return response()->json(['message' => 'Fixture not found.'], 404);
        }

        $leagueId = $fixtureItem['league']['id'] ?? null;
        $season = $fixtureItem['league']['season'] ?? null;
        $homeTeamId = $fixtureItem['teams']['home']['id'] ?? null;
        $awayTeamId = $fixtureItem['teams']['away']['id'] ?? null;

        return response()->json([
            'data' => [
                'fixture' => $fixtureItem,
                'prediction' => collect($apiFootballService->fixturePrediction($fixture)['response'] ?? [])->first(),
                'lineups' => $apiFootballService->fixtureLineups($fixture)['response'] ?? [],
                'statistics' => $apiFootballService->fixtureStatistics($fixture)['response'] ?? [],
                'events' => $apiFootballService->fixtureEvents($fixture)['response'] ?? [],
                'head_to_head' => $homeTeamId && $awayTeamId
                    ? ($apiFootballService->headToHead($homeTeamId, $awayTeamId)['response'] ?? [])
                    : [],
                'standings' => $leagueId && $season
                    ? collect($apiFootballService->standings($leagueId, $season)['response'] ?? [])->first()['league']['standings'][0] ?? []
                    : [],
            ],
        ]);
    }

    public function competitions(ApiFootballService $apiFootballService, FootballSnapshotService $snapshotService): JsonResponse
    {
        $payload = $apiFootballService->leagues();
        $items = collect($payload['response'] ?? [])
            ->map(fn (array $league) => $this->mapCompetition($league))
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->values();

        $topLeagues = $this->topLeagues($apiFootballService, $items);

        return response()->json([
            'data' => $items,
            'top_leagues' => $topLeagues,
            'countries' => $this->competitionCountries($items),
            'latest_matches' => $this->latestLeagueMatches($apiFootballService, $topLeagues),
        ]);
    }

    public function competitionDetail(int $league, Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $season = $request->integer('season') ?: now()->year;

        $standingsPayload = $apiFootballService->standings($league, $season);
        $standingsGroup = collect($standingsPayload['response'] ?? [])->first()['league']['standings'][0] ?? [];

        $resultsPayload = $apiFootballService->fixtures(array_filter([
            'league' => $league,
            'season' => $season,
            'last' => 10,
            'timezone' => $request->string('timezone')->toString() ?: null,
        ]));

        $fixturesPayload = $apiFootballService->fixtures(array_filter([
            'league' => $league,
            'season' => $season,
            'next' => 10,
            'timezone' => $request->string('timezone')->toString() ?: null,
        ]));

        $scorersPayload = $apiFootballService->topScorers($league, $season);
        $leagueInfo = collect($standingsPayload['response'] ?? [])->first()['league'] ?? null;

        return response()->json([
            'data' => [
                'league' => [
                    'id' => $leagueInfo['id'] ?? $league,
                    'name' => $leagueInfo['name'] ?? 'Competition',
                    'country' => $leagueInfo['country'] ?? $request->string('country')->toString() ?: 'Unknown',
                    'logo' => $leagueInfo['logo'] ?? $request->string('logo')->toString() ?: null,
                    'flag' => $leagueInfo['flag'] ?? null,
                    'season' => $leagueInfo['season'] ?? $season,
                ],
                'standings' => $standingsGroup,
                'results' => $resultsPayload['response'] ?? [],
                'fixtures' => $fixturesPayload['response'] ?? [],
                'top_scorers' => $scorersPayload['response'] ?? [],
            ],
        ]);
    }

    public function updates(Request $request, ApiFootballService $apiFootballService, FootballSnapshotService $snapshotService): JsonResponse
    {
        $payload = $snapshotService->get('updates');

        if ($payload === []) {
            $payload = $this->updatesCollection($apiFootballService);
        }

        $items = collect($payload)
            ->when($request->string('category')->toString() !== '', function ($updates) use ($request) {
                $category = $request->string('category')->toString();

                if ($category === 'Latest') {
                    return $updates;
                }

                if ($category === 'Transfer') {
                    return $updates->where('source_type', 'transfer');
                }

                return $updates->where('category', $category);
            })
            ->take(12)
            ->values();

        return response()->json(['data' => $items]);
    }

    public function updateDetail(string $update, ApiFootballService $apiFootballService): JsonResponse
    {
        $item = collect($this->updatesCollection($apiFootballService))
            ->firstWhere('id', $update);

        if (! $item) {
            return response()->json(['message' => 'Update not found.'], 404);
        }

        return response()->json(['data' => $item]);
    }

    protected function updatesCollection(ApiFootballService $apiFootballService): array
    {
        return $apiFootballService->footballUpdates($apiFootballService->resolveTeamIdsForHighlights());
    }

    protected function mapCompetition(array $league): array
    {
        return [
            'id' => $league['league']['id'] ?? null,
            'name' => $league['league']['name'] ?? null,
            'type' => $league['league']['type'] ?? null,
            'logo' => $league['league']['logo'] ?? null,
            'country' => $league['country']['name'] ?? null,
            'flag' => $league['country']['flag'] ?? null,
            'season' => collect($league['seasons'] ?? [])->firstWhere('current', true)['year'] ?? null,
        ];
    }

    protected function topLeagues(ApiFootballService $apiFootballService, $items)
    {
        $topIds = [39, 2, 3, 848, 140, 135, 78, 61, 253, 71, 307, 128, 262, 94, 203];

        $byId = $items->keyBy('id');

        return collect($topIds)
            ->map(function (int $id) use ($apiFootballService, $byId) {
                $league = $byId->get($id);

                if ($league) {
                    return $league;
                }

                try {
                    $payload = $apiFootballService->leagueById($id);

                    return collect($payload['response'] ?? [])
                        ->map(fn (array $item) => $this->mapCompetition($item))
                        ->first();
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
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

    protected function latestLeagueMatches(ApiFootballService $apiFootballService, $topLeagues): array
    {
        return $topLeagues
            ->take(6)
            ->flatMap(function (array $league) use ($apiFootballService) {
                if (empty($league['id']) || empty($league['season'])) {
                    return [];
                }

                try {
                    $payload = $apiFootballService->fixtures([
                        'league' => $league['id'],
                        'season' => $league['season'],
                        'last' => 3,
                        'timezone' => 'Africa/Lagos',
                    ]);
                } catch (\Throwable) {
                    return [];
                }

                return collect($payload['response'] ?? [])->map(function (array $fixture) use ($league) {
                    return [
                        'id' => $fixture['fixture']['id'] ?? null,
                        'league_id' => $league['id'],
                        'league_name' => $league['name'],
                        'league_logo' => $league['logo'] ?: ($league['flag'] ?? null),
                        'country' => $league['country'],
                        'flag' => $league['flag'] ?? null,
                        'season' => $league['season'],
                        'date' => $fixture['fixture']['date'] ?? null,
                        'status' => $fixture['fixture']['status']['short'] ?? null,
                        'home_team' => $fixture['teams']['home']['name'] ?? 'Home',
                        'away_team' => $fixture['teams']['away']['name'] ?? 'Away',
                        'home_logo' => $fixture['teams']['home']['logo'] ?? null,
                        'away_logo' => $fixture['teams']['away']['logo'] ?? null,
                        'home_score' => $fixture['goals']['home'] ?? null,
                        'away_score' => $fixture['goals']['away'] ?? null,
                    ];
                });
            })
            ->filter(fn (array $item) => $item['id'])
            ->sortByDesc('date')
            ->take(20)
            ->values()
            ->all();
    }

    protected function normalizeCountryName(?string $countryName): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim((string) $countryName)));
    }
}
