<?php

use App\Http\Controllers\Gateway\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('payments/webhooks/{gateway}', PaymentWebhookController::class)->name('payments.webhook');
