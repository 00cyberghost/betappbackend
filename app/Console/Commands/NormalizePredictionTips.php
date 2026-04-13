<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use Illuminate\Console\Command;

class NormalizePredictionTips extends Command
{
    protected $signature = 'predictions:normalize-tips';

    protected $description = 'Normalize older community predictions into compact betting tips.';

    public function handle(): int
    {
        $updated = 0;

        Prediction::query()
            ->where(function ($query) {
                $query
                    ->where('category', 'community_prediction')
                    ->orWhere(function ($innerQuery) {
                        $innerQuery
                            ->where('source', 'api_football')
                            ->where('prediction_type', 'AI Prediction');
                    });
            })
            ->chunkById(100, function ($predictions) use (&$updated) {
                foreach ($predictions as $prediction) {
                    $normalized = $this->normalize($prediction);

                    if (! $normalized) {
                        continue;
                    }

                    $prediction->forceFill($normalized)->save();
                    $updated++;
                }
            });

        $this->info("Normalized {$updated} predictions.");

        return self::SUCCESS;
    }

    protected function normalize(Prediction $prediction): ?array
    {
        $type = trim((string) $prediction->prediction_type);
        $value = trim((string) $prediction->prediction_value);

        if ($prediction->source === 'api_football' && $type === 'AI Prediction') {
            return $this->normalizeAiPrediction($prediction, $value);
        }

        if ($prediction->predicted_score_home !== null && $prediction->predicted_score_away !== null) {
            return [
                'prediction_type' => '1X2',
                'prediction_value' => $prediction->predicted_score_home > $prediction->predicted_score_away
                    ? '1'
                    : ($prediction->predicted_score_home < $prediction->predicted_score_away ? '2' : 'X'),
            ];
        }

        $map = [
            'home win' => ['prediction_type' => '1X2', 'prediction_value' => '1'],
            'away win' => ['prediction_type' => '1X2', 'prediction_value' => '2'],
            'draw' => ['prediction_type' => '1X2', 'prediction_value' => 'X'],
            'community pick' => ['prediction_type' => '1X2', 'prediction_value' => $value !== '' ? $value : 'X'],
        ];

        $lookup = strtolower($value !== '' ? $value : $type);

        return $map[$lookup] ?? null;
    }

    protected function normalizeAiPrediction(Prediction $prediction, string $value): ?array
    {
        $homeTeam = trim((string) $prediction->home_team_name);
        $awayTeam = trim((string) $prediction->away_team_name);
        $normalizedValue = strtolower($value);

        if ($value !== '' && $homeTeam !== '' && strcasecmp($value, $homeTeam) === 0) {
            return ['prediction_type' => '1X2', 'prediction_value' => '1'];
        }

        if ($value !== '' && $awayTeam !== '' && strcasecmp($value, $awayTeam) === 0) {
            return ['prediction_type' => '1X2', 'prediction_value' => '2'];
        }

        if (preg_match('/\b(under|over)\s+(\d+(?:\.\d+)?)\b/i', $value, $matches) === 1) {
            return [
                'prediction_type' => "Under/Over {$matches[2]}",
                'prediction_value' => ucfirst($normalizedValue === '' ? $matches[1] : strtolower($matches[1]))." {$matches[2]}",
            ];
        }

        if (in_array($normalizedValue, ['draw', 'x'], true)) {
            return ['prediction_type' => '1X2', 'prediction_value' => 'X'];
        }

        return null;
    }
}
