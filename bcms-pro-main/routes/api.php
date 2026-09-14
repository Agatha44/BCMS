<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Administration\AdministrationController;
use App\Http\Controllers\Advertisement\AdvertisementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Bms\AttendanceLogController;
use App\Http\Controllers\Bms\BridgeEmployeeApprovalController;
use App\Http\Controllers\Bms\BridgeEmployeeController;
use App\Http\Controllers\Bms\BridgeModuleController;
use App\Http\Controllers\Bms\EducationalLevelController;
use App\Http\Controllers\Bms\Payroll\PayrollController;
use App\Http\Controllers\Bms\SpecialTaskController;
use App\Http\Controllers\Bms\PublicHolidayController;
use App\Http\Controllers\Bms\PermissionController;
use App\Http\Controllers\Bms\ReferralRequestController;
use App\Http\Controllers\Bms\RoleController;
use App\Http\Controllers\Bms\RolePermissionController;
use App\Http\Controllers\TopUp\TopUpController;
//use App\Http\Controllers\Bms\UserFingerprintController;
use App\Http\Controllers\BodyTypesController\BodyTypesController;
use App\Http\Controllers\Booth\BoothController;
use App\Http\Controllers\Booth\PayAndGoController;
use App\Http\Controllers\Booth\TollCaptureController;
use App\Http\Controllers\Bms\BridgeModuleMenuController;
use App\Http\Controllers\Bms\BridgeModuleRoleMenuController;
use App\Http\Controllers\Bms\BridgeModuleRoleController;
use App\Http\Controllers\BundlesController\BundleController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportManagementController;
use App\Http\Controllers\Reports\CollectionReportController;
use App\Http\Controllers\Dashboard\CollectionDashboardController;
use \App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\Lane\LaneController;
use \App\Http\Controllers\DataController\DataController;
use \App\Http\Controllers\Vehicle\VehicleController;
use App\Http\Controllers\Receipts\NssfReceiptController;
use App\Http\Controllers\Receipts\ReceiptController;
use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\Registration\RegistrationRequestController;
use App\Http\Controllers\PassagesController;
use App\Http\Controllers\RFIDGateController;
use App\Http\Controllers\IncidentFine\IncidentFineController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\Shift\ShiftController;
use App\Http\Controllers\EventPayment\EventPaymentController;
use App\Http\Controllers\FineCharge\FineChargeController;
use App\Http\Controllers\OverloadFine\OverloadFineController;
use App\Http\Controllers\Bms\RegionController;
use App\Http\Controllers\Bms\DistrictController;
use App\Http\Controllers\Bms\BankController;
use App\Http\Controllers\Bms\SchemeController;
use App\Http\Controllers\Bms\BridgeEmploymentTypeController;
use App\Http\Controllers\Bms\BridgeShiftController;
use App\Http\Controllers\Bms\BridgeShiftDepartmentController;
use App\Http\Controllers\Bms\DepartmentController;
use App\Http\Controllers\BridgeStatusController;
use App\Models\BridgeBill;
use App\Services\Erms\ErmsPayloadSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

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

include __DIR__ . '/api/auth.php';
include __DIR__ . '/api/portal.php';
include __DIR__ . '/api/vehicle.php';
include __DIR__ . '/api/collection_management.php';
include __DIR__ . '/api/billing.php';
include __DIR__ . '/api/ussd.php';
include __DIR__ . '/api/erms.php';
include __DIR__ . '/api/payroll.php';
include __DIR__ . '/api/overtime.php';
include __DIR__ . '/api/leave.php';
include __DIR__ . '/api/collection.php';
include __DIR__ . '/api/report_engine.php';

