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


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//purchase order api
Route::middleware('auth:sanctum')->get('/suppliers', [SupplierApiController::class, 'index'])->name('api.supplier.index');
Route::middleware('auth:sanctum')->get('/active-purchase-type', [PurchaseOrderApiController::class,'activePurchaseType'])->name('api.active.purchase-type');
Route::middleware('auth:sanctum')->get('/active-purchase-term', [PurchaseOrderApiController::class,'activePurchaseTerm'])->name('api.active.purchase-term');
Route::middleware('auth:sanctum')->get('/active-approvers', [PurchaseOrderApiController::class,'activeApprovers'])->name('api.active.approvers');
Route::middleware('auth:sanctum')->get('/active-reviewers', [PurchaseOrderApiController::class,'activeReviewers'])->name('api.active.reviewers');
Route::middleware('auth:sanctum')->get('/active-event', [BanquetEventApiController::class,'activeEvent'])->name('api.active.event');
Route::middleware('auth:sanctum')->get('/active-production-order', [RestaurantApiController::class,'activeProductionOrder'])->name('api.active.production-order');
Route::middleware('auth:sanctum')->get('/active-purchase-order', [PurchaseOrderApiController::class,'activePurchaseOrder'])->name('api.active.purchase-order');
Route::middleware('auth:sanctum')->get('/received-purchase-order-filter1', [PurchaseOrderApiController::class,'receivedPurchaseOrderFilter1'])->name('api.received.purchase-order.filter1');


// fixed asset api
Route::middleware('auth:sanctum')->get('/active-asset-registration-type', [InventoryApiController::class,'activeAssetRegistrationType'])->name('api.active.asset-registration-type');
Route::middleware('auth:sanctum')->get('/active-asset-registration-reviewers', [InventoryApiController::class,'activeAssetRegistrationReviewers'])->name('api.active.asset-registration-reviewers');
Route::middleware('auth:sanctum')->get('/active-asset-registration-approvers', [InventoryApiController::class,'activeAssetRegistrationApprovers'])->name('api.active.asset-registration-approvers');
Route::middleware('auth:sanctum')->get('/get-received-items', [PurchaseOrderApiController::class,'getReceivedItems'])->name('api.get.received-items');
Route::middleware('auth:sanctum')->get('/get-receiving-references', [PurchaseOrderApiController::class,'getReceivingReferences'])->name('api.get.receiving-references');


// petty cash voucher
Route::middleware('auth:sanctum')->get('/get-pcv-type', [SystemParameterApiController::class,'pcvType'])->name('api.get.pcv-type');


// Accounting
Route::middleware('auth:sanctum')->get('/get-selected-transaction_template', [AccountingApiController::class,'selectedTransactionTemplate'])->name('api.get.selected-transaction_template');
Route::middleware('auth:sanctum')->get('/get-active-account-type', [AccountingApiController::class,'activeAccountType'])->name('api.get.active-account-type');
Route::middleware('auth:sanctum')->get('/get-pcv-payee-employee', [AccountingApiController::class,'pcvPayeeEmployee'])->name('api.get.pcv-payee-employee');
Route::middleware('auth:sanctum')->get('/get-pcv-payee-customer', [AccountingApiController::class,'pcvPayeeCustomer'])->name('api.get.pcv-payee-customer');
