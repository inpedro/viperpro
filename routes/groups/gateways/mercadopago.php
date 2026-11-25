<?php


use App\Http\Controllers\Gateway\MercadoPagoController;
use Illuminate\Support\Facades\Route;


Route::prefix('mercadopago')
    ->group(function ()
    {
        Route::post('callback', [MercadoPagoController::class, 'callbackMethod']);
        Route::post('payment/{id}', [MercadoPagoController::class, 'callbackMethodPayment']);
        // Withdrawal approve/cancel moved to authenticated Filament actions (POST + CSRF). See WithdrawalService.
    });
