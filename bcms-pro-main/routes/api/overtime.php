<?php

use App\Http\Controllers\Bms\OvertimeController;
use App\Http\Controllers\Bms\OvertimeRatesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // BMS OVERTIME RATES APIs
    Route::get('/overtime-rates', [OvertimeRatesController::class, 'index'])->name('overtime-rates');
    Route::get('/overtime-rates/active', [OvertimeRatesController::class, 'getActiveRates'])->name('overtime-rates-active');
    Route::get('/overtime-rates/educational-level/{educationalLevelId}', [OvertimeRatesController::class, 'getByEducationalLevel'])->name('overtime-rates-by-educational-level');
    Route::post('/overtime-rates', [OvertimeRatesController::class, 'store'])->name('create-overtime-rate');
    Route::get('/overtime-rates/{id}', [OvertimeRatesController::class, 'show'])->name('get-overtime-rate');
    Route::put('/overtime-rates/{id}', [OvertimeRatesController::class, 'update'])->name('update-overtime-rate');
    Route::put('/overtime-rates/{id}/status', [OvertimeRatesController::class, 'toggleStatus'])->name('toggle-overtime-rate-status');
    Route::delete('/overtime-rates/{id}', [OvertimeRatesController::class, 'destroy'])->name('delete-overtime-rate');

    // OVERTIME MANAGEMENT APIs
    Route::get('/overtime', [OvertimeController::class, 'index'])->name('overtime-index');
    Route::post('/overtime', [OvertimeController::class, 'store'])->name('overtime-store');
    Route::post('/overtime/check-date', [OvertimeController::class, 'checkOvertimeForDate'])->name('overtime-check-date');
    Route::post('/overtime/validate-amount', [OvertimeController::class, 'validateOvertimeAmount'])->name('overtime-validate-amount');

    // OVERTIME SUBMISSION DOCUMENTS (must come before /overtime/{id} to avoid route conflicts)
    Route::get('/overtime/submission-documents/types', [OvertimeController::class, 'listSubmissionDocumentTypes'])->name('overtime-submission-document-types');
    Route::get('/overtime/submission-documents', [OvertimeController::class, 'listSubmissionDocuments'])->name('overtime-submission-documents-index');
    Route::post('/overtime/submission-documents', [OvertimeController::class, 'uploadSubmissionDocument'])->name('overtime-submission-documents-store');
    Route::get('/overtime/submission-documents/{id}', [OvertimeController::class, 'showSubmissionDocument'])->whereNumber('id')->name('overtime-submission-documents-show');
    Route::put('/overtime/submission-documents/{id}', [OvertimeController::class, 'updateSubmissionDocument'])->whereNumber('id')->name('overtime-submission-documents-update');
    Route::post('/overtime/submission-documents/{id}/toggle-status', [OvertimeController::class, 'toggleSubmissionDocumentStatus'])->whereNumber('id')->name('overtime-submission-documents-toggle-status');
    Route::get('/overtime/submission-documents/{id}/download', [OvertimeController::class, 'downloadSubmissionDocument'])->whereNumber('id')->name('overtime-submission-documents-download');

    // OVERTIME BATCH MANAGEMENT APIs (must come before /overtime/{id} to avoid route conflicts)
    Route::get('/overtime/batches/ready', [OvertimeController::class, 'getReadyForBatch'])->name('overtime-ready-for-batch');
    Route::get('/overtime/batches', [OvertimeController::class, 'listBatches'])->name('overtime-list-batches');
    Route::post('/overtime/batches', [OvertimeController::class, 'createBatch'])->name('overtime-create-batch');
    Route::get('/overtime/batches/{batchId}/submission-readiness', [OvertimeController::class, 'getBatchSubmissionReadiness'])
        ->whereNumber('batchId')
        ->name('overtime-batch-submission-readiness');
    Route::get('/overtime/batches/{batchId}', [OvertimeController::class, 'getBatch'])->name('overtime-get-batch');
    Route::post('/overtime/batches/{batchId}/erms-repost', [OvertimeController::class, 'repostFailedErmsSubmission'])
        ->whereNumber('batchId')
        ->name('overtime-batch-erms-repost');
    Route::post('/overtime/batches/{batchId}/erms-submit', [OvertimeController::class, 'submitBatchToErms'])
        ->whereNumber('batchId')
        ->name('overtime-batch-erms-submit');
    Route::post('/overtime/batches/{batchId}/submit', [OvertimeController::class, 'saveAndSubmitBatch'])->name('overtime-submit-batch');

    // OVERTIME EMPLOYEE ROUTE (must come before /overtime/{id})
    Route::get('/overtime/employee', [OvertimeController::class, 'getEmployeeOvertime'])->name('overtime-employee');
    Route::get('/overtime/employee/{pfNumber}', [OvertimeController::class, 'getEmployeeOvertime'])->name('overtime-employee-by-pf');

    // OVERTIME MY ACTIONS (must come before /overtime/{id})
    Route::get('/overtime/my-actions', [OvertimeController::class, 'getMyActions'])->name('overtime-my-actions');

    // OVERTIME PARAMETERIZED ROUTES (must come after all specific routes)
    Route::get('/overtime/{id}', [OvertimeController::class, 'show'])->name('overtime-show');
    Route::put('/overtime/{id}/status', [OvertimeController::class, 'updateStatus'])->name('overtime-update-status');
    Route::put('/overtime/{id}/validator-approve', [OvertimeController::class, 'validatorApprove'])->name('overtime-validator-approve');
    Route::put('/overtime/{id}/reviewer-approve', [OvertimeController::class, 'reviewerApprove'])->name('overtime-reviewer-approve');
    Route::put('/overtime/{id}/reject', [OvertimeController::class, 'reject'])->name('overtime-reject');
    Route::put('/overtime/{id}/return', [OvertimeController::class, 'returnOvertimeRequest'])->name('overtime-return-to-applicant');
});

// E-OFFICE FEEDBACK api
Route::post('/overtime/feedback/status-update', [OvertimeController::class, 'eOfficeFeedbackStatusUpdate'])->name('overtime-eOffice-status-update');

Route::get('/overtime/overtime-document/{batch_number}', [OvertimeController::class, 'getOvertimeDocumentEndpoint'])
    ->name('overtime-overtime-document');

Route::get('/overtime/invoice-document/{batch_number}', [OvertimeController::class, 'getInvoiceDocumentEndpoint'])->name('overtime-invoice-document');
