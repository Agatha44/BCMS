<?php

use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PublicServicesController;
use App\Http\Controllers\Portal\PushNotificationController;
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
Route::post('portal/bridge-account-details', [PortalController::class, 'getBridgeAccountDetails'])->name('bridge-account-details');
Route::post('portal/bridge-account-passages-count', [PortalController::class, 'getBridgeAccountPassagesCount'])->name('bridge-account-passages-count');
Route::post('portal/subscribe-bridge-service', [PortalController::class, 'subscribeToBridgeService'])->name('bridge-account-subscription');
Route::post('portal/unsubscribe-bridge-service', [PortalController::class, 'unSubscribeBridgeService'])->name('bridge-account-unsubscription');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('portal/list-top-ups', [PortalController::class, 'fetchTopUps'])->name('bridge-account-top-ups');
    Route::get('portal/list-bundles', [PortalController::class, 'fetchBundles'])->name('bridge-account-bundles');
    Route::get('portal/list-vehicles', [PortalController::class, 'fetchVehicles'])->name('bridge-account-vehicles');
    Route::get('portal/list-bundle-passages', [PortalController::class, 'fetchBundlePassages'])->name('bridge-account-bundle-passages');
    Route::get('portal/list-normal-passages', [PortalController::class, 'fetchNormaPassages'])->name('bridge-account-normal-passages');

    Route::get('portal/list-commuter-bills', [PortalController::class, 'fetchCommuterBills'])->name('bridge-account-commuter-bills');
    Route::get('portal/list-commuter-passages', [PortalController::class, 'fetchCommuterPassages'])->name('bridge-account-commuter-passages');
    Route::post('portal/account-bills', [PortalController::class, 'fetchAccountBills'])->name('account-bills');
});

Route::prefix('portal/public')->group(function () {
    Route::post('search-vehicle', [PublicServicesController::class, 'searchVehicle'])->name('search-vehicle');
    Route::post('search-account', [PublicServicesController::class, 'searchAccount'])->name('search-account');
    Route::get('lanes', [App\Http\Controllers\Administration\LaneController::class, 'getPublicLanes'])->name('public-lanes');
});

// Push Notification Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('nssf-app/register-push-token', [PushNotificationController::class, 'registerToken'])->name('register-push-token');
    Route::post('nssf-app/unregister-push-token', [PushNotificationController::class, 'unregisterToken'])->name('unregister-push-token');
    Route::post('nssf-app/send-notification', [PushNotificationController::class, 'sendNotification'])->name('send-notification');
    Route::post('nssf-app/send-bulk-notification', [PushNotificationController::class, 'sendBulkNotification'])->name('send-bulk-notification');
});
