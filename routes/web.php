<?php

use App\Http\Controllers\LinnworkAuthController;
use App\Http\Controllers\KlaviyoOAuthController;
use App\Http\Controllers\MercadoLibreOAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => '/'], function () {
	Route::get('/', [LinnworkAuthController::class, 'login'])->name('login');
	Route::post('/auth', [LinnworkAuthController::class, 'auth'])->name('auth');
	Route::post('/logout', [LinnworkAuthController::class, 'logout'])->name('logout');
	
	Route::get('/uninstall', [LinnworkAuthController::class, 'uninstall'])->name('uninstall');
});

Route::get('/auth/klaviyo', [KlaviyoOAuthController::class, 'redirectToKlaviyo']);
Route::get('/oauth/callback', [KlaviyoOAuthController::class, 'handleCallback']);

Route::get('/auth/mercadolibre', [MercadoLibreOAuthController::class, 'redirect']);
Route::get('/oauth/mercadolibre/callback', [MercadoLibreOAuthController::class, 'callback']);
Route::get('/mercadolibre/{token}', [MercadoLibreOAuthController::class, 'dashboard'])->where('token', '[A-Za-z0-9\-]+');
Route::get('/mercadolibre', [MercadoLibreOAuthController::class, 'dashboard']);
Route::post('/mercadolibre/sync', [MercadoLibreOAuthController::class, 'sync']);
Route::post('/mercadolibre/sync-inventory', [MercadoLibreOAuthController::class, 'syncInventory']);
Route::post('/mercadolibre/disconnect', [MercadoLibreOAuthController::class, 'disconnect']);

/*Route::get('/', function () {
	return view('login');
})->name('login');

Route::get('/auth', [OrdersController::class, 'get'])->middleware('api.protect');)*/
