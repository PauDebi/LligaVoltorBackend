<?php

use App\Http\Controllers\FlightController;
use App\Http\Controllers\LeaderboardController;
use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('flights')->group(function () {
    Route::get('/', [FlightController::class, 'index']);
    Route::get('/{flight}', [FlightController::class, 'show']);
    Route::post('/', [FlightController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/user/{user_id}' , [FlightController::class, 'getFlightsFromUser'])->middleware('auth:sanctum');
});

Route::prefix('leaderboard')->group(function () {
    Route::get('/', [LeaderboardController::class, 'getOpen']);
    Route::get('/sport', [LeaderboardController::class, 'getSport']);
    Route::get('/club', [LeaderboardController::class, 'getClub']);
    Route::get('/tandem', [LeaderboardController::class, 'getTandem']);
});

require __DIR__.'/auth.php';
