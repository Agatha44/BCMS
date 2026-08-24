<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Collection\CollectionController;


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('tolls')->group(function () {
        Route::get('toll-transactions', [CollectionController::class, 'getTollTransactions'])->name('toll-transactions');
    });
});
