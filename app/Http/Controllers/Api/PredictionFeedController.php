<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Models\User;
use App\Services\ApiFootballService;
use App\Services\FootballSnapshotService;
use Carbon\Carbon;
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
                ->map(fn (Prediction $prediction) => $this->transformPrediction($prediction, false, null, $this->resolveAppUser($request)))
                ->values(),
        ]);
    }

    public function show(Request $request, Prediction $prediction, ApiFootballService $apiFootballService): JsonResponse
    {
        $prediction->load([
            'user:id,name,avatar_url',
            'comments' => fn ($query) => $query->latest()->with('user:id,name,avatar_url'),
        ]);

        return response()->json($this->transformPrediction($prediction, true, $apiFootballService, $this->resolveAppUser($request)));
    }

    public function home(FootballSnapshotService $snapshotService): JsonResponse
    {
        $selectedDate = $this->resolveSelectedDate(request());

        $sections = [
            'today_prediction' => $this->predictionCollection('today_prediction', 8, $selectedDate),
            'ai_prediction' => $this->predictionCollection('ai_prediction', 8, $selectedDate),
            'upcoming_matches' => $this->predictionCollection('upcoming_matches', 8),
            'football_trend' => $this->predictionCollection('football_trend', 8, $selectedDate),
            'popular_matches' => $this->predictionCollection('popular_matches', 8, $selectedDate),
            'community_prediction' => $this->predictionCollection('community_prediction', 8, $selectedDate),
        ];

        return response()->json([
            'data' => [
                'sections' => $sections,
                'selected_date' => $selectedDate->toDateString(),
                'live_scores' => $snapshotService->get('live'),
                'competitions' => $snapshotService->get('competitions'),
                'updates' => $snapshotService->get('updates'),
            ],
        ]);
    }

    protected function transformPrediction(Prediction $prediction, bool $detailed = false, ?ApiFootballService $apiFootballService = null, ?User $viewer = null): array
    {
        $payload = [
            'id' => $prediction->id,
            'fixture_id' => $prediction->fixture_id,
            'league_id' => $prediction->league_id,
            'league_name' => $prediction->league_name,
            'league_logo' => $prediction->league_logo,
            'country_name' => $prediction->country_name,
            'country_code' => $prediction->country_code,
            'home_team_name' => $prediction->home_team_name,
            'home_team_logo' => $prediction->home_team_logo,
            'away_team_name' => $prediction->away_team_name,
            'away_team_logo' => $prediction->away_team_logo,
            'match_starts_at' => optional($prediction->match_starts_at)?->toIso8601String(),
            'prediction_type' => $prediction->prediction_type,
            'prediction_value' => $prediction->prediction_value,
            'display_tip' => $this->displayTip($prediction),
            'predicted_score_home' => $prediction->predicted_score_home,
            'predicted_score_away' => $prediction->predicted_score_away,
            'probability' => $prediction->probability,
            'odds' => $prediction->odds,
            'analysis' => $prediction->analysis,
            'status' => $prediction->status,
            'scope' => $prediction->scope,
            'source' => $prediction->source,
            'category' => $prediction->category,
            'likes_count' => $prediction->likes_count,
            'comments_count' => $prediction->comments_count,
            'shares_count' => $prediction->shares_count,
            'liked_by_me' => $viewer ? $prediction->likes()->where('user_id', $viewer->id)->exists() : false,
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

    protected function resolveAppUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        return User::query()->where('api_token', hash('sha256', $token))->first();
    }

    protected function predictionCollection(string $category, int $limit, ?Carbon $selectedDate = null): array
    {
        return Prediction::query()
            ->with(['comments' => fn ($query) => $query->latest()->limit(5)->with('user:id,name,avatar_url'), 'user:id,name,avatar_url'])
            ->where('status', 'published')
            ->where('category', $category)
            ->when(
                $selectedDate && $category !== 'upcoming_matches',
                function ($query) use ($selectedDate) {
                    $query->whereDate('match_starts_at', $selectedDate->toDateString());
                }
            )
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Prediction $prediction) => $this->transformPrediction($prediction))
            ->values()
            ->all();
    }

    protected function resolveSelectedDate(Request $request): Carbon
    {
        $date = trim((string) $request->query('date', now('Africa/Lagos')->toDateString()));

        try {
            return Carbon::parse($date, 'Africa/Lagos')->startOfDay();
        } catch (\Throwable) {
            return now('Africa/Lagos')->startOfDay();
        }
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

    protected function displayTip(Prediction $prediction): string
    {
        $value = trim((string) $prediction->prediction_value);
        $type = strtolower(trim((string) $prediction->prediction_type));

        if (in_array(strtoupper($value), ['1', '2', 'X', '1X', 'X2', '12'], true)) {
            return strtoupper($value);
        }

        if (str_contains($type, 'over') || str_contains($type, 'under')) {
            return $value !== '' ? $value : ($prediction->prediction_type ?: 'Over/Under');
        }

        if (str_contains($type, 'btts')) {
            return $value !== '' ? strtoupper($value) : 'BTTS';
        }

        if ($prediction->predicted_score_home !== null && $prediction->predicted_score_away !== null) {
            if ($prediction->predicted_score_home > $prediction->predicted_score_away) {
                return '1';
            }

            if ($prediction->predicted_score_home < $prediction->predicted_score_away) {
                return '2';
            }

            return 'X';
        }

        if ($value !== '') {
            return mb_strlen($value) > 18 ? mb_substr($value, 0, 18) : $value;
        }

        return '-';
    }
}
