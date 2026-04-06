<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiFootballService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FootballLookupController extends Controller
{
    public function countries(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        return response()->json(
            $apiFootballService->countries($request->string('search')->toString() ?: null)
        );
    }

    public function leagues(Request $request, ApiFootballService $apiFootballService): JsonResponse
    {
        return response()->json(
            $apiFootballService->leagues(
                $request->string('country')->toString() ?: null,
                $request->integer('season') ?: null,
            )
        );
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

        return response()->json($apiFootballService->fixtures($params));
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
