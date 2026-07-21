<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SupplierApiController;
use App\Http\Controllers\Api\PurchaseOrderApiController;
use App\Http\Controllers\Api\BanquetEventApiController;
use App\Http\Controllers\Api\RestaurantApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\SystemParameterApiController;
use App\Http\Controllers\Api\AccountingApiController;
use App\Http\Controllers\Api\PettyCashVoucherApiController;
use App\Http\Controllers\Api\RevolvingFundApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\DataManagement\BankApiController;
use App\Http\Controllers\Api\Transaction\AflApiController;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Http\Controllers\Api\Business\EmployeeApiController;
use App\Http\Controllers\Api\Transaction\EmployeeCashAdvanceApiController;
use App\Http\Controllers\Api\Event\EventLiquidationApiController;
use App\Http\Controllers\Api\Inventory\ReceivingApiController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//purchase order api
Route::middleware('auth:sanctum')->get('/suppliers', [SupplierApiController::class, 'index'])->name('api.supplier.index');
Route::middleware('auth:sanctum')->get('/active-purchase-type', [PurchaseOrderApiController::class, 'activePurchaseType'])->name('api.active.purchase-type');
Route::middleware('auth:sanctum')->get('/active-purchase-term', [PurchaseOrderApiController::class, 'activePurchaseTerm'])->name('api.active.purchase-term');
Route::middleware('auth:sanctum')->get('/active-approvers', [PurchaseOrderApiController::class, 'activeApprovers'])->name('api.active.approvers');
Route::middleware('auth:sanctum')->get('/active-reviewers', [PurchaseOrderApiController::class, 'activeReviewers'])->name('api.active.reviewers');
Route::middleware('auth:sanctum')->get('/active-event', [BanquetEventApiController::class, 'activeEvent'])->name('api.active.event');
Route::middleware('auth:sanctum')->get('/active-production-order', [RestaurantApiController::class, 'activeProductionOrder'])->name('api.active.production-order');
Route::middleware('auth:sanctum')->get('/active-purchase-order', [PurchaseOrderApiController::class, 'activePurchaseOrder'])->name('api.active.purchase-order');
Route::middleware('auth:sanctum')->get('/received-purchase-order-filter1', [PurchaseOrderApiController::class, 'receivedPurchaseOrderFilter1'])->name('api.received.purchase-order.filter1');

//RECEIVING
Route::middleware('auth:sanctum')->get('/to-receive-purchase-order', [PurchaseOrderApiController::class, 'toReceivePurchaseOrder'])->name('api.get.to-receive-purchase-order');

//withdrawal
Route::middleware('auth:sanctum')->get('/active-receiving-number', [ReceivingApiController::class, 'activeReceivingNumber'])->name('api.active.receiving-number');
Route::middleware('auth:sanctum')->get('/active-withdrawal-type', [ReceivingApiController::class, 'withdrawalType'])->name('api.active.withdrawal-type');
Route::middleware('auth:sanctum')->get('/active-department', [ReceivingApiController::class, 'activeDepartment'])->name('api.active.department');
Route::middleware('auth:sanctum')->get('/active-approvers', [ReceivingApiController::class, 'activeApprovers'])->name('api.active.withdrawal-approvers');
Route::middleware('auth:sanctum')->get('/active-reviewers', [ReceivingApiController::class, 'activeReviewers'])->name('api.active.withdrawal-reviewers');

// fixed asset api
Route::middleware('auth:sanctum')->get('/active-asset-registration-type', [InventoryApiController::class, 'activeAssetRegistrationType'])->name('api.active.asset-registration-type');
Route::middleware('auth:sanctum')->get('/active-asset-registration-reviewers', [InventoryApiController::class, 'activeAssetRegistrationReviewers'])->name('api.active.asset-registration-reviewers');
Route::middleware('auth:sanctum')->get('/active-asset-registration-approvers', [InventoryApiController::class, 'activeAssetRegistrationApprovers'])->name('api.active.asset-registration-approvers');
Route::middleware('auth:sanctum')->get('/get-received-items', [PurchaseOrderApiController::class, 'getReceivedItems'])->name('api.get.received-items');
Route::middleware('auth:sanctum')->get('/get-receiving-references', [PurchaseOrderApiController::class, 'getReceivingReferences'])->name('api.get.receiving-references');

// REIMBURSEMENT
Route::middleware('auth:sanctum')->get('/get-for-reimburse-pcv', [PettyCashVoucherApiController::class, 'getForDisbursementPcv'])->name('api.get.reimburse-pcv');
Route::middleware('auth:sanctum')->get('/get-for-reimburse-approvers', [PettyCashVoucherApiController::class, 'getReimbursementApprovers'])->name('api.get.reimburse-approvers');

