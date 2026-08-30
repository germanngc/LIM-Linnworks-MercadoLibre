<?php

use App\Http\Controllers\Api\v1\OrdersController;
use App\Http\Controllers\ChannelIntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('v1/orders', [OrdersController::class, 'get'])->middleware('api.protect');

Route::post('/Config/AddNewUser', [ChannelIntegrationController::class, 'addNewUser']);
Route::post('/oauth/authorize', [ChannelIntegrationController::class, 'oauthAuthorize']);
Route::post('/Listing/GetConfiguratorSettings', [ChannelIntegrationController::class, 'getConfiguratorSettings']);
Route::match(['get', 'post'], '/Config/UserConfig', [ChannelIntegrationController::class, 'userConfig']);
Route::match(['get', 'post'], '/Config/GetConfig', [ChannelIntegrationController::class, 'userConfig']);

Route::middleware('lw.auth')->group(function () {
	Route::post('/Config/ConfigTest', [ChannelIntegrationController::class, 'configTest']);
	Route::post('/Config/SaveUserConfig', [ChannelIntegrationController::class, 'saveUserConfig']);
	Route::post('/Config/SaveConfig', [ChannelIntegrationController::class, 'saveUserConfig']);
	Route::post('/Config/ShippingTags', [ChannelIntegrationController::class, 'shippingTags']);
	Route::post('/Config/PaymentTags', [ChannelIntegrationController::class, 'paymentTags']);
	Route::post('/Config/ConfigDeleted', [ChannelIntegrationController::class, 'configDeleted']);

	Route::post('/Order/Orders', [ChannelIntegrationController::class, 'orders']);
	Route::post('/Order/Despatch', [ChannelIntegrationController::class, 'despatch']);
	Route::post('/Order/Cancel', [ChannelIntegrationController::class, 'cancel']);
	Route::post('/Order/Refund', [ChannelIntegrationController::class, 'refund']);
	Route::post('/Order/PostSaleOptions', [ChannelIntegrationController::class, 'postSaleOptions']);

	Route::post('/Product/Products', [ChannelIntegrationController::class, 'products']);
	Route::post('/Product/InventoryUpdate', [ChannelIntegrationController::class, 'inventoryUpdate']);
	Route::post('/Product/PriceUpdate', [ChannelIntegrationController::class, 'priceUpdate']);

	Route::post('/Listing/GetCategories', [ChannelIntegrationController::class, 'getCategories']);
	Route::post('/Listing/GetAttributesByCategory', [ChannelIntegrationController::class, 'getAttributesByCategory']);
	Route::post('/Listing/GetVariationsByCategory', [ChannelIntegrationController::class, 'getVariationsByCategory']);
	Route::post('/Listing/ListingUpdate', [ChannelIntegrationController::class, 'listingUpdate']);
	Route::post('/Listing/ListingDelete', [ChannelIntegrationController::class, 'listingDelete']);
	Route::post('/Listing/CheckFeed', [ChannelIntegrationController::class, 'checkFeed']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
