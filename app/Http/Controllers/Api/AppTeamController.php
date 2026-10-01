<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiFootballService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppTeamController extends Controller
{
    public function show(int $team, Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        $profile = collect($apiFootballService->teamById($team)['response'] ?? [])->first();

        if (! $profile) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $leagueId = $request->integer('league');
        $season = $request->integer('season');
        $timezone = $request->string('timezone')->toString() ?: 'Africa/Lagos';

        return response()->json([
            'data' => [
                'team' => $this->teamPayload($profile),
                'statistics' => $leagueId && $season
                    ? $this->safe(fn () => $this->statisticsPayload($apiFootballService->teamStatistics($team, $leagueId, $season)['response'] ?? []), [])
                    : [],
                'squad' => $this->safe(fn () => $this->squadPayload($apiFootballService->teamSquad($team)['response'] ?? []), []),
                'recent_fixtures' => $this->safe(fn () => $this->fixturesPayload($apiFootballService->fixtures([
                    'team' => $team,
                    'last' => 5,
                    'timezone' => $timezone,
                ])['response'] ?? []), []),
                'upcoming_fixtures' => $this->safe(fn () => $this->fixturesPayload($apiFootballService->fixtures([
                    'team' => $team,
                    'next' => 5,
                    'timezone' => $timezone,
                ])['response'] ?? []), []),
            ],
        ]);
    }

    protected function teamPayload(array $profile): array
    {
        return [
            'id' => $profile['team']['id'] ?? null,
            'name' => $profile['team']['name'] ?? null,
            'code' => $profile['team']['code'] ?? null,
            'country' => $profile['team']['country'] ?? null,
            'founded' => $profile['team']['founded'] ?? null,
            'national' => $profile['team']['national'] ?? false,
            'logo' => $profile['team']['logo'] ?? null,
            'venue' => [
                'id' => $profile['venue']['id'] ?? null,
                'name' => $profile['venue']['name'] ?? null,
                'address' => $profile['venue']['address'] ?? null,
                'city' => $profile['venue']['city'] ?? null,
                'capacity' => $profile['venue']['capacity'] ?? null,
                'surface' => $profile['venue']['surface'] ?? null,
                'image' => $profile['venue']['image'] ?? null,
            ],
        ];
    }

    protected function statisticsPayload(array $stats): array
    {
        if ($stats === []) {
            return [];
        }

        return [
            'form' => $stats['form'] ?? null,
            'played' => $stats['fixtures']['played'] ?? [],
            'wins' => $stats['fixtures']['wins'] ?? [],
            'draws' => $stats['fixtures']['draws'] ?? [],
            'loses' => $stats['fixtures']['loses'] ?? [],
            'goals_for' => $stats['goals']['for']['total'] ?? [],
            'goals_against' => $stats['goals']['against']['total'] ?? [],
            'clean_sheet' => $stats['clean_sheet'] ?? [],
            'failed_to_score' => $stats['failed_to_score'] ?? [],
            'league' => [
                'id' => $stats['league']['id'] ?? null,
                'name' => $stats['league']['name'] ?? null,
                'country' => $stats['league']['country'] ?? null,
                'logo' => $stats['league']['logo'] ?? null,
                'season' => $stats['league']['season'] ?? null,
            ],
        ];
    }

    protected function squadPayload(array $squads): array
    {
        $squad = collect($squads)->first();
        $players = is_array($squad) ? ($squad['players'] ?? []) : [];

        return collect($players)
            ->map(fn (array $player) => [
                'id' => $player['id'] ?? null,
                'name' => $player['name'] ?? null,
                'age' => $player['age'] ?? null,
                'number' => $player['number'] ?? null,
                'position' => $player['position'] ?? null,
                'photo' => $player['photo'] ?? null,
            ])
            ->filter(fn (array $player) => $player['id'] && $player['name'])
            ->values()
            ->all();
    }

    protected function fixturesPayload(array $fixtures): array
    {
        return collect($fixtures)
            ->map(fn (array $fixture) => [
                'id' => $fixture['fixture']['id'] ?? null,
                'date' => $fixture['fixture']['date'] ?? null,
                'status' => [
                    'short' => $fixture['fixture']['status']['short'] ?? null,
                    'long' => $fixture['fixture']['status']['long'] ?? null,
                    'elapsed' => $fixture['fixture']['status']['elapsed'] ?? null,
                ],
                'league' => [
                    'id' => $fixture['league']['id'] ?? null,
                    'name' => $fixture['league']['name'] ?? null,
                    'logo' => $fixture['league']['logo'] ?? null,
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
                'goals' => [
                    'home' => $fixture['goals']['home'] ?? null,
                    'away' => $fixture['goals']['away'] ?? null,
                ],
            ])
            ->filter(fn (array $fixture) => $fixture['id'])
            ->values()
            ->all();
    }

    protected function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