// CASH RETURN
Route::middleware('auth:sanctum')->get('/get-for-cash-return-pcv', [PettyCashVoucherApiController::class, 'getForCashReturnPcv'])->name('api.get.cash-return-pcv');
//CRS for cash advance
Route::middleware('auth:sanctum')->get('/get-active-employee-advances', [EmployeeCashAdvanceApiController::class, 'getBarnchActiveCashAdvances'])->name('api.get.active-advances-for-employees');
//CRS for event liquidation
Route::middleware('auth:sanctum')->get('/get-active-event-liquidation', [EventLiquidationApiController::class, 'getCashReturnEventLiquidation'])->name('api.get.cash-return.event-liquidation');

// petty cash voucher
Route::middleware('auth:sanctum')->get('/get-pcv-type', [SystemParameterApiController::class, 'pcvType'])->name('api.get.pcv-type');
Route::middleware('auth:sanctum')->get('/get-active-petty-cash-voucher', [PettyCashVoucherApiController::class, 'getActivePettyCashVouchers'])->name('api.get.active.petty-cash-voucher');
Route::middleware('auth:sanctum')->get('/get-event-purchase-order', [PurchaseOrderApiController::class, 'eventPurchaseOrder'])->name('api.get.event-purchase-order');
Route::middleware('auth:sanctum')->get('/get-non-event-purchase-order', [PurchaseOrderApiController::class, 'nonEventPurchaseOrder'])->name('api.get.non-event-purchase-order');
Route::middleware('auth:sanctum')->get('/get-pcv-active_afl', [AflApiController::class, 'getActiveAdvancesForLiquidation'])->name('api.pcv.get.active-afl');
Route::middleware('auth:sanctum')->get('/get-for-disbusement-cash-advances', [EmployeeApiController::class, 'getActiveCashAdvancesForDisburse'])->name('api.get.for-disbusement-cash-advances');



//revolving fund
Route::middleware('auth:sanctum')->get('/get-revolving-fund-type', [RevolvingFundApiController::class, 'activeAcknowledgementForRevolvingFund'])->name('api.get.active.acknowledgement-for-revolving-fund');

// Accounting
Route::middleware('auth:sanctum')->get('/get-selected-transaction_template', [AccountingApiController::class, 'selectedTransactionTemplate'])->name('api.get.selected-transaction_template');
Route::middleware('auth:sanctum')->get('/get-active-account-type', [AccountingApiController::class, 'activeAccountType'])->name('api.get.active-account-type');
Route::middleware('auth:sanctum')->get('/get-pcv-payee-employee', [AccountingApiController::class, 'pcvPayeeEmployee'])->name('api.get.pcv-payee-employee');
Route::middleware('auth:sanctum')->get('/get-pcv-payee-customer', [AccountingApiController::class, 'pcvPayeeCustomer'])->name('api.get.pcv-payee-customer');

//CUSTOMERS API
Route::middleware('auth:sanctum')->get('/get-branch-customers', [CustomerApiController::class, 'getBranchCustomers'])->name('api.get.branch-customers');

// BANK
Route::middleware('auth:sanctum')->get('/get-branch-banks', [BankApiController::class, 'getBranchBanks'])->name('api.get.branch-banks');

// ACKNOWLEDGEMENT
Route::middleware('auth:sanctum')->get('/get-active-fundedEvent', [BanquetEventApiController::class, 'getFundedEvent'])->name('api.get.active.funded-event');

// ADVANCES FOR LIQUIDATION
Route::middleware('auth:sanctum')->get('/get-afl-approvers', [AflApiController::class, 'getBranchApprovers'])->name('api.get.afl-approvers');
Route::middleware('auth:sanctum')->get('/get-disbursers', [AflApiController::class, 'getBranchDisbursers'])->name('api.get.disbursers');
Route::middleware('auth:sanctum')->get('/get-active-afl', [AflApiController::class, 'getActiveAdvancesForLiquidation'])->name('api.get.active-afl');

// ADVANCES FOR EMPLOYEES
Route::middleware('auth:sanctum')->get('/get-active-employees', [EmployeeApiController::class, 'getActiveBranchEmployees'])->name('api.get.active-employees-advances');
Route::middleware('auth:sanctum')->get('/get-cash-advance-approvers', [EmployeeCashAdvanceApiController::class, 'getBranchCashAdvanceApprovers'])->name('api.get.cash-advance-approvers');

//EVENT LIQUIDATION
Route::middleware('auth:sanctum')->get('/event-liquidation/active-event', [BanquetEventApiController::class, 'activeEvent'])->name('api.event-liquidation.active.event');
Route::middleware('auth:sanctum')->get('/event-liquidation/active-reviewers', [EventLiquidationApiController::class, 'activeReviewers'])->name('api.liquidate-event.active.reviewers');
Route::middleware('auth:sanctum')->get('/event-liquidation/active-approvers', [EventLiquidationApiController::class, 'activeApprovers'])->name('api.liquidate-event.active.approvers');
