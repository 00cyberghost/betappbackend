<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\ApiFootballService;
use App\Services\FootballSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PredictionFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = trim((string) $request->string('category'));
        $limit = max(1, min(50, $request->integer('limit') ?: 10));

        $predictions = Prediction::query()
            ->with([
                'comments' => fn ($query) => $query->latest()->limit(5)->with('user:id,name,avatar_url'),
                'user:id,name,avatar_url',
            ])
            ->where('status', 'published')
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->latest('published_at')
            ->paginate($limit);

        return response()->json([
            ...$predictions->toArray(),
            'data' => collect($predictions->items())
                ->map(fn (Prediction $prediction) => $this->transformPrediction($prediction))
                ->values(),
        ]);
    }

    public function show(Prediction $prediction, ApiFootballService $apiFootballService): JsonResponse
    {
        $prediction->load([
            'user:id,name,avatar_url',
            'comments' => fn ($query) => $query->latest()->with('user:id,name,avatar_url'),
        ]);

        return response()->json($this->transformPrediction($prediction, true, $apiFootballService));
    }

    public function home(FootballSnapshotService $snapshotService): JsonResponse
    {
        $sections = [
            'today_prediction' => $this->predictionCollection('today_prediction', 8),
            'ai_prediction' => $this->predictionCollection('ai_prediction', 8),
            'upcoming_matches' => $this->predictionCollection('upcoming_matches', 8),
            'football_trend' => $this->predictionCollection('football_trend', 8),
            'popular_matches' => $this->predictionCollection('popular_matches', 8),
            'community_prediction' => $this->predictionCollection('community_prediction', 8),
        ];

        return response()->json([
            'data' => [
                'sections' => $sections,
                'live_scores' => $snapshotService->get('live'),
                'competitions' => $snapshotService->get('competitions'),
                'updates' => $snapshotService->get('updates'),
            ],
        ]);
    }

    protected function transformPrediction(Prediction $prediction, bool $detailed = false, ?ApiFootballService $apiFootballService = null): array
    {
        $payload = [
            'id' => $prediction->id,
            'fixture_id' => $prediction->fixture_id,
            'league_id' => $prediction->league_id,
            'league_name' => $prediction->league_name,
            'country_name' => $prediction->country_name,
            'home_team_name' => $prediction->home_team_name,
            'home_team_logo' => $prediction->home_team_logo,
            'away_team_name' => $prediction->away_team_name,
            'away_team_logo' => $prediction->away_team_logo,
            'match_starts_at' => optional($prediction->match_starts_at)?->toIso8601String(),
            'prediction_type' => $prediction->prediction_type,
            'prediction_value' => $prediction->prediction_value,
            'probability' => $prediction->probability,
            'odds' => $prediction->odds,
            'analysis' => $prediction->analysis,
            'status' => $prediction->status,
            'scope' => $prediction->scope,
            'source' => $prediction->source,
            'category' => $prediction->category,
            'likes_count' => $prediction->likes_count,
            'comments_count' => $prediction->comments_count,
            'published_at' => optional($prediction->published_at)?->toIso8601String(),
            'author' => $prediction->user ? [
                'name' => $prediction->user->name,
                'avatar_url' => $prediction->user->avatar_url,
            ] : null,
            'comments' => $detailed
                ? $prediction->comments->map(fn ($comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'created_at' => optional($comment->created_at)?->toIso8601String(),
                    'user' => $comment->user ? [
                        'name' => $comment->user->name,
                        'avatar_url' => $comment->user->avatar_url,
                    ] : null,
                ])->values()
                : [],
        ];

        if ($detailed) {
            $payload['football_data'] = $this->fixtureFootballData($prediction, $apiFootballService);
        }

        return $payload;
    }

    protected function predictionCollection(string $category, int $limit): array
    {
        return Prediction::query()
            ->with(['comments' => fn ($query) => $query->latest()->limit(5)->with('user:id,name,avatar_url'), 'user:id,name,avatar_url'])
            ->where('status', 'published')
            ->where('category', $category)
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Prediction $prediction) => $this->transformPrediction($prediction))
            ->values()
            ->all();
    }

    protected function fixtureFootballData(Prediction $prediction, ?ApiFootballService $apiFootballService): array
    {
        if (! $apiFootballService || ! $prediction->fixture_id) {
            return [
                'summary' => null,
                'lineups' => [],
                'statistics' => [],
                'head_to_head' => [],
                'standings' => [],
            ];
        }

        try {
            $fixturePayload = $apiFootballService->fixtures([
                'id' => $prediction->fixture_id,
                'timezone' => 'Africa/Lagos',
            ]);

            $fixture = collect($fixturePayload['response'] ?? [])->first();

            if (! $fixture) {
                return [
                    'summary' => null,
                    'lineups' => [],
                    'statistics' => [],
                    'head_to_head' => [],
                    'standings' => [],
                ];
            }

            $leagueId = $prediction->league_id ?: ($fixture['league']['id'] ?? null);
            $season = $fixture['league']['season'] ?? null;
            $homeTeamId = $prediction->home_team_id ?: ($fixture['teams']['home']['id'] ?? null);
            $awayTeamId = $prediction->away_team_id ?: ($fixture['teams']['away']['id'] ?? null);
            $predictionPayload = collect($apiFootballService->fixturePrediction((int) $prediction->fixture_id)['response'] ?? [])->first();

            return [
                'summary' => [
                    'fixture' => $fixture,
                    'prediction' => $predictionPayload,
                    'events' => $apiFootballService->fixtureEvents((int) $prediction->fixture_id)['response'] ?? [],
                ],
                'lineups' => $apiFootballService->fixtureLineups((int) $prediction->fixture_id)['response'] ?? [],
                'statistics' => $apiFootballService->fixtureStatistics((int) $prediction->fixture_id)['response'] ?? [],
                'head_to_head' => $homeTeamId && $awayTeamId
                    ? ($apiFootballService->headToHead((int) $homeTeamId, (int) $awayTeamId)['response'] ?? [])
                    : [],
                'standings' => $leagueId && $season
                    ? collect($apiFootballService->standings((int) $leagueId, (int) $season)['response'] ?? [])->first()['league']['standings'][0] ?? []
                    : [],
            ];
        } catch (\Throwable) {
            return [
                'summary' => null,
                'lineups' => [],
                'statistics' => [],
                'head_to_head' => [],
                'standings' => [],
            ];
        }
    }
}
