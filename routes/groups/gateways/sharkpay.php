<?php


use App\Http\Controllers\Gateway\SharkPayController;
use Illuminate\Support\Facades\Route;


Route::prefix('sharkpay')
    ->group(function ()
    {
        Route::post('callback', [SharkPayController::class, 'callbackMethod']);
        Route::post('payment', [SharkPayController::class, 'callbackMethodPayment']);
        // Withdrawal approve/cancel moved to authenticated Filament actions (POST + CSRF). See WithdrawalService.
    });