// QR Code Decryption Routes...
use App\Http\Controllers\QrController;
Route::post('qr/decrypt', [QrController::class, 'decryptQrData'])->name('qr.decrypt');
Route::get('qr/health', [QrController::class, 'healthCheck'])->name('qr.health');


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('commuter-details', [PortalController::class, 'commuter'])->name('commuter-details');
    Route::post('commuter-vehicles', [PortalController::class, 'commuterVehicles'])->name('commuter-vehicles');
    Route::post('commuter-vehicle-passage', [PortalController::class, 'commuterVehiclePassage'])->name('commuter-vehicle-passage');
    Route::post('commuter-bundle-transactions', [PortalController::class, 'commuterBundleTransactions'])->name('commuter-bundle-transactions');
    Route::post('commuter-top-ups', [PortalController::class, 'commuterTopUps'])->name('commuter-top-ups');
    Route::post('commuter-bundles', [PortalController::class, 'commuterBundles'])->name('commuter-bundles');
    Route::post('commuter-active-bundle', [PortalController::class, 'commuterActiveBundle'])->name('commuter-active-bundle');
    Route::post('commuter-check-control-number', [PortalController::class, 'commuterCheckControlNumber'])->name('commuter-check-control-number');

    Route::get('list-bundle-types', [BundleController::class, 'getTollBundleTypes'])->name('list-bundle-types');
    Route::get('get-vehicle-price', [BundleController::class, 'fetchVehiclePrice'])->name('get-vehicle-price');
    Route::post('toll-bundle-bills', [BundleController::class, 'getTollBundleBills'])->name('toll-bundle-bills');
    Route::post('toll-bundle-bills/order-form', [BundleController::class, 'orderForm'])->name('toll-bundle-bills.order-form');
    Route::post('toll-bundle-bills/repost', [BundleController::class, 'repost'])->name('toll-bundle-bills.repost');
    Route::post('toll-bundle-subscriptions', [BundleController::class, 'tollBundleSubscriptions'])->name('toll-bundle-subscriptions');
    Route::post('top-up-bills', [TopUpController::class, 'topUpBills'])->name('top-up-bills');
    Route::post('top-up-bills/cancel', [TopUpController::class, 'cancel'])->name('top-up-bills.cancel');
    Route::post('top-up-bills/repost', [TopUpController::class, 'repost'])->name('top-up-bills.repost');
    Route::post('advert-bills', [AdvertisementController::class, 'index'])->name('advert-bills');
    Route::post('advert-bills/create', [AdvertisementController::class, 'store'])->name('advert-bills.create');
    Route::post('advert-bills/order-form', [AdvertisementController::class, 'orderForm'])->name('advert-bills.order-form');
    Route::post('advert-bills/cancel', [AdvertisementController::class, 'cancel'])->name('advert-bills.cancel');
    Route::post('event-bills', [EventPaymentController::class, 'index'])->name('event-bills');
    Route::post('event-bills/create', [EventPaymentController::class, 'store'])->name('event-bills.create');
    Route::post('event-bills/cancel', [EventPaymentController::class, 'cancel'])->name('event-bills.cancel');
    Route::post('incident-bills', [IncidentFineController::class, 'index'])->name('incident-bills');
    Route::post('incident-bills/create', [IncidentFineController::class, 'store'])->name('incident-bills.create');
    Route::post('incident-bills/cancel', [IncidentFineController::class, 'cancel'])->name('incident-bills.cancel');
    Route::post('fine-charge-bills', [FineChargeController::class, 'index'])->name('fine-charge-bills');
    Route::post('fine-charge-bills/create', [FineChargeController::class, 'store'])->name('fine-charge-bills.create');
    Route::post('fine-charge-bills/cancel', [FineChargeController::class, 'cancel'])->name('fine-charge-bills.cancel');
    Route::get('end-of-shift/shifts', [ShiftController::class, 'shifts'])->name('end-of-shift.shifts');
    Route::post('end-of-shift/shift-amount', [ShiftController::class, 'shiftAmount'])->name('end-of-shift.shift-amount');
    Route::post('end-of-shift/process', [ShiftController::class, 'postErpReceipt'])->name('end-of-shift.process');
    Route::post('end-of-shift-bills', [ShiftController::class, 'billsIndex'])->name('end-of-shift-bills');
    Route::post('end-of-shift-bills/create', [ShiftController::class, 'createShiftBill'])->name('end-of-shift-bills.create');
    Route::post('end-of-shift-bills/cancel', [ShiftController::class, 'cancelBill'])->name('end-of-shift-bills.cancel');
    Route::post('end-of-shift-bills/reuse', [ShiftController::class, 'reuseBill'])->name('end-of-shift-bills.reuse');
    Route::post('end-of-shift-bills/order-form', [ShiftController::class, 'orderForm'])->name('end-of-shift-bills.order-form');
    Route::post('end-of-shift-receipts', [ShiftController::class, 'receiptsIndex'])->name('end-of-shift-receipts');
    Route::get('end-of-shift-receipts/{id}', [ShiftController::class, 'receiptDetails'])->name('end-of-shift-receipts.details');
    Route::get('end-of-shift-receipts/{id}/print-receipt', [ShiftController::class, 'printReceipt'])->name('end-of-shift-receipts.print');

    Route::post('shift-record/query-shift', [App\Http\Controllers\Shift\ShiftRecordController::class, 'queryShift'])->name('shift-record.query-shift');
    Route::get('shift-record/get-shift/{id}', [App\Http\Controllers\Shift\ShiftRecordController::class, 'getShift'])->name('shift-record.get-shift');
    Route::post('shift/get-wrong-shift-amount', [App\Http\Controllers\Shift\ShiftController::class, 'getWrongShiftAmount'])->name('shift.get-wrong-shift-amount');
    Route::post('shift/update-wrong-shift', [App\Http\Controllers\Shift\ShiftController::class, 'updateWrongShift'])->name('shift.update-wrong-shift');

    Route::prefix('collection-reports')->name('collection-reports.')->group(function () {
        Route::post('daily-collection', [CollectionReportController::class, 'dailyCollection']);
        Route::post('daily-shift-collection', [CollectionReportController::class, 'dailyShiftCollection']);
        Route::post('body-type-collection', [CollectionReportController::class, 'bodyTypeCollection']);
        Route::post('booth-collection', [CollectionReportController::class, 'boothCollection']);
        Route::post('body-type-audit', [CollectionReportController::class, 'bodyTypeAudit']);
        Route::post('exempted-vehicles', [CollectionReportController::class, 'exemptedVehicles']);
        Route::post('daily-cashless', [CollectionReportController::class, 'dailyCashlessCollection']);
        Route::post('body-cashless', [CollectionReportController::class, 'bodyCashlessCollection']);
        Route::post('cancelled-transactions', [CollectionReportController::class, 'cancelledTransactions']);
        Route::post('bundle-collection', [CollectionReportController::class, 'bundleCollection']);
        Route::post('bundle-registration', [CollectionReportController::class, 'bundleRegistration']);
        Route::post('bundle-subscription', [CollectionReportController::class, 'bundleSubscription']);
        Route::post('vehicle-passage', [CollectionReportController::class, 'vehiclePassage']);
        Route::post('vehicle-passage/paginated', [CollectionReportController::class, 'vehiclePassagePaginated']);
        Route::post('toll-collection-detail', [CollectionReportController::class, 'tollCollectionDetail']);
        Route::post('payment-reconciliation', [CollectionReportController::class, 'paymentReconciliation']);
        Route::post('end-of-shift-overall', [CollectionReportController::class, 'endOfShiftOverall']);
        Route::post('shift-summary', [CollectionReportController::class, 'shiftSummary']);
        Route::post('shift-summary-audit', [CollectionReportController::class, 'shiftSummaryAudit']);
        Route::post('toll-collection-summary', [CollectionReportController::class, 'tollCollectionSummary']);
        Route::post('incident-collection-summary', [CollectionReportController::class, 'incidentCollectionSummary']);
        Route::post('overload-collection-summary', [CollectionReportController::class, 'overloadCollectionSummary']);
        Route::post('event-collection-summary', [CollectionReportController::class, 'eventCollectionSummary']);
        Route::post('monthly-collection-summary', [CollectionReportController::class, 'monthlyCollectionSummary']);
        Route::post('shift-collection', [CollectionReportController::class, 'shiftCollection']);
    });

    Route::prefix('collection-dashboard')->name('collection-dashboard.')->group(function () {
        Route::get('kpis', [CollectionDashboardController::class, 'kpis']);
        Route::get('body-types', [CollectionDashboardController::class, 'bodyTypes']);
        Route::get('lane-performance', [CollectionDashboardController::class, 'lanePerformance']);
        Route::get('toll-trends', [CollectionDashboardController::class, 'tollTrends']);
        Route::get('financial-years', [CollectionDashboardController::class, 'financialYears']);
    });
    Route::get('bundle-purchases', [BundleController::class, 'listBundlePurchases'])->name('bundle-purchases');
    Route::post('bundle-subscriptions/edit-bundle', [BundleController::class, 'editBundle'])->name('bundle-subscriptions.edit-bundle');
    Route::post('bundle-subscriptions/transfer-vehicle', [BundleController::class, 'transferBundleToVehicle'])->name('bundle-subscriptions.transfer-vehicle');
    Route::post('bundle-subscriptions/repair-overlap', [BundleController::class, 'repairOverlappingBundle'])->name('bundle-subscriptions.repair-overlap');

    // Payment method management routes
    Route::get('payment-methods/dropdown', [App\Http\Controllers\Administration\PaymentMethodController::class, 'getForDropdown'])->name('payment-methods-dropdown');
    Route::get('payment-methods', [App\Http\Controllers\Administration\PaymentMethodController::class, 'index'])->name('payment-methods-list');
    Route::get('payment-methods/{id}', [App\Http\Controllers\Administration\PaymentMethodController::class, 'show'])->name('get-payment-method');
    Route::post('payment-methods', [App\Http\Controllers\Administration\PaymentMethodController::class, 'store'])->name('create-payment-method');
    Route::put('payment-methods/{id}', [App\Http\Controllers\Administration\PaymentMethodController::class, 'update'])->name('update-payment-method');
                Route::delete('payment-methods/{id}', [App\Http\Controllers\Administration\PaymentMethodController::class, 'destroy'])->name('delete-payment-method');

            // Price management routes
            Route::get('prices', [App\Http\Controllers\Administration\PriceController::class, 'index'])->name('prices');
            Route::get('prices/body-types', [App\Http\Controllers\Administration\PriceController::class, 'getActiveBodyTypes'])->name('active-body-types');
            Route::get('prices/active', [App\Http\Controllers\Administration\PriceController::class, 'getActivePrices'])->name('active-prices');
            Route::get('prices/{id}/audits', [App\Http\Controllers\Administration\PriceController::class, 'audits'])->name('price-audits');
            Route::get('prices/{id}', [App\Http\Controllers\Administration\PriceController::class, 'show'])->name('get-price');
            Route::post('prices', [App\Http\Controllers\Administration\PriceController::class, 'store'])->name('create-price');
            Route::put('prices/{id}', [App\Http\Controllers\Administration\PriceController::class, 'update'])->name('update-price');
            Route::put('prices/{id}/status', [App\Http\Controllers\Administration\PriceController::class, 'toggleStatus'])->name('toggle-price-status');
            Route::delete('prices/{id}', [App\Http\Controllers\Administration\PriceController::class, 'destroy'])->name('delete-price');

});

Route::get('prepayment/print-receipt/{id}', [TopUpController::class, 'printReceipt'])->name('prepayment.print-receipt');
Route::get('advert-bills/print-receipt/{id}', [AdvertisementController::class, 'printReceipt'])->name('advert-bills.print-receipt');

Route::post('post-bill', [BundleController::class, 'postBill'])->name('post-bill');

Route::get('bundles/eligible-by-plate', [BundleController::class, 'eligibleBundlesByPlate'])->name('bundles-eligible-by-plate');

Route::post('get-vehicle-bundle-info', [BundleController::class, 'getVehicleBundleInfo'])->name('get-vehicle-bundle-info');

# Account Management
Route::post('account-registration', [AccountController::class, 'accountRegistration'])->name('account-registration');
Route::post('search-account-details', [AccountController::class, 'searchAccountDetails'])->name('search-account-details');

