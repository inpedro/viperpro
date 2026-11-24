<?php

use App\Http\Controllers\Api\Lobby\LobbyController;
use Illuminate\Support\Facades\Route;

Route::prefix('lobby')->group(function () {
    Route::get('games', [LobbyController::class, 'games']);
    Route::get('providers', [LobbyController::class, 'providers']);
    Route::get('categories', [LobbyController::class, 'categories']);
});
