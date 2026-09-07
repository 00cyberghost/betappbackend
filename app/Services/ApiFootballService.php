<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiFootballService
{
    public function countries(?string $search = null): array
    {
        return $this->cached('countries:'.($search ?? 'all'), 86400, function () use ($search) {
            return $this->get('countries', array_filter([
                'search' => $search,
            ]));
        });
    }

    public function leagues(?string $country = null, ?int $season = null, bool $current = true): array
    {
        return $this->cached(
            sprintf('leagues:%s:%s:%s', $country ?? 'all', $season ?? 'current', $current ? '1' : '0'),
            21600,
            fn () => $this->get('leagues', array_filter([
                'country' => $country,
                'season' => $season,
                'current' => $current ? 'true' : null,
            ]))
        );
    }

    public function leaguesByCode(string $code, ?int $season = null, bool $current = true): array
    {
        return $this->cached(
            sprintf('leagues-by-code:%s:%s:%s', strtoupper($code), $season ?? 'current', $current ? '1' : '0'),
            21600,
            fn () => $this->get('leagues', array_filter([
                'code' => strtoupper($code),
                'season' => $season,
                'current' => $current ? 'true' : null,
            ]))
        );
    }

    public function searchLeagues(string $search): array
    {
        return $this->cached(
            'leagues-search:'.md5($search),
            21600,
            fn () => $this->get('leagues', [
                'search' => $search,
            ])
        );
    }

    public function leagueById(int $leagueId, ?int $season = null, bool $current = true): array
    {
        return $this->cached(
            sprintf('league-by-id:%s:%s:%s', $leagueId, $season ?? 'current', $current ? '1' : '0'),
            21600,
            fn () => $this->get('leagues', array_filter([
                'id' => $leagueId,
                'season' => $season,
                'current' => $current ? 'true' : null,
            ]))
        );
    }

    public function teams(array $params): array
    {
        ksort($params);

        return $this->cached('teams:'.md5(json_encode($params)), 21600, fn () => $this->get('teams', $params));
    }

    public function fixtures(array $params): array
    {
        ksort($params);

        return $this->cached('fixtures:'.md5(json_encode($params)), 900, fn () => $this->get('fixtures', $params));
    }

    public function freshFixtures(array $params): array
    {
        ksort($params);

        return $this->get('fixtures', $params);
    }

    public function fixturePrediction(int $fixtureId): array
    {
        return $this->cached("predictions:{$fixtureId}", 1800, fn () => $this->get('predictions', [
            'fixture' => $fixtureId,
        ]));
    }

    public function fixtureLineups(int $fixtureId): array
    {
        return $this->cached("lineups:{$fixtureId}", 900, fn () => $this->get('fixtures/lineups', [
            'fixture' => $fixtureId,
        ]));
    }

    public function fixtureStatistics(int $fixtureId): array
    {
        return $this->cached("statistics:{$fixtureId}", 900, fn () => $this->get('fixtures/statistics', [
            'fixture' => $fixtureId,
        ]));
    }

    public function fixtureEvents(int $fixtureId): array
    {
        return $this->cached("events:{$fixtureId}", 120, fn () => $this->get('fixtures/events', [
            'fixture' => $fixtureId,
        ]));
    }

    public function headToHead(int $homeTeamId, int $awayTeamId, int $last = 10): array
    {
        return $this->cached("h2h:{$homeTeamId}:{$awayTeamId}:{$last}", 21600, fn () => $this->get('fixtures/headtohead', [
            'h2h' => "{$homeTeamId}-{$awayTeamId}",
            'last' => $last,
        ]));
    }

    public function standings(int $leagueId, int $season): array
    {
        return $this->cached("standings:{$leagueId}:{$season}", 1800, fn () => $this->get('standings', [
            'league' => $leagueId,
            'season' => $season,
        ]));
    }

    public function topScorers(int $leagueId, int $season): array
    {
        return $this->cached("topscorers:{$leagueId}:{$season}", 1800, fn () => $this->get('players/topscorers', [
            'league' => $leagueId,
            'season' => $season,
        ]));
    }

    public function transfers(array $teamIds): array
    {
        return collect($teamIds)
            ->flatMap(function (int $teamId) {
                try {
                    $response = $this->cached("transfers:{$teamId}", 21600, fn () => $this->get('transfers', [
                        'team' => $teamId,
                    ]));
                } catch (\Throwable) {
                    return [];
                }

                return collect($response['response'] ?? [])->map(function (array $transfer) use ($teamId) {
                    $latestTransfer = collect($transfer['transfers'] ?? [])->first();
                    $playerId = $transfer['player']['id'] ?? null;
                    $date = $latestTransfer['date'] ?? null;
                    $transferId = implode('-', array_filter([$teamId, $playerId, $date]));
                    $fromTeam = $latestTransfer['teams']['out']['name'] ?? 'their previous club';
                    $toTeam = $latestTransfer['teams']['in']['name'] ?? 'a new club';
                    $playerName = $transfer['player']['name'] ?? 'Player';
                    $title = $playerName.' transfer update';
                    $body = $playerName.' is linked with '.$toTeam.' from '.$fromTeam.'.';

                    return [
                        'id' => 'transfer-'.md5($transferId),
                        'title' => $title,
                        'body' => $body,
                        'date' => $date,
                        'player_name' => $playerName,
                        'player_photo' => $transfer['player']['photo'] ?? null,
                        'team_in' => $toTeam,
                        'team_out' => $fromTeam,
                        'team_logo' => $latestTransfer['teams']['in']['logo'] ?? $latestTransfer['teams']['out']['logo'] ?? null,
                        'type' => $latestTransfer['type'] ?? 'Transfer',
                        'source_type' => 'transfer',
                        'category' => $this->classifyUpdate($title.' '.$body),
                        'likes' => $this->stableCount($transferId, 4, 18),
                        'comments' => $this->stableCount($transferId.'comments', 1, 12),
                    ];
                });
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function injuries(array $teamIds, ?int $season = null): array
    {
        $season ??= (int) now('Africa/Lagos')->year;

        return collect($teamIds)
            ->flatMap(function (int $teamId) use ($season) {
                try {
                    $response = $this->cached("injuries:{$teamId}:{$season}", 3600, fn () => $this->get('injuries', [
                        'team' => $teamId,
                        'season' => $season,
                    ]));
                } catch (\Throwable) {
                    return [];
                }

                return collect($response['response'] ?? [])->map(function (array $injury) use ($teamId) {
                    $fixtureId = $injury['fixture']['id'] ?? null;
                    $playerId = $injury['player']['id'] ?? null;
                    $date = $injury['fixture']['date'] ?? null;
                    $reason = $injury['player']['reason'] ?? $injury['player']['type'] ?? 'an injury concern';
                    $playerName = $injury['player']['name'] ?? 'Player';
                    $teamName = $injury['team']['name'] ?? 'the squad';
                    $opponent = $injury['fixture']['teams']['away']['name'] ?? $injury['fixture']['teams']['home']['name'] ?? null;
                    $title = $playerName.' injury update';
                    $body = $playerName.' is listed for '.$teamName.' with '.$reason.($opponent ? ' around the fixture involving '.$opponent : '').'.';
                    $updateId = implode('-', array_filter([$teamId, $playerId, $fixtureId, $date, $reason]));

                    return [
                        'id' => 'injury-'.md5($updateId),
                        'title' => $title,
                        'body' => $body,
                        'date' => $date,
                        'player_name' => $playerName,
                        'player_photo' => $injury['player']['photo'] ?? null,
                        'team_in' => $teamName,
                        'team_out' => null,
                        'team_logo' => $injury['team']['logo'] ?? $injury['league']['logo'] ?? null,
                        'league' => $injury['league']['name'] ?? null,
                        'league_logo' => $injury['league']['logo'] ?? null,
                        'fixture_id' => $fixtureId,
                        'type' => $injury['player']['type'] ?? 'Injury',
                        'reason' => $reason,
                        'source_type' => 'injury',
                        'category' => $this->classifyUpdate(($injury['league']['name'] ?? '').' '.$teamName.' '.$title.' '.$body),
                        'likes' => $this->stableCount($updateId, 4, 18),
                        'comments' => $this->stableCount($updateId.'comments', 1, 12),
                    ];
                });
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function footballUpdates(array $teamIds): array
    {
        return collect([
            ...$this->transfers($teamIds),
            ...$this->injuries($teamIds),
        ])
            ->sortByDesc('date')
            ->unique('id')
            ->values()
            ->all();
    }

    public function resolveTeamIdsForHighlights(): array
    {
        return [40, 42, 49, 50, 63, 541, 529];
    }

    protected function classifyUpdate(string $text): string
    {
        $value = strtolower($text);

        if (str_contains($value, 'england') || str_contains($value, 'premier league') || str_contains($value, 'arsenal') || str_contains($value, 'liverpool') || str_contains($value, 'manchester')) {
            return 'Premier League';
        }

        if (str_contains($value, 'champions league') || str_contains($value, 'europa') || str_contains($value, 'la liga') || str_contains($value, 'serie a') || str_contains($value, 'bundesliga') || str_contains($value, 'ligue 1') || str_contains($value, 'barcelona') || str_contains($value, 'real madrid') || str_contains($value, 'bayern')) {
            return 'European Football';
        }

        if (str_contains($value, 'world cup') || str_contains($value, 'euro') || str_contains($value, 'afcon') || str_contains($value, 'national')) {
            return 'International';
        }

        return 'Latest';
    }

    protected function stableCount(string $seed, int $min, int $max): int
    {
        return $min + (abs(crc32($seed)) % (($max - $min) + 1));
    }

    protected function get(string $endpoint, array $query = []): array
    {
        $response = $this->request()
            ->get($endpoint, $query)
            ->throw()
            ->json();

        if (! empty($response['errors'])) {
            $message = is_array($response['errors'])
                ? implode(' ', array_values($response['errors']))
                : (string) $response['errors'];

            throw new RuntimeException($message ?: 'API-Football request failed.');
        }

        return $response;
    }

    protected function request(): PendingRequest
    {
        $apiKey = (string) config('services.api_football.key');

        if ($apiKey === '') {
            throw new RuntimeException('API_FOOTBALL_KEY is not configured.');
        }

        return Http::baseUrl((string) config('services.api_football.base_url'))
            ->acceptJson()
            ->connectTimeout(20)
            ->timeout(30)
            ->withHeaders([
                'x-apisports-key' => $apiKey,
            ]);
    }

    protected function cached(string $key, int $seconds, callable $callback): array
    {
        return Cache::remember("api-football:{$key}", $seconds, $callback);
    }
}