# POS Terminal Routes (Public - no auth required)
Route::post('pos/balance-deduction', [AccountController::class, 'processBalanceDeduction'])->name('pos-balance-deduction');
Route::post('pos/register', [PosTerminalController::class, 'selfRegister'])->name('pos-self-register');
Route::get('pos/status', [PosTerminalController::class, 'deviceStatus'])->name('pos-device-status');
Route::post('pos/heartbeat', [PosTerminalController::class, 'heartbeat'])->name('pos-heartbeat');
Route::get('pos/toll-capture', [TollCaptureController::class, 'getPendingForPos'])->name('pos-toll-capture-pending');
Route::post('pos/toll-capture/payment', [TollCaptureController::class, 'processPayment'])->name('pos-toll-capture-payment');

# Toll Capture (booth/ANPR — same JSON/image pattern as POST /api/vehicles/update)
Route::post('toll-capture', [TollCaptureController::class, 'store'])->name('toll-capture-store');
Route::put('toll-capture/image', [TollCaptureController::class, 'updateCaptureImage'])->name('toll-capture-update-image');
Route::post('toll-capture/image', [TollCaptureController::class, 'updateCaptureImage'])->name('toll-capture-update-image-post');
Route::get('toll-capture/{id}/image', [TollCaptureController::class, 'showImage'])->name('toll-capture-image');
Route::post('toll-capture/cancel', [TollCaptureController::class, 'cancel'])->name('toll-capture-cancel');
Route::get('toll-capture/history', [TollCaptureController::class, 'history'])->name('toll-capture-history');
Route::match(['get', 'post'], 'status-check/bridge-status', [BridgeStatusController::class, 'bridgeStatus'])->name('status-check.bridge-status');
Route::match(['get', 'post'], 'bridge-status/{function?}', [BridgeStatusController::class, 'bridgeStatus'])->name('bridge-status');

// Account management routes (inside auth:sanctum middleware group)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('accounts', [AccountController::class, 'listAccounts'])->name('accounts');
    Route::get('accounts/top-ups', [PortalController::class, 'fetchTopUps'])->name('account-top-ups');
    Route::post('accounts/send-otp', [AccountController::class, 'sendAccountCreationOtp'])->name('accounts-send-otp');
    Route::get('accounts/{id}', [AccountController::class, 'getAccount'])->name('get-account');
    Route::post('accounts', [AccountController::class, 'createAccount'])->name('create-account');
    Route::put('accounts/{id}', [AccountController::class, 'updateAccount'])->name('update-account');
    Route::put('accounts/{id}/activate', [AccountController::class, 'activateAccount'])->name('activate-account');
    Route::put('accounts/{id}/deactivate', [AccountController::class, 'deactivateAccount'])->name('deactivate-account');

    // Card reference management routes
    Route::post('accounts/link-card', [AccountController::class, 'linkCardReference'])->name('link-card-reference');
    Route::post('accounts/unlink-card', [AccountController::class, 'unlinkCardReference'])->name('unlink-card-reference');
    Route::post('accounts/register-card', [AccountController::class, 'registerCardToAccount'])->name('register-card-to-account');
    Route::get('accounts/{id}/card-history', [AccountController::class, 'getCardHistory'])->name('get-card-history');
    Route::post('accounts/search-by-card', [AccountController::class, 'searchByCardReference'])->name('search-by-card-reference');

    // Balance management routes
    Route::get('accounts/{id}/balance-history', [AccountController::class, 'getBalanceHistory'])->name('get-balance-history');
    Route::get('accounts/{id}/history-stats', [AccountController::class, 'getAccountHistoryStats'])->name('get-account-history-stats');
    Route::get('accounts/debug-lookup', [AccountController::class, 'debugAccountLookup'])->name('debug-account-lookup');
    Route::post('accounts/transfer', [AccountTransferController::class, 'transfer'])->name('accounts-transfer');
    Route::get('accounts/transfer/pending', [AccountTransferController::class, 'listPendingTransfers'])->name('accounts-transfer-pending');
    Route::get('accounts/transfer/history', [AccountTransferController::class, 'listTransferHistory'])->name('accounts-transfer-history');
    Route::get('accounts/transfer/history/{id}', [AccountTransferController::class, 'getTransferHistory'])->name('accounts-transfer-history-show');
    Route::get('accounts/transfer/{id}/approval-document', [AccountTransferController::class, 'downloadApprovalDocument'])->name('accounts-transfer-approval-document');
    Route::post('accounts/transfer/{id}/review', [AccountTransferController::class, 'review'])->name('accounts-transfer-review');
    Route::post('accounts/transfer/{id}/verify', [AccountTransferController::class, 'verify'])->name('accounts-transfer-verify');
    Route::post('accounts/transfer/{id}/approve', [AccountTransferController::class, 'approve'])->name('accounts-transfer-approve');
    Route::post('accounts/transfer/{id}/reject', [AccountTransferController::class, 'reject'])->name('accounts-transfer-reject');
    Route::post('accounts/transfer/{id}/return', [AccountTransferController::class, 'returnTransfer'])->name('accounts-transfer-return');
    Route::post('accounts/transfer/{id}/resubmit', [AccountTransferController::class, 'resubmit'])->name('accounts-transfer-resubmit');

    // POS Terminal management routes (authenticated)
    Route::post('pos-terminals', [PosTerminalController::class, 'registerTerminal'])->name('register-pos-terminal');
    Route::get('pos-terminals', [PosTerminalController::class, 'listTerminals'])->name('list-pos-terminals');
    Route::get('pos-terminals/{id}', [PosTerminalController::class, 'getTerminal'])->name('get-pos-terminal');
    Route::put('pos-terminals/{id}', [PosTerminalController::class, 'updateTerminal'])->name('update-pos-terminal');
    Route::put('pos-terminals/{id}/status', [PosTerminalController::class, 'updateTerminalStatus'])->name('update-terminal-status');

    // Reconciliation routes
    Route::post('reconciliation/run', [ReconciliationController::class, 'runReconciliation'])->name('reconciliation-run');
});

// Registration management routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('registration-requests', [RegistrationRequestController::class, 'index'])->name('registration-requests');
    Route::get('registration-requests/statistics', [RegistrationRequestController::class, 'statistics'])->name('registration-statistics');
    Route::get('registration-requests/{id}', [RegistrationRequestController::class, 'show'])->name('get-registration-request');
    Route::post('registration-requests', [RegistrationRequestController::class, 'store'])->name('create-registration-request');
    Route::put('registration-requests/{id}', [RegistrationRequestController::class, 'update'])->name('update-registration-request');
    Route::put('registration-requests/{id}/review', [RegistrationRequestController::class, 'review'])->name('review-registration-request');
    Route::get('registration-requests/{id}/download-card', [RegistrationRequestController::class, 'downloadRegistrationCard'])->name('download-registration-card');
    Route::get('registration-requests/check-pending/{plateNumber}', [RegistrationRequestController::class, 'checkPendingRequests'])->name('check-pending-requests');
    Route::delete('registration-requests/{id}', [RegistrationRequestController::class, 'destroy'])->name('delete-registration-request');

    // Body types route
    Route::get('body-types', function () {
        $bodyTypes = DB::table('body_type')->select('id', 'name', 'description')->get();
        return response()->json([
            'success' => true,
            'message' => 'Body types retrieved successfully',
            'data' => $bodyTypes
        ]);
    })->name('body-types');
});

#Reports
Route::post('toll-collection', [ReportController::class, 'TollCollection'])->name('toll-collection');
Route::post('incident-collection', [ReportController::class, 'IncidentCollection'])->name('incident-collection');
Route::post('overload-collection', [ReportController::class, 'OverloadCollection'])->name('overload-collection');
Route::post('event-collection', [ReportController::class, 'EventCollection'])->name('event-collection');
Route::post('overall-monthly-collection', [ReportController::class, 'OverallMonthlyCollection'])->name('overall-monthly-collection');
Route::post('overall-year-collection', [ReportController::class, 'OverallYearCollection'])->name('overall-year-collection');
Route::post('overall-project-report', [ReportController::class, 'OverallProjectReport'])->name('overall-project-report');
Route::post('shift-collection-report', [ReportController::class, 'shiftCollectionReport'])->name('shift-collection-report');
Route::post('toll-passes', [ReportController::class, 'getTollPasses'])->name('toll-passes');

