<?php

use App\Http\Controllers\Bms\Payroll\BenefitTypeController;
use App\Http\Controllers\Bms\Payroll\ArrearsReasonController;
use App\Http\Controllers\Bms\Payroll\DeductionTypeController;
use App\Http\Controllers\Bms\Payroll\EmployeeBenefitController;
use App\Http\Controllers\Bms\Payroll\EmployeeDeductionController;
use App\Http\Controllers\Bms\Payroll\EmployeeArrearsController;
use App\Http\Controllers\Bms\Payroll\EmployeeLoanController;
use App\Http\Controllers\Bms\Payroll\LoanTypeController;
use App\Http\Controllers\Bms\Payroll\PayrollController;
use App\Http\Controllers\Bms\Payroll\PayrollReportController;
use App\Http\Controllers\Erms\ErmsPayableCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/payroll/reports/types', [PayrollReportController::class, 'listReportTypes'])->name('payroll-report-types');
    Route::post('/payroll/reports', [PayrollReportController::class, 'show'])->name('payroll-reports');
    Route::post('/payroll/reports/download', [PayrollReportController::class, 'download'])->name('payroll-reports-download');

    Route::get('/payroll', [PayrollController::class, 'ListPayrollRuns'])->name('payroll-index');
    // Static endpoints must come before `/payroll/{id}` to avoid route conflicts.
    Route::get('/payroll/summary', [PayrollController::class, 'getPayrollSummary'])->name('payroll-summary');
    Route::post('/payslips', [PayrollController::class, 'getPayslip'])->name('payslips');
    Route::post('/payslips/pdf', [PayrollController::class, 'getPayslipPdf'])->name('payslips-pdf');

    Route::get('/payroll/{id}', [PayrollController::class, 'showPayrollRun'])
        ->whereNumber('id')
        ->name('payroll-show');
    Route::post('/payroll-runs/prepare', [PayrollController::class, 'preparePayrollRun'])->name('payroll-prepare');
    Route::patch('/payroll/{id}/workflow', [PayrollController::class, 'updateWorkflow'])
        ->whereNumber('id')
        ->name('payroll-workflow');
    Route::post('/payroll-runs/{id}/process', [PayrollController::class, 'processPayrollRun'])->name('payroll-process');
    Route::get('/payroll/{id}/transactions', [PayrollController::class, 'getPayrollTransactions'])
        ->whereNumber('id')
        ->name('payroll-transactions');
    Route::get('/payroll/{id}/journal', [PayrollController::class, 'getPayrollJournal'])
        ->whereNumber('id')
        ->name('payroll-journal');
    Route::get('/payroll/{id}/erms-executions', [PayrollController::class, 'listErmsExecutions'])
        ->whereNumber('id')
        ->name('payroll-erms-executions');
    Route::post('/payroll/{id}/erms-repost', [PayrollController::class, 'repostFailedErmsExecutions'])
        ->whereNumber('id')
        ->name('payroll-erms-repost');

    Route::get('/benefit-types', [BenefitTypeController::class, 'ListBenefitTypes'])->name('benefit-types');
    Route::get('/benefit-types/active', [BenefitTypeController::class, 'getActiveBenefitTypes'])->name('benefit-types-active');
    Route::post('/benefit-types', [BenefitTypeController::class, 'createBenefitType'])->name('create-benefit-type');
    Route::patch('/benefit-types/{id}', [BenefitTypeController::class, 'toggleBenefitTypeStatus'])->name('toggle-benefit-type-status');
    Route::get('/benefit-types/{id}', [BenefitTypeController::class, 'showBenefitType'])->name('get-benefit-type');
    Route::put('/benefit-types/{id}', [BenefitTypeController::class, 'updateBenefitType'])->name('update-benefit-type');
    Route::delete('/benefit-types/{id}', [BenefitTypeController::class, 'destroyBenefitType'])->name('delete-benefit-type');

    Route::get('/employee-benefits', [EmployeeBenefitController::class, 'ListEmployeeBenefits'])->name('employee-benefits');
    Route::get('/employee-benefits/active', [EmployeeBenefitController::class, 'getActiveEmployeeBenefits'])->name('employee-benefits-active');
    Route::post('/employee-benefits', [EmployeeBenefitController::class, 'createEmployeeBenefit'])->name('create-employee-benefit');
    Route::get('/employee-benefits/{id}', [EmployeeBenefitController::class, 'showEmployeeBenefit'])->name('get-employee-benefit');
    Route::put('/employee-benefits/{id}', [EmployeeBenefitController::class, 'updateEmployeeBenefit'])->name('update-employee-benefit');
    Route::patch('/employee-benefits/{id}', [EmployeeBenefitController::class, 'toggleEmployeeBenefitStatus'])->name('toggle-employee-benefit-status');
    Route::delete('/employee-benefits/{id}', [EmployeeBenefitController::class, 'destroyEmployeeBenefit'])->name('delete-employee-benefit');

    Route::get('/deduction-types', [DeductionTypeController::class, 'ListDeductionTypes'])->name('deduction-types');
    Route::get('/deduction-types/active', [DeductionTypeController::class, 'getActiveDeductionTypes'])->name('deduction-types-active');
    Route::post('/deduction-types', [DeductionTypeController::class, 'createDeductionType'])->name('create-deduction-type');
    Route::patch('/deduction-types/{id}', [DeductionTypeController::class, 'toggleDeductionTypeStatus'])->name('toggle-deduction-type-status');
    Route::get('/deduction-types/{id}', [DeductionTypeController::class, 'showDeductionType'])->name('get-deduction-type');
    Route::put('/deduction-types/{id}', [DeductionTypeController::class, 'updateDeductionType'])->name('update-deduction-type');
    Route::delete('/deduction-types/{id}', [DeductionTypeController::class, 'destroyDeductionType'])->name('delete-deduction-type');

    Route::get('/employee-deductions', [EmployeeDeductionController::class, 'ListEmployeeDeductions'])->name('employee-deductions');
    Route::get('/employee-deductions/active', [EmployeeDeductionController::class, 'getActiveEmployeeDeductions'])->name('employee-deductions-active');
    Route::post('/employee-deductions', [EmployeeDeductionController::class, 'createEmployeeDeduction'])->name('create-employee-deduction');
    Route::post('/employee-deductions/sync-mandatory', [EmployeeDeductionController::class, 'syncMandatoryDeductions'])->name('sync-mandatory-deductions');
    Route::post('/employee-deductions/recalculate/{nationalId}', [EmployeeDeductionController::class, 'recalculateForEmployee'])->name('recalculate-employee-deductions');
    Route::get('/employee-deductions/{id}', [EmployeeDeductionController::class, 'showEmployeeDeduction'])->name('get-employee-deduction');
    Route::put('/employee-deductions/{id}', [EmployeeDeductionController::class, 'updateEmployeeDeduction'])->name('update-employee-deduction');
    Route::patch('/employee-deductions/{id}', [EmployeeDeductionController::class, 'toggleEmployeeDeductionStatus'])->name('toggle-employee-deduction-status');
    Route::delete('/employee-deductions/{id}', [EmployeeDeductionController::class, 'destroyEmployeeDeduction'])->name('delete-employee-deduction');

    Route::get('/loan-types', [LoanTypeController::class, 'ListLoanTypes'])->name('loan-types');
    Route::get('/loan-types/active', [LoanTypeController::class, 'getActiveLoanTypes'])->name('loan-types-active');
    Route::post('/loan-types', [LoanTypeController::class, 'createLoanType'])->name('create-loan-type');
    Route::patch('/loan-types/{id}', [LoanTypeController::class, 'toggleLoanTypeStatus'])->name('toggle-loan-type-status');
    Route::get('/loan-types/{id}', [LoanTypeController::class, 'showLoanType'])->name('get-loan-type');
    Route::put('/loan-types/{id}', [LoanTypeController::class, 'updateLoanType'])->name('update-loan-type');
    Route::delete('/loan-types/{id}', [LoanTypeController::class, 'destroyLoanType'])->name('delete-loan-type');

    Route::get('/employee-loans', [EmployeeLoanController::class, 'ListEmployeeLoans'])->name('employee-loans');
    Route::post('/employee-loans', [EmployeeLoanController::class, 'createEmployeeLoan'])->name('create-employee-loan');
    Route::get('/employee-loans/{id}', [EmployeeLoanController::class, 'showEmployeeLoan'])
        ->whereNumber('id')
        ->name('get-employee-loan');
    Route::put('/employee-loans/{id}', [EmployeeLoanController::class, 'updateEmployeeLoan'])
        ->whereNumber('id')
        ->name('update-employee-loan');
    Route::patch('/employee-loans/{id}', [EmployeeLoanController::class, 'toggleEmployeeLoan'])
        ->whereNumber('id')
        ->name('toggle-employee-loan-status');

    Route::get('/arrears-reasons', [ArrearsReasonController::class, 'ListArrearsReasons'])->name('arrears-reasons');
    Route::get('/arrears-reasons/active', [ArrearsReasonController::class, 'getActiveArrearsReasons'])->name('arrears-reasons-active');
    Route::post('/arrears-reasons', [ArrearsReasonController::class, 'createArrearsReason'])->name('create-arrears-reason');
    Route::patch('/arrears-reasons/{id}', [ArrearsReasonController::class, 'toggleArrearsReasonStatus'])->name('toggle-arrears-reason-status');
    Route::get('/arrears-reasons/{id}', [ArrearsReasonController::class, 'showArrearsReason'])->name('get-arrears-reason');
    Route::put('/arrears-reasons/{id}', [ArrearsReasonController::class, 'updateArrearsReason'])->name('update-arrears-reason');
    Route::delete('/arrears-reasons/{id}', [ArrearsReasonController::class, 'destroyArrearsReason'])->name('delete-arrears-reason');

    Route::get('/employee-arrears', [EmployeeArrearsController::class, 'ListEmployeeArrears'])->name('employee-arrears');
    Route::post('/employee-arrears', [EmployeeArrearsController::class, 'createEmployeeArrears'])->name('create-employee-arrears');
    Route::get('/employee-arrears/{id}', [EmployeeArrearsController::class, 'showEmployeeArrears'])->name('get-employee-arrears');
    Route::put('/employee-arrears/{id}', [EmployeeArrearsController::class, 'updateEmployeeArrears'])->name('update-employee-arrears');
    Route::patch('/employee-arrears/{id}', [EmployeeArrearsController::class, 'toggleEmployeeArrearsStatus'])->name('toggle-employee-arrears-status');
    Route::delete('/employee-arrears/{id}', [EmployeeArrearsController::class, 'destroyEmployeeArrears'])->name('delete-employee-arrears');

    // Workflow: pending -> verified -> approved / rejected
    Route::patch('/employee-arrears/{id}/workflow', [EmployeeArrearsController::class, 'updateWorkflow'])->name('employee-arrears-workflow');
});


Route::get('/payroll/jv-document/{run}', [PayrollController::class, 'getJvDocumentEndpoint'])->name('payroll-jv-document');
Route::get('/payroll/payee-document/{run}', [PayrollController::class, 'getPayeeDocumentEndpoint'])->name('payroll-payee-document');
Route::get('/payroll/psssf-document/{run}', [PayrollController::class, 'getPsssfDocumentEndpoint'])->name('payroll-psssf-document');
Route::get('/payroll/heslb-document/{run}', [PayrollController::class, 'getHeslbDocumentEndpoint'])->name('payroll-heslb-document');
Route::get('/payroll/net-pay-document/{run}', [PayrollController::class, 'getNetPayDocumentEndpoint'])->name('payroll-net-pay-document');
Route::get('/payroll/minutes-document/{run}', [PayrollController::class, 'getMinutesDocumentEndpoint'])->name('payroll-minutes-document');


Route::post('/erms-payable/callback', [ErmsPayableCallbackController::class, 'callback'])->name('erms-payable-callback');