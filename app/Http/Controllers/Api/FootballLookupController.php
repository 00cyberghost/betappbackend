<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ApiFootballService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FootballLookupController extends Controller
{
    public function countries(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));

        $items = Country::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($search !== '' ? 100 : 300)
            ->get(['id', 'name', 'code', 'flag']);

        return response()->json(['data' => $items]);
    }

    public function leagues(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $code = trim((string) $request->string('code'));
        $search = trim((string) $request->string('search'));

        $payload = $code !== ''
            ? $apiFootballService->leaguesByCode($code, $request->integer('season') ?: null)
            : ($search !== ''
                ? $apiFootballService->searchLeagues($search)
                : $apiFootballService->leagues(
                    $request->string('country')->toString() ?: null,
                    $request->integer('season') ?: null,
                ));

        $items = collect($payload['response'] ?? [])
            ->map(fn (array $item) => [
                'id' => $item['league']['id'] ?? null,
                'name' => $item['league']['name'] ?? null,
                'logo' => $item['league']['logo'] ?? null,
                'country' => $item['country']['name'] ?? null,
                'country_code' => $item['country']['code'] ?? null,
                'season' => collect($item['seasons'] ?? [])->sortByDesc('year')->first()['year'] ?? null,
            ])
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function teams(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $code = trim((string) $request->string('code'));
        $search = trim((string) $request->string('search'));
        $league = $request->integer('league');
        $season = $request->integer('season');

        $params = array_filter([
            'code' => $code !== '' ? strtoupper($code) : null,
            'league' => $league ?: null,
            'season' => $league ? ($season ?: null) : null,
            'search' => $search !== '' ? $search : null,
        ]);

        $payload = $apiFootballService->teams($params);
        $items = collect($payload['response'] ?? [])
            ->map(fn (array $item) => [
                'id' => $item['team']['id'] ?? null,
                'name' => $item['team']['name'] ?? null,
                'logo' => $item['team']['logo'] ?? null,
                'country' => $item['team']['country'] ?? null,
                'code' => $item['team']['code'] ?? null,
            ])
            ->filter(fn (array $item) => $item['id'] && $item['name'])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function fixtures(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $params = array_filter([
            'league' => $request->integer('league') ?: null,
            'season' => $request->integer('season') ?: null,
            'date' => $request->string('date')->toString() ?: null,
            'team' => $request->integer('team') ?: null,
            'live' => $request->boolean('live') ? 'all' : null,
            'timezone' => $request->string('timezone')->toString() ?: null,
        ]);

        $payload = $apiFootballService->fixtures($params);

        $items = collect($payload['response'] ?? [])
            ->map(fn (array $fixture) => [
                'id' => $fixture['fixture']['id'] ?? null,
                'date' => $fixture['fixture']['date'] ?? null,
                'timestamp' => $fixture['fixture']['timestamp'] ?? null,
                'status' => $fixture['fixture']['status']['short'] ?? null,
                'league' => [
                    'id' => $fixture['league']['id'] ?? null,
                    'name' => $fixture['league']['name'] ?? null,
                    'logo' => $fixture['league']['logo'] ?? null,
                    'country' => $fixture['league']['country'] ?? null,
                    'flag' => $fixture['league']['flag'] ?? null,
                    'season' => $fixture['league']['season'] ?? null,
                ],
                'teams' => [
                    'home' => [
                        'id' => $fixture['teams']['home']['id'] ?? null,
                        'name' => $fixture['teams']['home']['name'] ?? null,
                        'logo' => $fixture['teams']['home']['logo'] ?? null,
                    ],
                    'away' => [
                        'id' => $fixture['teams']['away']['id'] ?? null,
                        'name' => $fixture['teams']['away']['name'] ?? null,
                        'logo' => $fixture['teams']['away']['logo'] ?? null,
                    ],
                ],
                'score' => [
                    'home' => $fixture['goals']['home'] ?? null,
                    'away' => $fixture['goals']['away'] ?? null,
                ],
                'label' => trim(sprintf(
                    '%s vs %s',
                    $fixture['teams']['home']['name'] ?? 'Home',
                    $fixture['teams']['away']['name'] ?? 'Away',
                )),
            ])
            ->filter(fn (array $fixture) => $fixture['id'] && $fixture['teams']['home']['name'] && $fixture['teams']['away']['name'])
            ->values();

        return response()->json([
            'data' => $items,
            'results' => $payload['results'] ?? $items->count(),
        ]);
    }

    public function details(int $fixture, Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $leagueId = $request->integer('league');
        $season = $request->integer('season');
        $homeTeam = $request->integer('home_team');
        $awayTeam = $request->integer('away_team');

        return response()->json([
            'fixture' => $apiFootballService->fixtures([
                'id' => $fixture,
                'timezone' => $request->string('timezone')->toString() ?: null,
            ]),
            'prediction' => $apiFootballService->fixturePrediction($fixture),
            'lineups' => $apiFootballService->fixtureLineups($fixture),
            'statistics' => $apiFootballService->fixtureStatistics($fixture),
            'events' => $apiFootballService->fixtureEvents($fixture),
            'headToHead' => $homeTeam && $awayTeam
                ? $apiFootballService->headToHead($homeTeam, $awayTeam)
                : ['response' => []],
            'standings' => $leagueId && $season
                ? $apiFootballService->standings($leagueId, $season)
                : ['response' => []],
        ]);
    }
}
