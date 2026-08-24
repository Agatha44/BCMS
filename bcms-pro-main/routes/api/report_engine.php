<?php

use App\Http\Controllers\ReportEngineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('report-engine')->name('report-engine.')->group(function () {
        Route::get('registrations/active', [ReportEngineController::class, 'activeRegistrations']);
        Route::get('registrations', [ReportEngineController::class, 'listRegistrations']);
        Route::post('registrations', [ReportEngineController::class, 'storeRegistration']);
        Route::put('registrations/{id}', [ReportEngineController::class, 'updateRegistration']);

        Route::get('registrations/{registrationId}/definitions', [ReportEngineController::class, 'adminDefinitions']);
        Route::get('definitions/{definitionId}', [ReportEngineController::class, 'showDefinition']);
        Route::post('registrations/{registrationId}/definitions', [ReportEngineController::class, 'storeDefinition']);
        Route::put('definitions/{definitionId}', [ReportEngineController::class, 'updateDefinition']);
        Route::delete('definitions/{definitionId}', [ReportEngineController::class, 'deleteDefinition']);
        Route::post('definitions/{definitionId}/restore', [ReportEngineController::class, 'restoreDefinition']);

        Route::get('{moduleSlug}/catalog', [ReportEngineController::class, 'catalog']);
        Route::get('{moduleSlug}/reports', [ReportEngineController::class, 'listReports']);
        Route::get('{moduleSlug}/reports/{reportName}/params', [ReportEngineController::class, 'reportParams']);
        Route::post('{moduleSlug}/reports/{reportName}/generate', [ReportEngineController::class, 'generateReport']);
        Route::post('{moduleSlug}/reports/{reportName}/export-audit', [ReportEngineController::class, 'logReportExport']);
    });
});
