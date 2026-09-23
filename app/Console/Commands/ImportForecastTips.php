<?php

namespace App\Console\Commands;

use App\Models\Tip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportForecastTips extends Command
{
    protected $signature = 'tips:import-forecasts {path : Path to a forecast SQL dump}';

    protected $description = 'Import forecast SQL rows into the tips table.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            throw new RuntimeException("Forecast SQL file not found: {$path}");
        }

        $rows = $this->extractRows((string) file_get_contents($path));

        if ($rows === []) {
            $this->warn('No forecast rows were found in the SQL file.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $index => $row) {
                $tip = $this->mapForecastToTip($row, $index + 1);

                $model = Tip::query()
                    ->where('source', 'forecast_sql')
                    ->where('source_id', $row['id'])
                    ->first();

                if (! $model) {
                    $model = Tip::query()
                        ->where('prediction_type', $tip['prediction_type'])
                        ->where('value', $tip['value'])
                        ->first();
                }

                if ($model) {
                    $model->fill($tip)->save();
                } else {
                    $model = Tip::create($tip);
                }

                $model->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        $this->info("Imported forecast tips. Created: {$created}. Updated: {$updated}. Total rows: ".count($rows).'.');

        return self::SUCCESS;
    }

    protected function extractRows(string $sql): array
    {
        preg_match_all(
            "/\\((\\d+),\\s*'((?:\\\\\\\\|\\\\'|[^'])*)',\\s*'([^']*)',\\s*'([^']*)'\\)/",
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        return collect($matches)
            ->map(fn (array $match) => [
                'id' => (int) $match[1],
                'forecast' => trim(stripcslashes($match[2])),
                'created_at' => $match[3],
                'updated_at' => $match[4],
            ])
            ->filter(fn (array $row) => $row['forecast'] !== '')
            ->values()
            ->all();
    }

    protected function mapForecastToTip(array $row, int $sortOrder): array
    {
        $raw = $row['forecast'];
        $value = $this->normalizeValue($raw);

        return [
            'prediction_type' => $this->predictionTypeFor($value),
            'label' => $this->labelFor($value),
            'value' => $value,
            'description' => 'Imported from forecast.sql',
            'sort_order' => $sortOrder,
            'is_active' => true,
            'source' => 'forecast_sql',
            'source_id' => $row['id'],
            'raw_label' => $raw,
        ];
    }

    protected function normalizeValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: $value);
        $value = str_replace(['\\', '∕'], '/', $value);
        $value = preg_replace('/\b0V\b/i', 'OV', $value) ?: $value;
        $value = preg_replace('/\bHOMR\b/i', 'HOME', $value) ?: $value;
        $value = preg_replace('/21O\.5/i', '210.5', $value) ?: $value;
        $value = preg_replace('/\b1TH\b/i', '1ST HALF', $value) ?: $value;
        $value = preg_replace('/\b2TH\b/i', '2ND HALF', $value) ?: $value;
        $value = preg_replace('/\b2HT\b/i', '2ND HALF', $value) ?: $value;
        $value = preg_replace('/\b1HT\b/i', '1ST HALF', $value) ?: $value;
        $value = preg_replace('/\bHT\b/i', 'HT', $value) ?: $value;
        $value = preg_replace('/\bCRN\b/i', 'Corners', $value) ?: $value;
        $value = preg_replace('/\bOV\b/i', 'Over', $value) ?: $value;
        $value = preg_replace('/\bUND?\b/i', 'Under', $value) ?: $value;
        $value = preg_replace('/\bCARD\b/i', 'Cards', $value) ?: $value;
        $value = preg_replace('/\bCARDS\b/i', 'Cards', $value) ?: $value;
        $value = preg_replace('/\bGG\b/i', 'BTTS Yes', $value) ?: $value;
        $value = preg_replace('/\bNG\b/i', 'BTTS No', $value) ?: $value;
        $value = preg_replace('/\b1x\b/i', '1X', $value) ?: $value;
        $value = preg_replace('/\b2x\b/i', 'X2', $value) ?: $value;
        $value = preg_replace('/\bx\b/', 'X', $value) ?: $value;
        $value = preg_replace('/\s*&\s*/', ' & ', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    protected function labelFor(string $value): string
    {
        if (preg_match('/^\(([^)]+)\)$/', trim($value), $match) === 1) {
            return $match[1];
        }

        if (preg_match('/^((?:\(\d+\-\d+\))+)$/' , trim($value)) === 1) {
            return str_replace([')(', '(', ')'], [' / ', '', ''], trim($value));
        }

        return Str::headline($value);
    }

    protected function predictionTypeFor(string $value): string
    {
        $normalized = strtoupper($value);

        if (preg_match('/^\(?\d+\-\d+\)?$/', $normalized) === 1 || str_contains($normalized, 'CORRECT')) {
            return 'Correct Score';
        }

        if (preg_match('/^\(\d+\-\d+\)/', $normalized) === 1) {
            return 'Correct Score Group';
        }

        if (str_contains($normalized, '/') && preg_match('/^[12X]\s*\/\s*[12X]$/', $normalized) === 1) {
            return 'Half Time/Full Time';
        }

        if (str_contains($normalized, 'HT') || str_contains($normalized, '1ST HALF')) {
            return 'Half Time';
        }

        if (str_contains($normalized, '2ND HALF')) {
            return 'Second Half';
        }

        if (str_contains($normalized, 'DNB')) {
            return 'Draw No Bet';
        }

        if (in_array($normalized, ['1', 'X', '2'], true)) {
            return '1X2';
        }

        if (in_array($normalized, ['1X', 'X2', '12'], true) || str_contains($normalized, ' OR ')) {
            return 'Double Chance';
        }

        if (str_contains($normalized, 'BTTS')) {
            return 'Both Teams To Score';
        }

        if (str_contains($normalized, 'CORNERS')) {
            return 'Corners';
        }

        if (str_contains($normalized, 'CARD')) {
            return 'Cards';
        }

        if (str_contains($normalized, 'FOULS')) {
            return 'Fouls';
        }

        if (str_contains($normalized, 'OFFSIDE')) {
            return 'Offsides';
        }

        if (str_contains($normalized, 'QUALIFY')) {
            return 'Qualification';
        }

        if (str_contains($normalized, 'SET')) {
            return 'Set Winner';
        }

        if (str_contains($normalized, 'OVER') || str_contains($normalized, 'UNDER')) {
            return 'Under/Over';
        }

        return 'Other';
    }
}
