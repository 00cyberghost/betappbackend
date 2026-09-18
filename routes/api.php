<?php

use App\Http\Controllers\Api\FootballLookupController;
use App\Http\Controllers\Api\PredictionFeedController;
use App\Http\Controllers\Api\AppAuthController;
use App\Http\Controllers\Api\AppContactMessageController;
use App\Http\Controllers\Api\AppFootballFeedController;
use App\Http\Controllers\Api\AppInteractionController;
use App\Http\Controllers\Api\AppNotificationController;
use App\Http\Controllers\Api\AppPredictionController;
use App\Http\Controllers\Api\AppProfileController;
use App\Http\Controllers\Api\AppMatchHighlightController;
use App\Http\Controllers\Api\TipController;
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
Route::get('/app/match-highlights', [AppMatchHighlightController::class, 'index']);

Route::prefix('football')->group(function () {
    Route::get('/countries', [FootballLookupController::class, 'countries']);
    Route::get('/leagues', [FootballLookupController::class, 'leagues']);
    Route::get('/teams', [FootballLookupController::class, 'teams']);
    Route::get('/fixtures', [FootballLookupController::class, 'fixtures']);
    Route::get('/fixtures/{fixture}', [FootballLookupController::class, 'details']);
    Route::get('/tips', [TipController::class, 'index']);
});

Route::prefix('app')->group(function () {
    Route::post('/register', [AppAuthController::class, 'register']);
    Route::post('/login', [AppAuthController::class, 'login']);
    Route::post('/auth/google', [AppAuthController::class, 'google']);
    Route::post('/forgot-password', [AppAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AppAuthController::class, 'resetPassword']);

    Route::middleware('app.auth')->group(function () {
        Route::post('/logout', [AppAuthController::class, 'logout']);
        Route::get('/me', [AppProfileController::class, 'show']);
        Route::delete('/me', [AppProfileController::class, 'destroy']);
        Route::put('/profile', [AppProfileController::class, 'update']);
        Route::post('/predictions', [AppPredictionController::class, 'store']);
        Route::post('/predictions/{prediction}/like', [AppInteractionController::class, 'toggleLike']);
        Route::post('/predictions/{prediction}/comment', [AppInteractionController::class, 'comment']);
        Route::post('/predictions/{prediction}/share', [AppInteractionController::class, 'share']);
        Route::get('/notifications', [AppNotificationController::class, 'index']);
        Route::get('/notifications/preferences', [AppNotificationController::class, 'preferences']);
        Route::put('/notifications/preferences', [AppNotificationController::class, 'updatePreferences']);
        Route::post('/notifications/device-token', [AppNotificationController::class, 'storeDeviceToken']);
        Route::post('/notifications/read-all', [AppNotificationController::class, 'markAllRead']);
        Route::post('/contact-messages', [AppContactMessageController::class, 'store']);
    });
});