#mobile app authentication
Route::post('bridge-app-authentication', [AuthController::class, 'mobileAuthentication'])->name('bridge-app-authentication');

#Administrations
Route::get('bridge-users', [AdministrationController::class, 'bridgeUsers'])->name('bridge-users');
Route::get('users', [AdministrationController::class, 'Users'])->name('users');
Route::get('users/{id}', [AdministrationController::class, 'getUser'])->name('get-user');
Route::post('add-user', [AdministrationController::class, 'AddNewUser'])->name('add-user');
Route::put('users/{id}', [AdministrationController::class, 'updateUser'])->name('update-user');
Route::put('users/{id}/status', [AdministrationController::class, 'updateUserStatus'])->name('update-user-status');
Route::put('users/{id}/roles', [AdministrationController::class, 'updateUserRoles'])->name('update-user-roles');
Route::post('users/{id}/roles', [AdministrationController::class, 'assignRoleToUser'])->name('assign-user-role');
Route::post('users/roles/grant', [AdministrationController::class, 'grantRoleToUser'])->name('grant-user-role');
Route::post('users/roles/revoke', [AdministrationController::class, 'revokeUserRole'])->name('revoke-user-role');
Route::get('roles', [AdministrationController::class, 'getAvailableRoles'])->name('get-roles');

// Role management routes
Route::get('roles/list', [AdministrationController::class, 'getRoles'])->name('list-roles');
Route::get('roles/{id}/modules', [AdministrationController::class, 'getAuthRoleModules'])->name('get-auth-role-modules');
Route::post('roles/{id}/modules', [AdministrationController::class, 'assignModuleToAuthRole'])->name('assign-auth-role-module');
Route::get('roles/{id}', [AdministrationController::class, 'getRole'])->name('get-role');
Route::post('roles', [AdministrationController::class, 'createRole'])->name('create-role');
Route::put('roles/{id}', [AdministrationController::class, 'updateRole'])->name('update-role');
Route::put('roles/{id}/status', [AdministrationController::class, 'toggleRoleStatus'])->name('toggle-role-status');

// Permissions routes
Route::get('permissions', [App\Http\Controllers\Administration\AuthPermissionController::class, 'index'])->name('permissions');
Route::get('permissions/{id}', [App\Http\Controllers\Administration\AuthPermissionController::class, 'show'])->name('get-permission');
Route::post('permissions', [App\Http\Controllers\Administration\AuthPermissionController::class, 'store'])->name('create-permission');
Route::put('permissions/{id}', [App\Http\Controllers\Administration\AuthPermissionController::class, 'update'])->name('update-permission');
Route::put('permissions/{id}/status', [App\Http\Controllers\Administration\AuthPermissionController::class, 'toggleStatus'])->name('toggle-permission-status');
Route::get('permissions/available', [App\Http\Controllers\Administration\AuthPermissionController::class, 'getAvailablePermissions'])->name('available-permissions');
Route::get('roles/{roleId}/permissions', [App\Http\Controllers\Administration\AuthPermissionController::class, 'getRolePermissions'])->name('role-permissions');
Route::post('roles/{roleId}/permissions', [App\Http\Controllers\Administration\AuthPermissionController::class, 'assignPermissions'])->name('assign-role-permissions');

// Actions routes
Route::get('actions', [App\Http\Controllers\Administration\AuthActionController::class, 'index'])->name('actions');
Route::get('actions/{id}', [App\Http\Controllers\Administration\AuthActionController::class, 'show'])->name('get-action');
Route::post('actions', [App\Http\Controllers\Administration\AuthActionController::class, 'store'])->name('create-action');
Route::put('actions/{id}', [App\Http\Controllers\Administration\AuthActionController::class, 'update'])->name('update-action');
Route::put('actions/{id}/status', [App\Http\Controllers\Administration\AuthActionController::class, 'toggleStatus'])->name('toggle-action-status');
Route::get('actions/available', [App\Http\Controllers\Administration\AuthActionController::class, 'getAvailableActions'])->name('available-actions');
Route::get('roles/{roleId}/actions', [App\Http\Controllers\Administration\AuthActionController::class, 'getRoleActions'])->name('role-actions');
Route::post('roles/{roleId}/actions', [App\Http\Controllers\Administration\AuthActionController::class, 'assignRoleActions'])->name('assign-role-actions');
Route::get('user/menu-items', [App\Http\Controllers\Administration\AuthActionController::class, 'getUserMenuItems'])->name('user-menu-items');

// Lane management routes
Route::get('lanes', [App\Http\Controllers\Administration\LaneController::class, 'index'])->name('lanes');
Route::get('lanes/{id}', [App\Http\Controllers\Administration\LaneController::class, 'show'])->name('get-lane');
Route::post('lanes', [App\Http\Controllers\Administration\LaneController::class, 'store'])->name('create-lane');
Route::put('lanes/{id}', [App\Http\Controllers\Administration\LaneController::class, 'update'])->name('update-lane');
Route::put('lanes/{id}/status', [App\Http\Controllers\Administration\LaneController::class, 'toggleStatus'])->name('toggle-lane-status');
Route::delete('lanes/{id}', [App\Http\Controllers\Administration\LaneController::class, 'destroy'])->name('delete-lane');
Route::get('lanes/payment-methods', [App\Http\Controllers\Administration\LaneController::class, 'getPaymentMethods'])->name('lanes-payment-methods');
Route::get('lanes/active', [App\Http\Controllers\Administration\LaneController::class, 'getActiveLanes'])->name('active-lanes');
Route::post('lanes/manual-open-gate', [App\Http\Controllers\Administration\LaneController::class, 'manualOpenGate'])->name('manual-open-gate');
//caily
#Report Management
Route::get('report-categories', [ReportManagementController::class, 'reportCategories'])->name('report-categories');
Route::post('report-stats', [ReportManagementController::class, 'reportStats'])->name('report-stats');
Route::post('report-list', [ReportManagementController::class, 'getReportsByCategory'])->name('report-list');
Route::post('account-passage', [ReportController::class, 'accountPassage'])->name('account-passage');

# Booth Management
Route::get('test', [BoothController::class, 'testURL'])->name('test');

Route::post('open-counter', [BoothController::class, 'openCounter'])->name('open-counter');

Route::post('operator-collections', [DataController::class, 'getOperatorCollections'])->name('operator-collections');

Route::get('list-lanes', [LaneController::class, 'listLanes'])->name('list-lanes');

Route::get('list-body-types', [BodyTypesController::class, 'listBodyTypes'])->name('list-body-types');
Route::get('list-exempted-body-types', [BodyTypesController::class, 'listExemptedBodyTypes'])->name('list-exempted-body-types');

Route::post('get-vehicle-data', [VehicleController::class, 'getVehicleData'])->name('get-vehicle-data');

Route::post('pay-and-go', [PayAndGoController::class, 'payAndGo'])->name('pay-and-go');

Route::post('cancelled-detection', [BoothController::class, 'cancelledDetection'])->name('cancelled-detection');

Route::post('detection', [BoothController::class, 'detection'])->name('detection');

Route::post('open-gate', [BoothController::class, 'openGate'])->name('open-gate');

Route::post('close-counter', [BoothController::class, 'closeCounter'])->name('close-counter');

Route::post('reprint-receipt', [BoothController::class, 'rePrintReceipt'])->name('reprint-receipt');

Route::post('open-toll-gate', [LaneController::class, 'openTollGate'])->name('open-toll-gate');

Route::post('card-passage', [BoothController::class, 'cardPassage'])->name('card-passage');

