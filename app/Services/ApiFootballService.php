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
                $response = $this->cached("transfers:{$teamId}", 21600, fn () => $this->get('transfers', [
                    'team' => $teamId,
                ]));

                return collect($response['response'] ?? [])->map(function (array $transfer) use ($teamId) {
                    $latestTransfer = collect($transfer['transfers'] ?? [])->first();
                    $playerId = $transfer['player']['id'] ?? null;
                    $date = $latestTransfer['date'] ?? null;
                    $transferId = implode('-', array_filter([$teamId, $playerId, $date]));
                    $fromTeam = $latestTransfer['teams']['out']['name'] ?? 'their previous club';
                    $toTeam = $latestTransfer['teams']['in']['name'] ?? 'a new club';
                    $playerName = $transfer['player']['name'] ?? 'Player';

                    return [
                        'id' => $transferId,
                        'title' => $playerName.' transfer update',
                        'body' => $playerName.' is linked with '.$toTeam.' from '.$fromTeam.'.',
                        'date' => $date,
                        'player_name' => $playerName,
                        'player_photo' => $transfer['player']['photo'] ?? null,
                        'team_in' => $toTeam,
                        'team_out' => $fromTeam,
                        'type' => $latestTransfer['type'] ?? 'Transfer',
                        'likes' => rand(4, 18),
                        'comments' => rand(1, 12),
                    ];
                });
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function resolveTeamIdsForHighlights(): array
    {
        return [40, 42, 49, 50, 63, 541, 529];
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
