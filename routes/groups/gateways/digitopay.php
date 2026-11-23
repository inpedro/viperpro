<?php


use App\Http\Controllers\Gateway\DigitoPayController;
use Illuminate\Support\Facades\Route;

Route::prefix('digitopay')
    ->group(function ()
    {
        Route::post('callback', [DigitoPayController::class, 'callbackMethod']);
        Route::post('payment', [DigitoPayController::class, 'callbackMethodPayment']);
        // Withdrawal approve/cancel moved to authenticated Filament actions (POST + CSRF). See WithdrawalService.
    });

