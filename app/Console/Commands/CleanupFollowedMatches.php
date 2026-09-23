<?php

namespace App\Console\Commands;

use App\Models\UserFollowedMatch;
use App\Services\ApiFootballService;
use App\Services\FirebasePushService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class CleanupFollowedMatches extends Command
{
    protected $signature = 'football:cleanup-followed-matches';

    protected $description = 'Notify followed match topics about score/status changes and delete completed follows.';

    private const FINISHED_STATUSES = ['FT', 'AET', 'PEN', 'CANC', 'ABD', 'AWD', 'WO'];

    public function handle(ApiFootballService $apiFootballService, FirebasePushService $firebasePushService): int
    {
        $fixtureIds = UserFollowedMatch::query()
            ->select('fixture_id')
            ->distinct()
            ->pluck('fixture_id');

        if ($fixtureIds->isEmpty()) {
            $this->info('No followed matches to check.');

            return self::SUCCESS;
        }

        $checked = 0;
        $completed = 0;
        $updated = 0;

        foreach ($fixtureIds as $fixtureId) {
            try {
                $payload = $apiFootballService->freshFixtures([
                    'id' => (int) $fixtureId,
                    'timezone' => 'Africa/Lagos',
                ]);
            } catch (Throwable $exception) {
                $this->warn("Fixture {$fixtureId} could not be checked: {$exception->getMessage()}");
                continue;
            }

            $fixture = collect($payload['response'] ?? [])->first();

            if (! $fixture) {
                continue;
            }

            $checked++;

            $follows = UserFollowedMatch::query()
                ->where('fixture_id', $fixtureId)
                ->get();

            if ($follows->isEmpty()) {
                continue;
            }

            $homeName = $fixture['teams']['home']['name'] ?? $follows->first()->home_team_name ?? 'Home';
            $awayName = $fixture['teams']['away']['name'] ?? $follows->first()->away_team_name ?? 'Away';
            $homeScore = $fixture['goals']['home'] ?? 0;
            $awayScore = $fixture['goals']['away'] ?? 0;
            $statusShort = $fixture['fixture']['status']['short'] ?? null;
            $statusLong = $fixture['fixture']['status']['long'] ?? $statusShort ?? 'Match update';
            $topic = $follows->first()->topic ?: "match-{$fixtureId}";
            $representative = $follows->first();
            $scoreChanged = $representative->home_score !== null
                && $representative->away_score !== null
                && ((int) $representative->home_score !== (int) $homeScore || (int) $representative->away_score !== (int) $awayScore);
            $statusChanged = $representative->status_short && $statusShort && $representative->status_short !== $statusShort;

            if ($scoreChanged || $statusChanged) {
                $firebasePushService->sendToTopic(
                    $topic,
                    $scoreChanged ? 'Goal update' : 'Match status update',
                    "{$homeName} {$homeScore}-{$awayScore} {$awayName} · {$statusLong}",
                    [
                        'type' => 'followed_match_update',
                        'fixture_id' => (string) $fixtureId,
                    ],
                );

                $updated++;
            }

            if ($statusShort && in_array($statusShort, self::FINISHED_STATUSES, true)) {
                $firebasePushService->sendToTopic(
                    $topic,
                    'Full time',
                    "{$homeName} {$homeScore}-{$awayScore} {$awayName}",
                    [
                        'type' => 'followed_match_finished',
                        'fixture_id' => (string) $fixtureId,
                    ],
                );

                UserFollowedMatch::query()
                    ->where('fixture_id', $fixtureId)
                    ->delete();

                $completed++;

                continue;
            }

            $this->updateFollowRows($follows, [
                'home_team_name' => $homeName,
                'away_team_name' => $awayName,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'status_short' => $statusShort,
                'last_checked_at' => now(),
            ]);
        }

        $this->info("Checked {$checked} fixtures, sent {$updated} updates, cleaned {$completed} completed fixtures.");

        return self::SUCCESS;
    }

    protected function updateFollowRows(Collection $follows, array $values): void
    {
        UserFollowedMatch::query()
            ->whereIn('id', $follows->pluck('id'))
            ->update($values);
    }
}
