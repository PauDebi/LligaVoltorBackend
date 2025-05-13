<?php

use App\Http\Controllers\FlightController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\UserController;
use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', function (Request $request) {
        return $request->user();
    });
    Route::post('/',[UserController::class, 'update']) ;
    Route::post('/image/upload', [UserController::class, 'uploadProfilePicture']);
});

Route::prefix('flights')->group(function () {
    Route::get('/', [FlightController::class, 'index']);
    Route::get('/{flight}', [FlightController::class, 'show']);
    Route::post('/', [FlightController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/user/{user_id}' , [FlightController::class, 'getFlightsFromUser'])->middleware('auth:sanctum');
    Route::get('/download/{flight_path}', [FlightController::class, 'getFile'])->middleware('auth:sanctum');
});

Route::prefix('leaderboard')->group(function () {
    Route::get('/', [LeaderboardController::class, 'getOpen']);
    Route::get('/sport', [LeaderboardController::class, 'getSport']);
    Route::get('/club', [LeaderboardController::class, 'getClub']);
    Route::get('/tandem', [LeaderboardController::class, 'getTandem']);
});



require __DIR__.'/auth.php';
