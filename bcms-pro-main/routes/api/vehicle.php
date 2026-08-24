<?php

use App\Http\Controllers\Vehicle\VehicleController;
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
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('vehicle/image/{vehicle_id}', [VehicleController::class, 'getVehicleImage'])->name('vehicle-image');
    Route::put('vehicles/image', [VehicleController::class, 'updateVehicleImage'])->name('vehicle-update-image');
    Route::get('vehicle/details', [VehicleController::class, 'getVehicleData'])->name('vehicle-data');

    // Vehicle Association
    Route::post('vehicles/associate', [VehicleController::class, 'associateVehicle'])->name('vehicle-associate');
    Route::post('vehicles/disassociate', [VehicleController::class, 'disassociateVehicle'])->name('vehicle-disassociate');

    // Vehicle Enrollment
    Route::post('vehicles/associate-vehicle', [VehicleController::class, 'actionEnrollVehicleAssociate'])->name('vehicle-enroll');

    // Vehicle Disassociation
    Route::post('vehicles/disassociate-vehicle', [VehicleController::class, 'actionDisassociateVehicle'])->name('vehicle-disenroll');

    // Vehicle Search for TypeAhead
    Route::get('vehicles/search-unassociated', [VehicleController::class, 'searchUnassociatedVehicles'])->name('vehicle-search-unassociated');

    // Vehicle Search for Debugging (all vehicles)
    Route::get('vehicles/search-all', [VehicleController::class, 'searchAllVehicles'])->name('vehicle-search-all');

    // Vehicle Management (Admin Routes)
    Route::post('vehicles/create', [VehicleController::class, 'createVehicle'])->name('vehicle-create');
    Route::get('vehicles/all', [VehicleController::class, 'getAllVehicles'])->name('vehicle-all');
    Route::get('vehicles/lookup', [VehicleController::class, 'searchVehicleDetails'])->name('vehicle-lookup');
    Route::get('vehicles/recent-transactions', [VehicleController::class, 'recentTollTransactions'])->name('vehicle-recent-transactions');
    Route::get('vehicles/{id}', [VehicleController::class, 'getVehicleById'])->name('vehicle-by-id');
    Route::put('vehicles/update', [VehicleController::class, 'updateVehicle'])->name('vehicle-update');
    Route::post('vehicles/update', [VehicleController::class, 'updateVehicle'])->name('vehicle-update-post');
    Route::post('vehicles/activate', [VehicleController::class, 'activateVehicle'])->name('vehicle-activate');
    Route::post('vehicles/deactivate', [VehicleController::class, 'deactivateVehicle'])->name('vehicle-deactivate');
    Route::post('vehicles/clear-image', [VehicleController::class, 'clearVehicleImage'])->name('vehicle-clear-image');
    Route::post('vehicles/query', [VehicleController::class, 'queryVehicle'])->name('vehicle-query');
    Route::post('vehicles/exempt', [VehicleController::class, 'exemptVehicle'])->name('vehicle-exempt');
    Route::post('vehicles/remove-exempt', [VehicleController::class, 'removeExemptedVehicle'])->name('vehicle-remove-exempt');
    
    // Get vehicles by account ID
    Route::get('vehicles/account/{accountId}', [VehicleController::class, 'getVehiclesByAccountId'])->name('vehicles-by-account');
    
    // Fetch vehicle details by plate number
    Route::post('vehicles/fetch', [VehicleController::class, 'fetchVehicle'])->name('fetch-vehicle');

    // Fetch norma passages
    Route::post('vehicles/fetch-normal-passages', [VehicleController::class, 'fetchNormaPassages'])->name('fetch-norma-passages'); 

    // Fetch bundle passages
    Route::post('vehicles/fetch-bundle-passages', [VehicleController::class, 'fetchBundlePassages'])->name('fetch-bundle-passages'); 
    
    // Fetch bundle subscriptions
    Route::post('vehicles/fetch-bundle-subscriptions', [VehicleController::class, 'fetchBundleSubscriptions'])->name('fetch-bundle-subscriptions'); 

    // Remove vehicle with dependency checks
    Route::post('vehicles/remove', [VehicleController::class, 'removeVehicle'])->name('vehicle-remove');
});

