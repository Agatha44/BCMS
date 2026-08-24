<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Ussd\UssdController;

/**
 * USSD Routes
 * These routes are typically called from USSD gateway systems
 * No authentication required as USSD gateways handle their own authentication
 */
Route::prefix('ussd')->group(function () {
    // Main USSD request handler (routes based on bridge_service parameter)
    Route::post('/handle', [UssdController::class, 'handleRequest']);
    
    // Legacy endpoint for account status check (optional - can be removed if not needed)
    Route::post('/check-account', [UssdController::class, 'checkAccountStatus']);
});

