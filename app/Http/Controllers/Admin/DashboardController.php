<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Models\PredictionComment;
use App\Models\PredictionLike;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $recentPredictions = Prediction::query()
            ->latest()
            ->take(5)
            ->get([
                'id',
                'league_name',
                'home_team_name',
                'away_team_name',
                'prediction_value',
                'status',
                'published_at',
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'predictions' => Prediction::count(),
                'publishedPredictions' => Prediction::where('status', 'published')->count(),
                'comments' => PredictionComment::count(),
                'likes' => PredictionLike::count(),
            ],
            'recentPredictions' => $recentPredictions,
        ]);
    }
}
