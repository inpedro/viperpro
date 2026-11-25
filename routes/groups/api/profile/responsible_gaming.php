<?php

use App\Http\Controllers\Api\ResponsibleGaming\ResponsibleGamingController;
use Illuminate\Support\Facades\Route;

Route::prefix('responsible-gaming')->group(function () {
    Route::get('/', [ResponsibleGamingController::class, 'show']);
    Route::put('/limits', [ResponsibleGamingController::class, 'limits']);
    Route::post('/exclude', [ResponsibleGamingController::class, 'exclude']);
    Route::put('/reality-check', [ResponsibleGamingController::class, 'realityCheck']);
});
