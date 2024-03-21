<?php

use App\Http\Controllers\Api\Profile\ProfileController;
use App\Services\Gateways\Primepag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
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

Route::post('webhook/primepag', function(Request $request){

    Log::debug($request->all());

    if ($request->all()['message']['status'] == "paid") {
        $primepag = new Primepag();
        $primepag->confirmPayment($request->all()['message']['reference_code']);
    }
    return response()->json(['message' => 'ok'], 200);
});

Route::group(['namespace' => 'App\Http\Controllers\Api\WebHook'], function () {
    Route::post('evoplay', 'EvoplayController@actions');
    Route::post('salsa', 'SalsaController@salsa');
    Route::group(['prefix' => '/pgsoft'], function () {
        Route::post('/verify', 'PgController@session');
        Route::post('/Verify', 'PgController@session');
        Route::post('/cash/get', 'PgController@cashGet');
        Route::post('/cash/transfer', 'PgController@getTransfer');
        Route::post('/cash/adjust', 'PgController@adjust');
    });

    Route::group(['prefix' => '/pragmatic'], function () {
        Route::post('/authenticate.html', 'PragmaticController@authenticate');
        Route::post('/adjustment.html', 'PragmaticController@adjustment');
        Route::post('/balance.html', 'PragmaticController@balance');
        Route::post('/bet.html', 'PragmaticController@bet');
        Route::post('/refund.html', 'PragmaticController@refound');
        Route::post('/result.html', 'PragmaticController@result');
        Route::post('/bonusWin.html', 'PragmaticController@bonusWin');
        Route::post('/jackpotWin.html', 'PragmaticController@jackpotWin');
        Route::post('/promoWin.html', 'PragmaticController@promoWin');
    });

});
/*
 * Auth Route with JWT
 */
Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
    include_once(__DIR__ . '/groups/api/auth/auth.php');
});

Route::group(['middleware' => ['auth.jwt']], function () {
    Route::prefix('profile')
        ->group(function ()
        {
            include_once(__DIR__ . '/groups/api/profile/profile.php');
            include_once(__DIR__ . '/groups/api/profile/affiliates.php');
            include_once(__DIR__ . '/groups/api/profile/wallet.php');
            include_once(__DIR__ . '/groups/api/profile/likes.php');
            include_once(__DIR__ . '/groups/api/profile/favorites.php');
            include_once(__DIR__ . '/groups/api/profile/recents.php');
            include_once(__DIR__ . '/groups/api/profile/vip.php');
        });

    Route::prefix('wallet')
        ->group(function ()
        {
            include_once(__DIR__ . '/groups/api/wallet/deposit.php');
            include_once(__DIR__ . '/groups/api/wallet/withdraw.php');
        });

    include_once(__DIR__ . '/groups/api/missions/mission.php');;
    include_once(__DIR__ . '/groups/api/missions/missionuser.php');;
});


Route::prefix('categories')
    ->group(function ()
    {
        include_once(__DIR__ . '/groups/api/categories/index.php');;
    });

include_once(__DIR__ . '/groups/api/games/index.php');
include_once(__DIR__ . '/groups/api/gateways/suitpay.php');

Route::prefix('search')
    ->group(function ()
    {
        include_once(__DIR__ . '/groups/api/search/search.php');
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
        include_once(__DIR__ . '/groups/api/settings/settings.php');
        include_once(__DIR__ . '/groups/api/settings/banners.php');
        include_once(__DIR__ . '/groups/api/settings/currency.php');
        include_once(__DIR__ . '/groups/api/settings/bonus.php');
    });

// LANDING SPIN
Route::prefix('spin')
    ->group(function ()
    {
        include_once(__DIR__ . '/groups/api/spin/index.php');
    })
    ->name('landing.spin.');