// NSSF Receipt Routes
Route::get('/nssf-receipt', [NssfReceiptController::class, 'showReceipt']);
Route::post('/nssf-receipt/render', [NssfReceiptController::class, 'renderReceipt']);
Route::get('/nssf-receipt/data', [NssfReceiptController::class, 'getDummyReceiptData']);
Route::get('/nssf-receipt/pdf', [NssfReceiptController::class, 'generatePdf']);
Route::post('/nssf-receipt/custom-pdf', [NssfReceiptController::class, 'generateCustomPdf']);

// Passages PDF Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/passages/bundle-pdf', [PassagesController::class, 'fetchBundlePassagesPdf'])->name('bundle-passages-pdf');
    Route::get('/passages/normal-pdf', [PassagesController::class, 'fetchNormalPassagesPdf'])->name('normal-passages-pdf');
    Route::get('/passages/all-pdf', [PassagesController::class, 'fetchAllPassagesPdf'])->name('all-passages-pdf');
});

// RFID Gate Control Routes (Public - for POS terminals)
Route::post('/rfid/process-access', [RFIDGateController::class, 'processRFIDAccess'])->name('rfid-process-access');
Route::get('/rfid/vehicle-info', [RFIDGateController::class, 'getVehicleByRFID'])->name('rfid-vehicle-info');

// TRA Receipt Routes
Route::post('/tra-receipt', [App\Http\Controllers\Receipts\TraReceiptController::class, 'getTraReceipt'])->name('get-tra-receipt');

// Z-Report Routes (Two-Phase System)
// Phase 1: Prepare/Calculate Z-report data (saves to zreport table)
Route::post('/z-report/prepare', [ReceiptController::class, 'prepareZReport'])->name('z-report-prepare');
// Phase 1 (Batch): Prepare multiple znumbers sequentially
Route::post('/z-report/batch-prepare', [ReceiptController::class, 'batchPrepare'])->name('z-report-batch-prepare');
// Phase 2: Post Z-report to TRA (reads from zreport table)
Route::post('/z-report/batch', [ReceiptController::class, 'batchReport'])->name('z-report-batch');
// Phase 2 (Batch): Post multiple znumbers sequentially
Route::post('/z-report/batch-post', [ReceiptController::class, 'batchPost'])->name('z-report-batch-post');
// Phase 2 (Batch): Post all prepared Z-reports one by one
Route::post('/z-report/batch-post-all', [ReceiptController::class, 'batchPostAll'])->name('z-report-batch-post-all');
// Batch Prepare and Post: Prepare multiple znumbers sequentially, then post all at once
Route::post('/z-report/batch-prepare-and-post', [ReceiptController::class, 'batchPrepareAndPost'])->name('z-report-batch-prepare-and-post');

// Z-Report Routes  jjjj
Route::post('/z-report/repost-missing', [App\Http\Controllers\Receipts\ZReportController::class, 'repostMissingZreports'])->name('z-report-repost-missing');
Route::post('/z-report/process-all-missing', [App\Http\Controllers\Receipts\ZReportController::class, 'processAllMissingZreports'])->name('z-report-process-all-missing');
Route::post('/z-report/generate-payload', [App\Http\Controllers\Receipts\ZReportController::class, 'generateZreportPayload'])->name('z-report-generate-payload');
Route::post('/z-report/start-automated-processing', [App\Http\Controllers\Receipts\ZReportController::class, 'startAutomatedProcessing'])->name('z-report-start-automated-processing');
Route::get('/z-report/processing-status/{jobId}', [App\Http\Controllers\Receipts\ZReportController::class, 'getProcessingStatus'])->name('z-report-processing-status');
Route::get('/z-report/processing-jobs', [App\Http\Controllers\Receipts\ZReportController::class, 'getAllProcessingJobs'])->name('z-report-processing-jobs');
Route::get('/z-report/missing-znumbers', [App\Http\Controllers\Receipts\ZReportController::class, 'listMissingZnumbers'])->name('z-report-missing-znumbers');
Route::post('/z-report/stop-processing/{jobId}', [App\Http\Controllers\Receipts\ZReportController::class, 'stopProcessing'])->name('z-report-stop-processing');
Route::post('/z-report/stop-all-processing', [App\Http\Controllers\Receipts\ZReportController::class, 'stopAllProcessing'])->name('z-report-stop-all-processing');

// Incident Fine Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/incident-fine/query', [IncidentFineController::class, 'query'])->name('incident-fine-query');
    Route::get('/incident-fine/query-details/{id}', [IncidentFineController::class, 'queryDetails'])->name('incident-fine-details');
    Route::post('/incident-fine/create', [IncidentFineController::class, 'create'])->name('incident-fine-create');
    Route::post('/incident-fine/edit-incident', [IncidentFineController::class, 'editIncident'])->name('incident-fine-edit');
    Route::post('/incident-fine/bill-cancellation', [IncidentFineController::class, 'billCancellation'])->name('incident-fine-cancel');
    Route::get('/incident-fine/view-reason/{id}', [IncidentFineController::class, 'viewReason'])->name('incident-fine-view-reason');
    Route::post('/incident-fine/repost-bill', [IncidentFineController::class, 'repostBill'])->name('incident-fine-repost');
    Route::get('/incident-fine/print-receipt/{id}', [IncidentFineController::class, 'printReceipt'])->name('incident-fine-print-receipt');
});

// Event Payment Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/event-payment/query', [EventPaymentController::class, 'query'])->name('event-payment-query');
    Route::get('/event-payment/query-details/{id}', [EventPaymentController::class, 'queryDetails'])->name('event-payment-details');
    Route::get('/event-payment/view-reason/{id}', [EventPaymentController::class, 'viewReason'])->name('event-payment-view-reason');
    Route::get('/event-payment/print-receipt/{id}', [EventPaymentController::class, 'printReceipt'])->name('event-payment-print-receipt');
    Route::get('/fine-charge/print-receipt/{id}', [FineChargeController::class, 'printReceipt'])->name('fine-charge-print-receipt');
});


