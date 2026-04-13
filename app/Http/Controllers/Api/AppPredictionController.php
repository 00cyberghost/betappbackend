<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppPredictionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fixture_id' => ['required', 'integer'],
            'league_id' => ['nullable', 'integer'],
            'league_name' => ['required', 'string', 'max:255'],
            'league_logo' => ['nullable', 'url', 'max:2048'],
            'country_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'home_team_id' => ['nullable', 'integer'],
            'home_team_name' => ['required', 'string', 'max:255'],
            'home_team_logo' => ['nullable', 'url', 'max:2048'],
            'away_team_id' => ['nullable', 'integer'],
            'away_team_name' => ['required', 'string', 'max:255'],
            'away_team_logo' => ['nullable', 'url', 'max:2048'],
            'prediction_type' => ['required', 'string', 'max:100'],
            'prediction_value' => ['required', 'string', 'max:100'],
            'predicted_score_home' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predicted_score_away' => ['nullable', 'integer', 'min:0', 'max:99'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'odds' => ['nullable', 'numeric', 'min:0'],
            'analysis' => ['required', 'string'],
            'match_starts_at' => ['required', 'date'],
        ]);

        $prediction = Prediction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'pending_review',
            'scope' => 'community',
            'source' => 'manual',
            'category' => 'community_prediction',
            'published_at' => null,
        ]);

        return response()->json([
            'message' => 'Prediction submitted for review.',
            'prediction' => $prediction,
        ], 201);
    }
}
