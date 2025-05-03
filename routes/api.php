<?php

use App\Http\Controllers\FlightController;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::apiResource('flight', FlightController::class)->middleware('auth:sanctum');

Route::prefix('leaderboard')->group(function () {
    Route::get('/', [LeaderboardController::class, 'getOpen']);
    Route::get('/sport', [LeaderboardController::class, 'getSport']);
    Route::get('/club', [LeaderboardController::class, 'getClub']);
    Route::get('/tandem', [LeaderboardController::class, 'getTandem']);
});

require __DIR__.'/auth.php';
