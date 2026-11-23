<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'api'], function () {

    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');

    Route::post('forget-password', [AuthController::class, 'submitForgetPassword'])->middleware('throttle:password-reset');
    Route::post('reset-password/{token}', [AuthController::class, 'submitResetPassword'])->middleware('throttle:password-reset');

    Route::group(['middleware' => ['auth.jwt']], function () {
        Route::get('verify', [AuthController::class, 'verify']); // verifica se está autenticado
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('me', [AuthController::class, 'me']);
    });
});
