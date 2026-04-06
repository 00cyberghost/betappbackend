<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiFootballService;
use App\Services\FootballSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppFootballFeedController extends Controller
{
    public function live(Request $request, ApiFootballService $apiFootballService, FootballSnapshotService $snapshotService): JsonResponse
    {
        if (! $request->hasAny(['league', 'timezone'])) {
            $cached = $snapshotService->get('live');

            if ($cached !== []) {
                return response()->json(['data' => $cached]);
            }
        }

        $payload = $apiFootballService->fixtures(array_filter([
            'live' => 'all',
            'league' => $request->integer('league') ?: null,
            'timezone' => $request->string('timezone')->toString() ?: null,
        ]));

        $items = collect($payload['response'] ?? [])->map(function (array $fixture) {
            return [
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
            ];
        })->values();

        return response()->json(['data' => $items]);
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
        $cached = $snapshotService->get('competitions');

        if ($cached !== []) {
            return response()->json(['data' => $cached]);
        }

        $payload = $apiFootballService->leagues();

        $items = collect($payload['response'] ?? [])
            ->map(function (array $league) {
                return [
                    'id' => $league['league']['id'] ?? null,
                    'name' => $league['league']['name'] ?? null,
                    'type' => $league['league']['type'] ?? null,
                    'logo' => $league['league']['logo'] ?? null,
                    'country' => $league['country']['name'] ?? null,
                    'flag' => $league['country']['flag'] ?? null,
                    'season' => collect($league['seasons'] ?? [])->firstWhere('current', true)['year'] ?? null,
                ];
            })
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->values();

        return response()->json(['data' => $items]);
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

    public function updates(ApiFootballService $apiFootballService, FootballSnapshotService $snapshotService): JsonResponse
    {
        $payload = $snapshotService->get('updates');

        if ($payload === []) {
            $payload = $this->updatesCollection($apiFootballService);
        }

        $items = collect($payload)
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
        return $apiFootballService->transfers($apiFootballService->resolveTeamIdsForHighlights());
    }
}
