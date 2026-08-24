<?php

use App\Http\Controllers\CollectionManagement\VehicleCollectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Collection management API
|--------------------------------------------------------------------------
|
| Back-office style endpoints for vehicle listing and detail (with image).
|
*/

Route::middleware(['auth:sanctum'])->prefix('collection-management')->group(function () {
    Route::get('vehicles', [VehicleCollectionController::class, 'index'])->name('collection-management-vehicles');
    Route::put('vehicles/{id}', [VehicleCollectionController::class, 'update'])->name('collection-management-vehicle-update');
    Route::post('vehicles/{id}/update', [VehicleCollectionController::class, 'update'])->name('collection-management-vehicle-update-post');
    Route::get('vehicles/{id}', [VehicleCollectionController::class, 'show'])->name('collection-management-vehicle-show');
});
