<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Billing\BillingController;


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('billing')->group(function () {
        Route::post('/request-bundle', [BillingController::class, 'requestBundle']);
        Route::post('/request-bundle-ussd', [BillingController::class, 'requestBundleUssd']);
    });
});

Route::prefix('billing')->group(function () {
    Route::post('/receive-control-number', [BillingController::class, 'receiveControlNumber']);
    Route::post('/receive-control-number-payment', [BillingController::class, 'receiveControlNumberPayment']);
    Route::post('/receive-control-number-from-dmz', [BillingController::class, 'receiveControlNumberFromDMZ']);
    Route::post('/receive-payment', [BillingController::class, 'receivePayment']);
    Route::post('/bill-cancellation', [BillingController::class, 'billCancellation']);
    Route::post('/bill-push-to-pay', [BillingController::class, 'billPushToPay']);
    Route::post('/heartbeat-control-number', [BillingController::class, 'heartbeatControlNumber']);
    Route::post('/heartbeat-payment', [BillingController::class, 'heartbeatPayment']);
    Route::post('/post-top-up-bill', [BillingController::class, 'postTopUpBill']);

});
