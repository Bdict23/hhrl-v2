<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SupplierApiController;
use App\Http\Controllers\Api\PurchaseOrderApiController;
use App\Http\Controllers\Api\BanquetEventApiController;
use App\Http\Controllers\Api\RestaurantApiController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->get('/suppliers', [SupplierApiController::class, 'index'])->name('api.supplier.index');
Route::middleware('auth:sanctum')->get('/active-purchase-type', [PurchaseOrderApiController::class,'activePurchaseType'])->name('api.active.purchase-type');
Route::middleware('auth:sanctum')->get('/active-purchase-term', [PurchaseOrderApiController::class,'activePurchaseTerm'])->name('api.active.purchase-term');
Route::middleware('auth:sanctum')->get('/active-approvers', [PurchaseOrderApiController::class,'activeApprovers'])->name('api.active.approvers');
Route::middleware('auth:sanctum')->get('/active-reviewers', [PurchaseOrderApiController::class,'activeReviewers'])->name('api.active.reviewers');
Route::middleware('auth:sanctum')->get('/active-event', [BanquetEventApiController::class,'activeEvent'])->name('api.active.event');
Route::middleware('auth:sanctum')->get('/active-production-order', [RestaurantApiController::class,'activeProductionOrder'])->name('api.active.production-order');
