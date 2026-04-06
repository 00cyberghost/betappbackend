<?php

use App\Http\Controllers\Api\FootballLookupController;
use App\Http\Controllers\Api\PredictionFeedController;
use App\Http\Controllers\Api\AppAuthController;
use App\Http\Controllers\Api\AppFootballFeedController;
use App\Http\Controllers\Api\AppPredictionController;
use App\Http\Controllers\Api\AppProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/predictions', [PredictionFeedController::class, 'index']);
Route::get('/predictions/home', [PredictionFeedController::class, 'home']);
Route::get('/predictions/{prediction}', [PredictionFeedController::class, 'show']);
Route::get('/app/live', [AppFootballFeedController::class, 'live']);
Route::get('/app/live/{fixture}', [AppFootballFeedController::class, 'liveDetail']);
Route::get('/app/competitions', [AppFootballFeedController::class, 'competitions']);
Route::get('/app/competitions/{league}', [AppFootballFeedController::class, 'competitionDetail']);
Route::get('/app/updates', [AppFootballFeedController::class, 'updates']);
Route::get('/app/updates/{update}', [AppFootballFeedController::class, 'updateDetail']);

Route::prefix('football')->group(function () {
    Route::get('/countries', [FootballLookupController::class, 'countries']);
    Route::get('/leagues', [FootballLookupController::class, 'leagues']);
    Route::get('/fixtures', [FootballLookupController::class, 'fixtures']);
    Route::get('/fixtures/{fixture}', [FootballLookupController::class, 'details']);
});

Route::prefix('app')->group(function () {
    Route::post('/register', [AppAuthController::class, 'register']);
    Route::post('/login', [AppAuthController::class, 'login']);

    Route::middleware('app.auth')->group(function () {
        Route::post('/logout', [AppAuthController::class, 'logout']);
        Route::get('/me', [AppProfileController::class, 'show']);
        Route::put('/profile', [AppProfileController::class, 'update']);
        Route::post('/predictions', [AppPredictionController::class, 'store']);
    });
});
