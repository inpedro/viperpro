<?php

use App\Http\Controllers\Api\Content\LegalController;
use Illuminate\Support\Facades\Route;

Route::get('legal/{slug}', [LegalController::class, 'show']);
