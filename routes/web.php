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


    // CASH RETURN
        Volt::route('/cash-return/summary-tabs', 'transactions.cash-return.cash-return-summary-tab')->name('cash-return.summary-tab');
            //PCV
                Volt::route('/cash-return/pcv-crs-create', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-create')->name('cash-return.pcv-crs.create');
                Volt::route('/cash-return/pcv-view/{id}', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-view')->name('cash-return.pcv-crs.view');
                Volt::route('/cash-return/pcv-edit/{id}', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-edit')->name('cash-return.pcv-crs.edit');
            //EVENT
                Volt::route('/cash-return/event-crs-create', 'transactions.cash-return.cash-return-event.cash-return-event-create')->name('cash-return.event-crs.create');
                Volt::route('/cash-return/event-crs-view/{id}', 'transactions.cash-return.cash-return-event.cash-return-event-view')->name('cash-return.event-crs.view');
                Volt::route('/cash-return/event-crs-edit/{id}',  'transactions.cash-return.cash-return-event.cash-return-event-view')->name('cash-return.event-crs.view');
            //CASH ADVANCES
                Volt::route('/cash-return/cash-advance-crs-create', 'transactions.cash-return.cash-return-ca.cash-return-ca-create')->name('cash-return.cash-advance-crs.create');
                Volt::route('/cash-return/cash-advance-crs-view/{id}', 'transactions.cash-return.cash-return-ca.cash-return-ca-view')->name('cash-return.cash-advance-crs.view');
                Volt::route('/cash-return/cash-advance-crs-edit/{id}', 'transactions.cash-return.cash-return-ca.cash-return-ca-edit')->name('cash-return.cash-advance-crs.edit');






// VALIDATION

    //Fixed Asset
    Volt::route('/fixed-asset/validation-summary', 'inventory.fixed-asset.fixed-asset-validation-summary')->name('fixed-asset.validation-summary');
    volt::route('/fixed-asset/validation-review-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-review-view')->name('fixed-asset.validation.review-view');
    volt::route('/fixed-asset/validation-approval-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-approval-view')->name('fixed-asset.validation.approval-view');


});

require __DIR__.'/auth.php';
