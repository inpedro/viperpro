<?php

use App\Http\Controllers\Api\Settings\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/data', [SettingController::class, 'index']);
Route::get('/locales', [SettingController::class, 'locales']);
Route::get('/registration', [SettingController::class, 'registration']);
