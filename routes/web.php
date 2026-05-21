<?php

use App\Livewire\User\Profile;
use Illuminate\Support\Facades\Route;
use App\Livewire\Users\Index;
use App\Livewire\Home\Dashboard;
use Illuminate\Support\Facades\Http;
use App\Livewire\Inventory\PurchaseOrderAction;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\SupplierApiController as SupplierApi;
use App\Livewire\Inventory\FixedAsset;




Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth'])->group(function () {
    // Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/users', Index::class)->name('users.index');
    Route::get('/user/profile', Profile::class)->name('user.profile');

// INVENTORY SELECTION
    //Purchase Order
    Volt::route('/purchase-order/summary', 'inventory.purchase-order.purchase-order-summary')->name('purchase-order-summary');
    Volt::route('/purcahse-order/create', 'inventory.purchase-order.purchase-order-create')->name('purchase-order-create');
    Volt::route('/purcahse-order/edit/{id}', 'inventory.purchase-order.purchase-order-edit')->name('purchase-order-edit');
    Volt::route('/purcahse-order/view/{id}', 'inventory.purchase-order.purchase-order-view')->name('purchase-order-view');

    //Receiving Order
    Volt::route('/receiving-order/summary', 'inventory.receiving-order.receiving-purchase-order-summary')->name('receiving-summary');

    // Fixed Asset
    Volt::route('/fixed-asset-menu','inventory.fixed-asset.fixed-asset-menu')->name('fixed-asset.menu');
    Volt::route('/fixed-asset-registration','inventory.fixed-asset.fixed-asset-registration')->name('fixed-asset.registration');
    Volt::route('/fixed-asset-batch-view/{id}','inventory.fixed-asset.fixed-asset-batch-view')->name('fixed-asset.batch-view');

// TRANSACTION

    //Petty Cash Voucher
    Volt::route('/petty-cash-voucher/summary', 'transactions.petty-cash-voucher.petty-cash-voucher-summary')->name('petty-cash-voucher.summary');
    Volt::route('/petty-cash-voucher/create', 'transactions.petty-cash-voucher.petty-cash-voucher-create')->name('petty-cash-voucher.create');
    Volt::route('/petty-cash-voucher/view/{id}', 'transactions.petty-cash-voucher.petty-cash-voucher-view')->name('petty-cash-voucher.view');
    Volt::route('/petty-cash-voucher/edit/{id}', 'transactions.petty-cash-voucher.petty-cash-voucher-edit')->name('petty-cash-voucher.edit');



// VALIDATION

    //Fixed Asset
    Volt::route('/fixed-asset/validation-summary', 'inventory.fixed-asset.fixed-asset-validation-summary')->name('fixed-asset.validation-summary');
    volt::route('/fixed-asset/validation-review-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-review-view')->name('fixed-asset.validation.review-view');
    volt::route('/fixed-asset/validation-approval-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-approval-view')->name('fixed-asset.validation.approval-view');


});

require __DIR__.'/auth.php';
