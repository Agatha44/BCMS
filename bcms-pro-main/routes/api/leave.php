<?php

use App\Http\Controllers\Bms\LeaveApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/leave/applications', [LeaveApplicationController::class, 'index'])->name('leave-applications-index');
    Route::post('/leave/applications', [LeaveApplicationController::class, 'store'])->name('leave-applications-store');
    Route::post('/leave/applications/{id}/verify', [LeaveApplicationController::class, 'verify'])->whereNumber('id')->name('leave-applications-verify');
    Route::post('/leave/applications/{id}/approve', [LeaveApplicationController::class, 'approve'])->whereNumber('id')->name('leave-applications-approve');
    Route::post('/leave/applications/{id}/reject', [LeaveApplicationController::class, 'reject'])->whereNumber('id')->name('leave-applications-reject');
    Route::get('/leave/applications/{id}/document', [LeaveApplicationController::class, 'downloadDocument'])->whereNumber('id')->name('leave-applications-document');
});
