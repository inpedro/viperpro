<?php

use App\Models\Game;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|Sme
*/
// Removed: unauthenticated GET /clear utility route (ran optimize:clear for anyone). Use `php artisan optimize:clear`.

// GAMES PROVIDER
require(__DIR__ . '/groups/provider/games.php');
require(__DIR__ . '/groups/provider/venix.php');
require(__DIR__ . '/groups/provider/salsa.php');


// GATEWAYS
require(__DIR__ . '/groups/gateways/digitopay.php');
require(__DIR__ . '/groups/gateways/sharkpay.php');
require(__DIR__ . '/groups/gateways/mercadopago.php');
require(__DIR__ . '/groups/gateways/suitpay.php');

/// SOCIAL
require(__DIR__ . '/groups/auth/social.php');

// APP
require(__DIR__ . '/groups/layouts/app.php');

