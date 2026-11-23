<?php


use App\Http\Controllers\Gateway\SuitPayController;
use Illuminate\Support\Facades\Route;


Route::prefix('suitpay')
    ->group(function ()
    {
        Route::post('callback', [SuitPayController::class, 'callbackMethod']);
        Route::post('payment', [SuitPayController::class, 'callbackMethodPayment']);
        // Withdrawal approve/cancel moved to authenticated Filament actions (POST + CSRF). See WithdrawalService.
    });