// BMS Routes
Route::middleware(['auth:sanctum'])->group(function () {

            // BMS USER APIs
    Route::get('/bms-loggedUser-role', [AdministrationController::class, 'fetchRoleForLoggedUser']);

            // BMS ROLES APIs
    Route::post('/register-bms-role', [RoleController::class, 'registerRole']);
    Route::get('/bms-roles', [RoleController::class, 'fetchRoles']);
    Route::get('/bms-roles/active', [RoleController::class, 'getActiveRoles']);
    Route::get('/bms-role/{role}', [RoleController::class, 'showRoles']);
    Route::put('/update-bms-role/{role}', [RoleController::class, 'updateRole']);
    Route::delete('/bms-role/{role}', [RoleController::class, 'destroyRole']);
    Route::put('/update-bms-role-status/{role}', [RoleController::class, 'toggleRoleStatus']);

            // BMS PERMISSION APIs
    Route::post('/register-bms-permission', [PermissionController::class, 'registerPermission']);
    Route::get('/bms-permissions', [PermissionController::class, 'fetchPermissions']);
    Route::get('/bms-permission/{permission}', [PermissionController::class, 'showPermissions']);
    Route::put('/update-bms-permission/{permission}', [PermissionController::class, 'updatePermission']);
    Route::delete('/bms-permission/{permission}', [PermissionController::class, 'destroyPermission']);
    Route::put('/update-bms-permission-status/{permission}', [PermissionController::class, 'togglePermissionStatus']);

                // BMS  PERMISSION ASSIGNMENT APIs
    Route::post('/assign-bms-permission-role', [RolePermissionController::class, 'assignPermissionRole']);
    Route::get('/bms-role-permissions', [RolePermissionController::class, 'fetchRolePermissions']);
    Route::get('/bms-role-permission/{rolePermission}', [RolePermissionController::class, 'showRolePermissions']);
    Route::put('/update-bms-role-permission/{rolePermission}', [RolePermissionController::class, 'updateRolePermission']);
    Route::delete('/bms-role-permission/{rolePermission}', [RolePermissionController::class, 'destroyRolePermission']);
    Route::put('/update-bms-role-permission-status/{rolePermission}', [RolePermissionController::class, 'toggleRolePermissionStatus']);

            // BMS EDUCATIONAL LEVEL APIs
    Route::get('/educational-levels', [EducationalLevelController::class, 'index'])->name('educational-levels');
    Route::get('/educational-levels/active', [EducationalLevelController::class, 'getActiveLevels'])->name('educational-levels-active');
    Route::post('/educational-levels', [EducationalLevelController::class, 'store'])->name('create-educational-level');
    Route::get('/educational-levels/{id}', [EducationalLevelController::class, 'show'])->name('get-educational-level');
    Route::put('/educational-levels/{id}', [EducationalLevelController::class, 'update'])->name('update-educational-level');
    Route::put('/educational-levels/{id}/status', [EducationalLevelController::class, 'toggleStatus'])->name('toggle-educational-level-status');
    Route::delete('/educational-levels/{id}', [EducationalLevelController::class, 'destroy'])->name('delete-educational-level');

            // SPECIAL TASKS MANAGEMENT APIs
    Route::get('/special-tasks', [SpecialTaskController::class, 'index'])->name('special-tasks-index');
    Route::post('/special-tasks', [SpecialTaskController::class, 'store'])->name('special-tasks-store');
    Route::post('/special-tasks/apply', [SpecialTaskController::class, 'apply'])->name('special-tasks-apply');
    Route::get('/special-tasks/employee', [SpecialTaskController::class, 'getEmployeeTasks'])->name('special-tasks-employee');
    Route::get('/special-tasks/employee/{pfNumber}', [SpecialTaskController::class, 'getEmployeeTasks'])->name('special-tasks-employee-by-pf');
    Route::get('/special-tasks/{id}', [SpecialTaskController::class, 'show'])->name('special-tasks-show');
    Route::put('/special-tasks/{id}', [SpecialTaskController::class, 'update'])->name('special-tasks-update');
    Route::post('/special-tasks/{id}/approve', [SpecialTaskController::class, 'approve'])->name('special-tasks-approve');
    Route::post('/special-tasks/{id}/reject', [SpecialTaskController::class, 'reject'])->name('special-tasks-reject');
    Route::delete('/special-tasks/{id}', [SpecialTaskController::class, 'destroy'])->name('special-tasks-destroy');

            // PUBLIC HOLIDAYS MANAGEMENT APIs
    Route::get('/public-holidays', [PublicHolidayController::class, 'index'])->name('public-holidays-index');
    Route::post('/public-holidays', [PublicHolidayController::class, 'store'])->name('public-holidays-store');
    Route::post('/public-holidays/check-date', [PublicHolidayController::class, 'checkDate'])->name('public-holidays-check-date');
    Route::post('/public-holidays/bulk-create', [PublicHolidayController::class, 'bulkCreate'])->name('public-holidays-bulk-create');
    Route::get('/public-holidays/year/{year}', [PublicHolidayController::class, 'getByYear'])->name('public-holidays-by-year');
    Route::get('/public-holidays/{id}', [PublicHolidayController::class, 'show'])->name('public-holidays-show');
    Route::put('/public-holidays/{id}', [PublicHolidayController::class, 'update'])->name('public-holidays-update');
    Route::post('/public-holidays/{id}/toggle-status', [PublicHolidayController::class, 'toggleStatus'])->name('public-holidays-toggle-status');
    Route::delete('/public-holidays/{id}', [PublicHolidayController::class, 'destroy'])->name('public-holidays-destroy');

});

