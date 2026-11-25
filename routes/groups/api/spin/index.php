<?php

use App\Http\Controllers\Api\Landing\LandingSpinController;
use Illuminate\Support\Facades\Route;


Route::get('prizes', [LandingSpinController::class, 'Prizes'])->name('prizes');
Route::post('result', [LandingSpinController::class, 'Spin'])->middleware('throttle:spin')->name('result');
Route::get('winners', [LandingSpinController::class, 'Winners'])->name('winners');
