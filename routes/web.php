<?php

use App\Http\Controllers\Admin\DashboardController;
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
});

require __DIR__.'/settings.php';