// Attendance Log Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Main attendance index route
    Route::get('/attendance', [AttendanceLogController::class, 'index'])->name('attendance-index');

    // Get attendance record by date for logged-in user
    Route::post('/attendance/by-date', [AttendanceLogController::class, 'getByDate'])->name('attendance-by-date');

    // View attendance sessions
    Route::get('/attendance/sessions', [AttendanceLogController::class, 'getUserSessions'])->name('attendance-user-sessions');
    Route::get('/attendance/sessions/user/{userId}', [AttendanceLogController::class, 'getUserSessions'])->name('attendance-user-sessions-by-id');
    Route::get('/attendance/sessions/all', [AttendanceLogController::class, 'getAllSessions'])->name('attendance-all-sessions');
    Route::get('/attendance/sessions/{id}', [AttendanceLogController::class, 'show'])->name('attendance-show-session');

    // Attendance management (supervisor only)
    Route::get('/attendance/management', [AttendanceLogController::class, 'getAttendanceManagement'])->name('attendance-management');

    // ZKTeco Device Sync Routes (supervisor/admin only - consider adding middleware)
    Route::get('/attendance/device/fetch', [AttendanceLogController::class, 'fetchDeviceLogs'])->name('attendance-device-fetch');
    Route::post('/attendance/device/sync', [AttendanceLogController::class, 'syncDeviceLogs'])->name('attendance-device-sync');
    Route::post('/attendance/device/quick-sync', [AttendanceLogController::class, 'quickSyncRecentLogs'])->name('attendance-device-quick-sync');
    Route::get('/attendance/device/info', [AttendanceLogController::class, 'getDeviceInfo'])->name('attendance-device-info');

    // Bridge Office Routes
    Route::get('/bridge-offices', [\App\Http\Controllers\Bms\BridgeOfficeController::class, 'index'])->name('bridge-offices-index');
    Route::get('/bridge-offices/{id}', [\App\Http\Controllers\Bms\BridgeOfficeController::class, 'show'])->name('bridge-offices-show');

    // Bridge Employee Routes
    Route::get('/bridge-employees', [BridgeEmployeeController::class, 'index'])->name('bridge-employees-index');
    Route::get('/bridge-employees/active', [BridgeEmployeeController::class, 'getActiveEmployees'])->name('bridge-employees-active');
    Route::post('/bridge-employees/register', [BridgeEmployeeController::class, 'registerBridgeEmployee'])->name('bridge-employees-register');
    Route::get('/bridge-employees/{nationalId}', [BridgeEmployeeController::class, 'show'])->name('bridge-employees-show');
    Route::put('/bridge-employees/{nationalId}', [BridgeEmployeeController::class, 'update'])->name('bridge-employees-update');
    Route::delete('/bridge-employees/{nationalId}', [BridgeEmployeeController::class, 'destroy'])->name('bridge-employees-destroy');
    Route::post('/bridge-employees/search', [BridgeEmployeeController::class, 'search'])->name('bridge-employees-search');
    Route::post('/bridge-employees/{nationalId}/request-termination', [BridgeEmployeeController::class, 'submitTermination'])->name('bridge-employees-request-termination');
    Route::post('/bridge-employees/{nationalId}/submit-deletion', [BridgeEmployeeController::class, 'submitDeletion'])->name('bridge-employees-submit-deletion');

    // BMS Role Assignment Routes
    Route::post('/bridge-users', [AdministrationController::class, 'createBridgeUser'])->name('bridge-users-create');
    Route::post('/bridge-users/{nationalId}/assign-role', [AdministrationController::class, 'assignRole'])->name('bridge-users-assign-role');
    Route::get('/bridge-users/{nationalId}', [AdministrationController::class, 'showBridgeUser'])->name('bridge-users-show');
    Route::put('/bridge-users/{nationalId}', [AdministrationController::class, 'updateBridgeUser'])->name('bridge-users-update');
    Route::post('/bridge-users/{nationalId}/revoke-role', [AdministrationController::class, 'revokeRole'])->name('bridge-users-revoke-role');
    Route::put('/bridge-users/{nationalId}/toggle-status', [AdministrationController::class, 'toggleBridgeUserStatus'])->name('bridge-users-toggle-status');
    Route::get('/bridge-employees/{nationalId}/roles', [BridgeEmployeeController::class, 'getEmployeeRoles'])->name('bridge-employees-get-roles');
    Route::put('/bridge-employees/{nationalId}/roles', [BridgeEmployeeController::class, 'toggleRoleStatus'])->name('bridge-employees-toggle-role-status');

    // Employee Approval Routes (Maker-Checker)
    Route::get('/employee-approvals/pending', [BridgeEmployeeApprovalController::class, 'pendingApprovals'])->name('employee-approvals-pending');
    Route::post('/employee-approvals/{statusId}/approve-creation', [BridgeEmployeeApprovalController::class, 'approveCreation'])->name('employee-approvals-approve-creation');
    Route::post('/employee-approvals/{statusId}/approve-termination', [BridgeEmployeeApprovalController::class, 'approveTermination'])->name('employee-approvals-approve-termination');
    Route::post('/employee-approvals/{statusId}/approve-deletion', [BridgeEmployeeApprovalController::class, 'approveDeletion'])->name('employee-approvals-approve-deletion');
    Route::post('/employee-approvals/{statusId}/approve-update', [BridgeEmployeeApprovalController::class, 'approveUpdate'])->name('employee-approvals-approve-update');
    Route::post('/employee-approvals/{statusId}/reject', [BridgeEmployeeApprovalController::class, 'reject'])->name('employee-approvals-reject');

    // Referral Request Routes (Module-Based Approval)
    Route::get('/referral-requests', [ReferralRequestController::class, 'index'])->name('referral-requests-index');
    Route::get('/referral-requests/pending', [ReferralRequestController::class, 'pending'])->name('referral-requests-pending');
    Route::get('/referral-requests/statistics', [ReferralRequestController::class, 'statistics'])->name('referral-requests-statistics');
    Route::get('/referral-requests/{id}', [ReferralRequestController::class, 'show'])->name('referral-requests-show');
    Route::post('/referral-requests/{id}/approve', [ReferralRequestController::class, 'approve'])->name('referral-requests-approve');
    Route::post('/referral-requests/{id}/reject', [ReferralRequestController::class, 'reject'])->name('referral-requests-reject');

    // Bridge Module Routes
    Route::get('/bridge-modules', [BridgeModuleController::class, 'index'])->name('bridge-modules-index');
    Route::get('/bridge-modules/active', [BridgeModuleController::class, 'getActive'])->name('bridge-modules-active');
    Route::get('/bridge-modules/{id}', [BridgeModuleController::class, 'show'])->name('bridge-modules-show');
    Route::post('/register-bridge-modules', [BridgeModuleController::class, 'store'])->name('bridge-modules-store');
    Route::put('/bridge-modules/{id}', [BridgeModuleController::class, 'update'])->name('bridge-modules-update');
    Route::put('/bridge-modules/{id}/toggle-status', [BridgeModuleController::class, 'toggleStatus'])->name('bridge-modules-toggle-status');
    Route::delete('/bridge-modules/{id}', [BridgeModuleController::class, 'destroy'])->name('bridge-modules-destroy');

    // Bridge Module Role Routes
    Route::get('/bridge-module-roles', [BridgeModuleRoleController::class, 'index'])->name('bridge-module-roles-index');
    Route::get('/bridge-module-roles/active', [BridgeModuleRoleController::class, 'getActive'])->name('bridge-module-roles-active');
    // User-specific routes must come before parameterized routes to avoid route conflicts
    Route::get('/bridge-module-roles/user-modules', [BridgeModuleRoleController::class, 'getUserAccessibleModules'])->name('bridge-module-roles-user-modules');
    Route::get('/bridge-module-roles/{id}', [BridgeModuleRoleController::class, 'show'])->name('bridge-module-roles-show');
    Route::post('/bridge-module-roles', [BridgeModuleRoleController::class, 'store'])->name('bridge-module-roles-store');
    Route::put('/bridge-module-roles/{id}', [BridgeModuleRoleController::class, 'update'])->name('bridge-module-roles-update');
    Route::put('/bridge-module-roles/{id}/toggle-status', [BridgeModuleRoleController::class, 'toggleStatus'])->name('bridge-module-roles-toggle-status');
    Route::delete('/bridge-module-roles/{id}', [BridgeModuleRoleController::class, 'destroy'])->name('bridge-module-roles-destroy');

    // Module-Role Assignment Routes
    Route::post('/bridge-module-roles/revoke', [BridgeModuleRoleController::class, 'revokeModuleFromRole'])->name('bridge-module-roles-revoke');
    Route::get('/bridge-module-roles/role/{roleId}', [BridgeModuleRoleController::class, 'getModulesByRole'])->name('bridge-module-roles-by-role');
    Route::get('/bridge-module-roles/module/{moduleId}', [BridgeModuleRoleController::class, 'getRolesByModule'])->name('bridge-module-roles-by-module');
    Route::post('/bridge-module-roles/bulk-assign', [BridgeModuleRoleController::class, 'bulkAssignModules'])->name('bridge-module-roles-bulk-assign');

    // Bridge Module Menu Routes
    Route::get('/bridge-module-menus', [BridgeModuleMenuController::class, 'index'])->name('bridge-module-menus-index');
    Route::get('/bridge-module-menus/active', [BridgeModuleMenuController::class, 'getActive'])->name('bridge-module-menus-active');
    Route::get('/bridge-module-menus/module/{moduleId}', [BridgeModuleMenuController::class, 'getByModule'])->name('bridge-module-menus-by-module');
    Route::get('/bridge-module-menus/{id}', [BridgeModuleMenuController::class, 'show'])->name('bridge-module-menus-show');
    Route::post('/bridge-module-menus', [BridgeModuleMenuController::class, 'store'])->name('bridge-module-menus-store');
    Route::put('/bridge-module-menus/{id}', [BridgeModuleMenuController::class, 'update'])->name('bridge-module-menus-update');
    Route::put('/bridge-module-menus/{id}/toggle-status', [BridgeModuleMenuController::class, 'toggleStatus'])->name('bridge-module-menus-toggle-status');
    Route::delete('/bridge-module-menus/{id}', [BridgeModuleMenuController::class, 'destroy'])->name('bridge-module-menus-destroy');

    // Bridge Module Role Menu Routes
    Route::get('/bridge-module-role-menus', [BridgeModuleRoleMenuController::class, 'index'])->name('bridge-module-role-menus-index');
    Route::get('/bridge-module-role-menus/active', [BridgeModuleRoleMenuController::class, 'getActive'])->name('bridge-module-role-menus-active');
    Route::get('/bridge-module-role-menus/{id}', [BridgeModuleRoleMenuController::class, 'show'])->name('bridge-module-role-menus-show');
    Route::post('/bridge-module-role-menus', [BridgeModuleRoleMenuController::class, 'store'])->name('bridge-module-role-menus-store');
    Route::put('/bridge-module-role-menus/{id}', [BridgeModuleRoleMenuController::class, 'update'])->name('bridge-module-role-menus-update');
    Route::put('/bridge-module-role-menus/{id}/toggle-status', [BridgeModuleRoleMenuController::class, 'toggleStatus'])->name('bridge-module-role-menus-toggle-status');
    Route::delete('/bridge-module-role-menus/{id}', [BridgeModuleRoleMenuController::class, 'destroy'])->name('bridge-module-role-menus-destroy');

    // Role-Menu Assignment Routes
    Route::post('/bridge-module-role-menus/assign', [BridgeModuleRoleMenuController::class, 'assignMenuToRole'])->name('bridge-module-role-menus-assign');
    Route::post('/bridge-module-role-menus/revoke', [BridgeModuleRoleMenuController::class, 'revokeMenuFromRole'])->name('bridge-module-role-menus-revoke');
    Route::get('/bridge-module-role-menus/role/{roleId}', [BridgeModuleRoleMenuController::class, 'getMenusByRole'])->name('bridge-module-role-menus-by-role');
    Route::get('/bridge-module-role-menus/menu/{menuId}', [BridgeModuleRoleMenuController::class, 'getRolesByMenu'])->name('bridge-module-role-menus-by-menu');
    Route::post('/bridge-module-role-menus/user-menus', [BridgeModuleRoleMenuController::class, 'getUserMenus'])->name('bridge-module-role-menus-user-menus');
    Route::post('/bridge-module-role-menus/bulk-assign', [BridgeModuleRoleMenuController::class, 'bulkAssignMenus'])->name('bridge-module-role-menus-bulk-assign');

    // Region (Domicile) Management Routes
    Route::get('/regions', [RegionController::class, 'index'])->name('regions-index');
    Route::get('/regions/active', [RegionController::class, 'getActive'])->name('regions-active');
    Route::get('/regions/{id}/districts', [DistrictController::class, 'getByDomicile'])->name('regions-districts');
    Route::get('/regions/{id}', [RegionController::class, 'show'])->name('regions-show');
    Route::post('/regions', [RegionController::class, 'store'])->name('regions-store');
    Route::put('/regions/{id}', [RegionController::class, 'update'])->name('regions-update');
    Route::put('/regions/{id}/status', [RegionController::class, 'toggleStatus'])->name('regions-toggle-status');
    Route::delete('/regions/{id}', [RegionController::class, 'destroy'])->name('regions-destroy');

    // District Management Routes
    Route::get('/districts', [DistrictController::class, 'index'])->name('districts-index');
    Route::get('/districts/active', [DistrictController::class, 'getActive'])->name('districts-active');
    Route::get('/districts/{id}', [DistrictController::class, 'show'])->name('districts-show');
    Route::post('/districts', [DistrictController::class, 'store'])->name('districts-store');
    Route::put('/districts/{id}', [DistrictController::class, 'update'])->name('districts-update');
    Route::put('/districts/{id}/status', [DistrictController::class, 'toggleStatus'])->name('districts-toggle-status');
    Route::delete('/districts/{id}', [DistrictController::class, 'destroy'])->name('districts-destroy');

    // Bank Management Routes
    Route::get('/banks', [BankController::class, 'index'])->name('banks-index');
    Route::get('/banks/active', [BankController::class, 'getActive'])->name('banks-active');
    Route::get('/banks/{bankId}', [BankController::class, 'show'])->name('banks-show');
    Route::post('/banks', [BankController::class, 'store'])->name('banks-store');
    Route::put('/banks/{bankId}', [BankController::class, 'update'])->name('banks-update');
    Route::put('/banks/{bankId}/status', [BankController::class, 'toggleStatus'])->name('banks-toggle-status');
    Route::delete('/banks/{bankId}', [BankController::class, 'destroy'])->name('banks-destroy');

    // Scheme Management Routes
    Route::get('/schemes', [SchemeController::class, 'index'])->name('schemes-index');
    Route::get('/schemes/active', [SchemeController::class, 'getActive'])->name('schemes-active');
    Route::get('/schemes/{id}', [SchemeController::class, 'show'])->name('schemes-show');
    Route::post('/schemes', [SchemeController::class, 'store'])->name('schemes-store');
    Route::put('/schemes/{id}', [SchemeController::class, 'update'])->name('schemes-update');
    Route::delete('/schemes/{id}', [SchemeController::class, 'destroy'])->name('schemes-destroy');

    // Bridge Employment Type Management Routes
    Route::get('/bridge-employment-types', [BridgeEmploymentTypeController::class, 'index'])->name('bridge-employment-types-index');
    Route::get('/bridge-employment-types/all', [BridgeEmploymentTypeController::class, 'getAll'])->name('bridge-employment-types-all');     // for listing all employment types used for dropdown
    Route::get('/bridge-employment-types/active', [BridgeEmploymentTypeController::class, 'getActiveBridgeEmploymentTypes'])->name('bridge-employment-types-active');     // for listing active employment types
    Route::get('/bridge-employment-types/{id}', [BridgeEmploymentTypeController::class, 'show'])->name('bridge-employment-types-show');
    Route::post('/bridge-employment-types', [BridgeEmploymentTypeController::class, 'store'])->name('bridge-employment-types-store');
    Route::put('/bridge-employment-types/{id}', [BridgeEmploymentTypeController::class, 'update'])->name('bridge-employment-types-update');
    Route::delete('/bridge-employment-types/{id}', [BridgeEmploymentTypeController::class, 'destroy'])->name('bridge-employment-types-destroy');

    // Department Management Routes
    Route::get('/departments', [DepartmentController::class, 'listDepartments'])->name('departments-index');
    Route::get('/departments/active', [DepartmentController::class, 'getActiveDepartments'])->name('departments-active');
    Route::get('/departments/{id}', [DepartmentController::class, 'getDepartmentById'])->name('departments-show');
    Route::post('/departments', [DepartmentController::class, 'createDepartment'])->name('departments-store');
    Route::put('/departments/{id}', [DepartmentController::class, 'updateDepartment'])->name('departments-update');
    Route::put('/departments/{id}/status', [DepartmentController::class, 'toggleStatus'])->name('departments-toggle-status');
    Route::delete('/departments/{id}', [DepartmentController::class, 'deleteDepartment'])->name('departments-destroy');

    // Bridge Shifts Management Routes
    Route::get('/bridge-shifts', [BridgeShiftController::class, 'index'])->name('bridge-shifts-index');
    Route::get('/bridge-shifts/all', [BridgeShiftController::class, 'getAllShifts'])->name('bridge-shifts-all');
    Route::get('/bridge-shifts/active', [BridgeShiftController::class, 'getActiveShifts'])->name('bridge-shifts-active');
    Route::get('/bridge-shifts/{id}', [BridgeShiftController::class, 'showShifts'])->name('bridge-shifts-show');
    Route::post('/bridge-shifts', [BridgeShiftController::class, 'RegisterShifts'])->name('bridge-shifts-store');
    Route::put('/bridge-shifts/{id}', [BridgeShiftController::class, 'updateShifts'])->name('bridge-shifts-update');
    Route::put('/bridge-shifts/{id}/status', [BridgeShiftController::class, 'toggleStatus'])->name('bridge-shifts-toggle-status');
    Route::delete('/bridge-shifts/{id}', [BridgeShiftController::class, 'destroyShift'])->name('bridge-shifts-destroy');

    // Bridge Shift Department Mapping Routes
    Route::get('/bridge-shift-departments', [BridgeShiftDepartmentController::class, 'index'])->name('bridge-shift-departments-index');
    Route::get('/bridge-shift-departments/active', [BridgeShiftDepartmentController::class, 'getActiveDepartmentShiftAssignment'])->name('bridge-shift-departments-active');
    Route::get('/bridge-shift-departments/{id}', [BridgeShiftDepartmentController::class, 'show'])->name('bridge-shift-departments-show');
    Route::post('/bridge-shift-departments', [BridgeShiftDepartmentController::class, 'createAssignment'])->name('bridge-shift-departments-store');
    Route::put('/bridge-shift-departments/{id}', [BridgeShiftDepartmentController::class, 'updateDepartmentShiftAssignment'])->name('bridge-shift-departments-update');
    Route::put('/bridge-shift-departments/{id}/status', [BridgeShiftDepartmentController::class, 'toggleDepartmentShiftAssignmentStatus'])->name('bridge-shift-departments-toggle-status');
    Route::delete('/bridge-shift-departments/{id}', [BridgeShiftDepartmentController::class, 'destroy'])->name('bridge-shift-departments-destroy');

});


// Overload Fine Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/overload-fine/query', [OverloadFineController::class, 'query'])->name('overload-fine-query');
    Route::get('/overload-fine/query-details/{id}', [OverloadFineController::class, 'queryDetails'])->name('overload-fine-details');
    Route::post('/overload-fine/create', [OverloadFineController::class, 'create'])->name('overload-fine-create');
    Route::post('/overload-fine/edit-overload', [OverloadFineController::class, 'editOverload'])->name('overload-fine-edit');
    Route::post('/overload-fine/bill-cancellation', [OverloadFineController::class, 'billCancellation'])->name('overload-fine-cancel');
    Route::get('/overload-fine/view-reason/{id}', [OverloadFineController::class, 'viewReason'])->name('overload-fine-view-reason');
    Route::post('/overload-fine/repost-bill', [OverloadFineController::class, 'repostBill'])->name('overload-fine-repost');
    Route::get('/overload-fine/print-receipt/{id}', [OverloadFineController::class, 'printReceipt'])->name('overload-fine-print-receipt');
});
