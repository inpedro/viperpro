<?php

use App\Http\Controllers\Api\Games\GameController;
use Illuminate\Support\Facades\Route;

// Every provider callback is authenticated (fail closed) — see config/game_providers.php.
Route::post('playigaming_api', [GameController::class, 'webhookPlayIGamingMethod'])->middleware('provider.callback:playigaming');
Route::post('playgaming', [GameController::class, 'webhookPlayGamingMethod'])->middleware('provider.callback:playgaming');
Route::post('ever', [GameController::class, 'webhookEvergameMethod'])->middleware('provider.callback:evergame');

Route::middleware('provider.callback:fivers')->group(function () {
    Route::post('gold_api', [GameController::class, 'webhookGoldApiMethod']);
    Route::post('gold_api/user_balance', [GameController::class, 'webhookUserBalanceMethod']);
    Route::post('gold_api/game_callback', [GameController::class, 'webhookGameCallbackMethod']);
    Route::post('gold_api/money_callback', [GameController::class, 'webhookMoneyCallbackMethod']);
});
