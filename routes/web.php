<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\MatchHighlightController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PopularLeagueController;
use App\Http\Controllers\Admin\PredictionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('dashboard/predictions', [PredictionController::class, 'index']);
    Route::get('dashboard/predictions/create', [PredictionController::class, 'create']);
    Route::post('dashboard/predictions', [PredictionController::class, 'store']);
    Route::get('dashboard/predictions/{prediction}/edit', [PredictionController::class, 'edit']);
    Route::put('dashboard/predictions/{prediction}', [PredictionController::class, 'update']);
    Route::delete('dashboard/predictions/{prediction}', [PredictionController::class, 'destroy']);
    Route::get('dashboard/notifications', [NotificationController::class, 'index']);
    Route::post('dashboard/notifications', [NotificationController::class, 'store']);
    Route::get('dashboard/contact-messages', [ContactMessageController::class, 'index']);
    Route::patch('dashboard/contact-messages/{contactMessage}/read', [ContactMessageController::class, 'markRead']);
    Route::get('dashboard/match-highlights', [MatchHighlightController::class, 'index']);
    Route::post('dashboard/match-highlights', [MatchHighlightController::class, 'store']);
    Route::delete('dashboard/match-highlights/{matchHighlight}', [MatchHighlightController::class, 'destroy']);
    Route::get('dashboard/popular-leagues', [PopularLeagueController::class, 'index']);
    Route::post('dashboard/popular-leagues/sync-data', [PopularLeagueController::class, 'syncData']);
    Route::post('dashboard/popular-leagues', [PopularLeagueController::class, 'store']);
    Route::put('dashboard/popular-leagues/{popularLeague}', [PopularLeagueController::class, 'update']);
    Route::delete('dashboard/popular-leagues/{popularLeague}', [PopularLeagueController::class, 'destroy']);
});

require __DIR__.'/settings.php';
