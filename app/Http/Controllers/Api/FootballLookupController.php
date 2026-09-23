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

    public function prediction(int $fixture, ApiFootballService $apiFootballService): JsonResponse
    {
        $prediction = collect($apiFootballService->fixturePrediction($fixture)['response'] ?? [])->first();
        $predictions = $prediction['predictions'] ?? [];
        $advice = trim((string) ($predictions['advice'] ?? ''));
        $underOver = trim((string) ($predictions['under_over'] ?? ''));
        $winnerName = trim((string) ($predictions['winner']['name'] ?? ''));
        $winnerComment = trim((string) ($predictions['winner']['comment'] ?? ''));
        $homeTeam = trim((string) ($prediction['teams']['home']['name'] ?? ''));
        $awayTeam = trim((string) ($prediction['teams']['away']['name'] ?? ''));
        $percentages = collect($predictions['percent'] ?? []);

        [$predictionType, $predictionValue] = $this->predictionTypeAndValueFromApiFootball(
            advice: $advice,
            underOver: $underOver,
            winnerName: $winnerName,
            winnerComment: $winnerComment,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
        );

        return response()->json([
            'data' => [
                'available' => $predictionType !== '' || $predictionValue !== '',
                'prediction_type' => $predictionType,
                'prediction_value' => $predictionValue,
                'probability' => $this->highestProbability($percentages),
                'analysis' => $this->predictionAnalysis($advice, $underOver, $winnerName, $winnerComment),
                'raw' => $prediction,
            ],
        ]);
    }

    protected function predictionTypeAndValueFromApiFootball(
        string $advice,
        string $underOver,
        string $winnerName,
        string $winnerComment,
        string $homeTeam,
        string $awayTeam,
    ): array
    {
        if ($advice !== '') {
            $parts = preg_split('/\s*:\s*/', $advice, 2);

            if (is_array($parts) && count($parts) === 2) {
                $mapped = $this->mappedMarketFromAdvice(trim($parts[0]), trim($parts[1]), $homeTeam, $awayTeam);

                if ($mapped) {
                    return $mapped;
                }

                if ($underOver !== '') {
                    return $this->underOverMarket($underOver);
                }

                return [trim($parts[0]), trim($parts[1])];
            }

            $mapped = $this->mappedMarketFromAdvice('', $advice, $homeTeam, $awayTeam);

            if ($mapped) {
                return $mapped;
            }
        }

        if ($underOver !== '') {
            return $this->underOverMarket($underOver);
        }

        if ($winnerName !== '' && $winnerComment !== '') {
            $mapped = $this->mappedWinnerMarket($winnerName, $homeTeam, $awayTeam, $winnerComment);

            if ($mapped) {
                return $mapped;
            }

            return [$winnerComment, $winnerName];
        }

        if ($winnerName !== '') {
            return $this->mappedWinnerMarket($winnerName, $homeTeam, $awayTeam) ?? ['Winner', $winnerName];
        }

        return ['', ''];
    }

    protected function mappedMarketFromAdvice(string $market, string $value, string $homeTeam, string $awayTeam): ?array
    {
        $marketKey = $this->normalizeMarketText($market);
        $valueKey = $this->normalizeMarketText($value);

        if (str_contains($marketKey, 'double chance')) {
            $hasDraw = str_contains($valueKey, 'draw');
            $hasHome = $this->mentionsSide($valueKey, 'home', $homeTeam);
            $hasAway = $this->mentionsSide($valueKey, 'away', $awayTeam);

            if ($hasHome && $hasDraw) {
                return ['Double Chance', '1X'];
            }

            if ($hasAway && $hasDraw) {
                return ['Double Chance', 'X2'];
            }

            if ($hasHome && $hasAway) {
                return ['Double Chance', '12'];
            }
        }

        if (str_contains($marketKey, 'winner') || str_contains($marketKey, 'match winner')) {
            return $this->mappedWinnerMarket($value, $homeTeam, $awayTeam);
        }

        if (str_contains($marketKey, 'both teams') || str_contains($marketKey, 'btts')) {
            if (str_contains($valueKey, 'yes') || str_contains($valueKey, 'gg')) {
                return ['Both Teams To Score', 'BTTS Yes'];
            }

            if (str_contains($valueKey, 'no') || str_contains($valueKey, 'ng')) {
                return ['Both Teams To Score', 'BTTS No'];
            }
        }

        if (str_contains($marketKey.' '.$valueKey, 'under') || str_contains($marketKey.' '.$valueKey, 'over')) {
            return $this->underOverMarket($value);
        }

        return null;
    }

    protected function mappedWinnerMarket(string $winner, string $homeTeam, string $awayTeam, string $comment = ''): ?array
    {
        $winnerKey = $this->normalizeMarketText($winner);
        $commentKey = $this->normalizeMarketText($comment);

        if ($winnerKey === 'draw') {
            return ['1X2', 'X'];
        }

        if ($this->mentionsSide($winnerKey, 'home', $homeTeam)) {
            return str_contains($commentKey, 'draw') ? ['Double Chance', '1X'] : ['1X2', '1'];
        }

        if ($this->mentionsSide($winnerKey, 'away', $awayTeam)) {
            return str_contains($commentKey, 'draw') ? ['Double Chance', 'X2'] : ['1X2', '2'];
        }

        return null;
    }

    protected function underOverMarket(string $value): array
    {
        if (preg_match('/\b(under|over)\s+(\d+(?:\.\d+)?)\b/i', $value, $matches) === 1) {
            return ["Under/Over {$matches[2]}", ucfirst(strtolower($matches[1]))." {$matches[2]}"];
        }

        return ['Under/Over', $value];
    }

    protected function mentionsSide(string $value, string $side, string $teamName): bool
    {
        if (str_contains($value, $side)) {
            return true;
        }

        return $teamName !== '' && str_contains($value, $this->normalizeMarketText($teamName));
    }

    protected function normalizeMarketText(string $value): string
    {
        $normalized = strtolower($value);
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    protected function highestProbability(\Illuminate\Support\Collection $percentages): ?int
    {
        $probability = $percentages
            ->map(fn ($value) => (int) rtrim((string) $value, '%'))
            ->max();

        return $probability ? (int) $probability : null;
    }

    protected function predictionAnalysis(string $advice, string $underOver, string $winnerName, string $winnerComment): string
    {
        return collect([
            $advice !== '' ? "{$advice}." : null,
            $winnerName !== '' ? "Winner lean: {$winnerName}".($winnerComment !== '' ? " ({$winnerComment})" : '').'.' : null,
            $underOver !== '' ? "Suggested total market: {$underOver}." : null,
        ])->filter()->implode(' ');
    }
}
