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
            'league_name' => ['required', 'string', 'max:255'],
            'country_name' => ['nullable', 'string', 'max:255'],
            'home_team_name' => ['required', 'string', 'max:255'],
            'away_team_name' => ['required', 'string', 'max:255'],
            'prediction_type' => ['required', 'string', 'max:100'],
            'prediction_value' => ['required', 'string', 'max:100'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'odds' => ['nullable', 'numeric', 'min:0'],
            'analysis' => ['required', 'string'],
            'match_starts_at' => ['nullable', 'date'],
        ]);

        $prediction = Prediction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'pending_review',
            'scope' => 'community',
            'source' => 'manual',
            'category' => 'community_prediction',
            'published_at' => null,
            'home_team_logo' => null,
            'away_team_logo' => null,
        ]);

        return response()->json([
            'message' => 'Prediction submitted for review.',
            'prediction' => $prediction,
        ], 201);
    }
}
