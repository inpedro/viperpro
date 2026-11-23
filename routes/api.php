<?php

use App\Http\Controllers\Api\Profile\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


/*
 * Auth Route with JWT
 */
Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
    require(__DIR__ . '/groups/api/auth/auth.php');
});

Route::group(['middleware' => ['auth.jwt']], function () {
    Route::prefix('profile')
        ->group(function ()
        {
            require(__DIR__ . '/groups/api/profile/profile.php');
            require(__DIR__ . '/groups/api/profile/affiliates.php');
            require(__DIR__ . '/groups/api/profile/wallet.php');
            require(__DIR__ . '/groups/api/profile/likes.php');
            require(__DIR__ . '/groups/api/profile/favorites.php');
            require(__DIR__ . '/groups/api/profile/recents.php');
            require(__DIR__ . '/groups/api/profile/vip.php');
        });

    Route::prefix('wallet')
        ->group(function ()
        {
            require(__DIR__ . '/groups/api/wallet/deposit.php');
            require(__DIR__ . '/groups/api/wallet/withdraw.php');
        });

    require(__DIR__ . '/groups/api/missions/mission.php');;
    require(__DIR__ . '/groups/api/missions/missionuser.php');;
});


Route::prefix('categories')
    ->group(function ()
    {
        require(__DIR__ . '/groups/api/categories/index.php');;
    });

require(__DIR__ . '/groups/api/games/index.php');
require(__DIR__ . '/groups/api/gateways/digitopay.php');
require(__DIR__ . '/groups/api/gateways/suitpay.php');

Route::prefix('search')
    ->group(function ()
    {
        require(__DIR__ . '/groups/api/search/search.php');
    });

Route::prefix('profile')
    ->group(function ()
    {
        Route::post('/getLanguage', [ProfileController::class, 'getLanguage']);
        Route::put('/updateLanguage', [ProfileController::class, 'updateLanguage']);
    });

Route::prefix('providers')
    ->group(function ()
    {

    });


Route::prefix('settings')
    ->group(function ()
    {
        require(__DIR__ . '/groups/api/settings/settings.php');
        require(__DIR__ . '/groups/api/settings/banners.php');
        require(__DIR__ . '/groups/api/settings/currency.php');
        require(__DIR__ . '/groups/api/settings/bonus.php');
    });

// LANDING SPIN
Route::prefix('spin')
    ->group(function ()
    {
        require(__DIR__ . '/groups/api/spin/index.php');
    })
    ->name('landing.spin.');
