<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;


Route::prefix('auth')->group(function () {
    Route::post('booth-login', [AuthController::class, 'boothLogin'])->name('booth-login');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('update-password', [AuthController::class, 'updatePassword'])->name('update-password');
    
    // Forgot password routes
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    
    // Test route for CORS
    Route::get('test-cors', function () {
        return response()->json(['message' => 'CORS is working!']);
    })->name('test-cors');
});

// Protected routes that require authentication
Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('user', [AuthController::class, 'user'])->name('user');
});

