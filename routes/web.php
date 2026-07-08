<?php

use App\Livewire\User\Profile;
use Illuminate\Support\Facades\Route;
use App\Livewire\Users\Index;
use App\Livewire\Home\Dashboard;
use Livewire\Volt\Volt;





Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/users', Index::class)->name('users.index');
    Route::get('/user/profile', Profile::class)->name('user.profile');


    //EVENTS
    //EVENT LIQUIDATION
    Volt::route('/events/summary', 'events.event-liquidation.event-liquidation-summary')->name('event-liquidation-summary');
    Volt::route('/events/create', 'events.event-liquidation.event-liquidation-create')->name('event-liquidation-create');
    Volt::route('/events/edit/{id}', 'events.event-liquidation.event-liquidation-edit')->name('event-liquidation-edit');
    Volt::route('/events/view/{id}', 'events.event-liquidation.event-liquidation-view')->name('event-liquidation-view');
    Volt::route('/events/validation-summary', 'events.event-liquidation.event-liquidation-validation-summary-tab')->name('event-liquidation.validation-summary');
    Volt::route('/events/validation/review-show/{id}', 'events.event-liquidation.event-liquidation-validation-review-show')->name('event-liquidation.validation.review-show');
    Volt::route('/events/validation/approval-show/{id}', 'events.event-liquidation.event-liquidation-validation-approval-show')->name('event-liquidation.validation.approval-show');


    // INVENTORY SELECTION
    //Purchase Order
    Volt::route('/purchase-order/summary', 'inventory.purchase-order.purchase-order-summary')->name('purchase-order-summary');
    Volt::route('/purcahse-order/create', 'inventory.purchase-order.purchase-order-create')->name('purchase-order-create');
    Volt::route('/purcahse-order/edit/{id}', 'inventory.purchase-order.purchase-order-edit')->name('purchase-order-edit');
    Volt::route('/purcahse-order/view/{id}', 'inventory.purchase-order.purchase-order-view')->name('purchase-order-view');
    Volt::route('/purchase-order-validation-tab', 'inventory.purchase-order.purchase-order-validation-tabs')->name('purchase-order.validation-tabs');
    Volt::route('/purchase-order-validation-review-show/{id}', 'inventory.purchase-order.purchase-order-validation-review-show')->name('purchase-order.validation.review-show');
    Volt::route('/purchase-order-validation-approval-show/{id}', 'inventory.purchase-order.purchase-order-validation-approval-show')->name('purchase-order.validation.approval-show');


    //Receiving Order
    Volt::route('/receiving-order/summary', 'inventory.receiving-order.receiving-purchase-order-summary')->name('receiving-summary');
    Volt::route('/receiving-order/create', 'inventory.receiving-order.receiving-purchase-order-create')->name('receiving.create');
    Volt::route('/receiving-order/edit/{id}', 'inventory.receiving-order.receiving-purchase-order-edit')->name('receiving.edit');
    Volt::route('/receiving-order/view/{id}', 'inventory.receiving-order.receiving-purchase-order-view')->name('receiving.view');

    //BACKORDER
    Volt::route('/backorder/summary', 'inventory.backorder.backorder-summary')->name('backorder-summary');



    // Fixed Asset
    Volt::route('/fixed-asset-menu', 'inventory.fixed-asset.fixed-asset-menu')->name('fixed-asset.menu');
    Volt::route('/fixed-asset-registration', 'inventory.fixed-asset.fixed-asset-registration')->name('fixed-asset.registration');
    Volt::route('/fixed-asset-batch-view/{id}', 'inventory.fixed-asset.fixed-asset-batch-view')->name('fixed-asset.batch-view');


    // TRANSACTION
    // ADVANCES FOR LIQUIDATION
    Volt::route('/advances-for-liquidation/summary', 'transactions.advances-for-liquidation.advances-for-liquidation-summary')->name('afl.summary');
    Volt::route('/advances-for-liquidation/create', 'transactions.advances-for-liquidation.advances-for-liquidation-create')->name('afl.create');
    Volt::route('/advances-for-liquidation/view/{id}', 'transactions.advances-for-liquidation.advances-for-liquidation-view')->name('afl.view');
    Volt::route('/advances-for-liquidation/edit/{id}', 'transactions.advances-for-liquidation.advances-for-liquidation-edit')->name('afl.edit');

    //EMPLOYEES ADVANCES
    Volt::route('/employees-advances-summary', 'transactions.employees-advances.employees-advances-summary')->name('employees-advances.summary');
    Volt::route('/employees-advances-create', 'transactions.employees-advances.employees-advances-create')->name('employees-advances.create');
    Volt::route('/employees-advances-show/{id}', 'transactions.employees-advances.employees-advances-view')->name('employees-advances.view');
    Volt::route('/employees-advances-edit/{id}', 'transactions.employees-advances.employees-advances-edit')->name('employees-advances.edit');


    //Acknowledgement Receipt
    Volt::route('/acknowledgement-receipt/summary', 'transactions.acknowledgement-receipt.acknowledgement-receipt-summary')->name('acknowledgement-receipt.summary');
    Volt::route('acknowledgement-receipt/create', 'transactions.acknowledgement-receipt.acknowledgement-receipt-create')->name('acknowledgement-receipt.create');
    Volt::route('acknowledgement-receipt/view/{id}', 'transactions.acknowledgement-receipt.acknowledgement-receipt-view')->name('acknowledgement-receipt.view');
    Volt::route('acknowledgement-receipt/edit/{id}', 'transactions.acknowledgement-receipt.acknowledgement-receipt-edit')->name('acknowledgement-receipt.edit');

    //Petty Cash Voucher
    Volt::route('/petty-cash-voucher/summary', 'transactions.petty-cash-voucher.petty-cash-voucher-summary')->name('petty-cash-voucher.summary');
    Volt::route('/petty-cash-voucher/create', 'transactions.petty-cash-voucher.petty-cash-voucher-create')->name('petty-cash-voucher.create');
    Volt::route('/petty-cash-voucher/view/{id}', 'transactions.petty-cash-voucher.petty-cash-voucher-view')->name('petty-cash-voucher.view');
    Volt::route('/petty-cash-voucher/edit/{id}', 'transactions.petty-cash-voucher.petty-cash-voucher-edit')->name('petty-cash-voucher.edit');


    //CASH RETURN
    Volt::route('/cash-return/summary-tabs', 'transactions.cash-return.cash-return-summary-tab')->name('cash-return.summary-tab');
    //CRS PCV
    Volt::route('/cash-return/pcv-crs-create', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-create')->name('cash-return.pcv-crs.create');
    Volt::route('/cash-return/pcv-view/{id}', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-view')->name('cash-return.pcv-crs.view');
    Volt::route('/cash-return/pcv-edit/{id}', 'transactions.cash-return.cash-return-pcv.cash-return-pcv-edit')->name('cash-return.pcv-crs.edit');


    //EVENT
    Volt::route('/cash-return/event-crs-create', 'transactions.cash-return.cash-return-event.cash-return-event-create')->name('cash-return.event-crs.create');
    Volt::route('/cash-return/event-crs-edit/{id}', 'transactions.cash-return.cash-return-event.cash-return-event-edit')->name('cash-return.event-crs.edit');
    Volt::route('/cash-return/event-crs-view/{id}',  'transactions.cash-return.cash-return-event.cash-return-event-view')->name('cash-return.event-crs.view');
    //CRS AFL
    Volt::route('/cash-return/advances-for-liquidation-crs-create', 'transactions.cash-return.cash-return-afl.cash-return-afl-create')->name('cash-return.afl-crs.create');
    Volt::route('/cash-return/advances-for-liquidation-crs-view/{id}', 'transactions.cash-return.cash-return-afl.cash-return-afl-view')->name('cash-return.afl-crs.view');
    Volt::route('/cash-return/advances-for-liquidation-crs-edit/{id}', 'transactions.cash-return.cash-return-afl.cash-return-afl-edit')->name('cash-return.afl-crs.edit');
    //CRS FOR EMPLOYEE CASH ADVANCES
    Volt::route('/cash-return/employee-advance-create', 'transactions.cash-return.cash-return-employee-advances.cash-return-employee-advances-create')->name('cash-return.employee-advances.create');
    Volt::route('/cash-return/employee-advance-edit/{id}', 'transactions.cash-return.cash-return-employee-advances.cash-return-employee-advances-edit')->name('cash-return.employee-advances.edit');
    Volt::route('/cash-return/employee-advance-view/{id}', 'transactions.cash-return.cash-return-employee-advances.cash-return-employee-advances-view')->name('cash-return.employee-advances.view');



    // REIMBURSEMENT
    Volt::route('/reimbursement/summary', 'transactions.reimbursement.reimbursement-summary')->name('reimbursement.summary');
    Volt::route('/reimbursement/create', 'transactions.reimbursement.reimbursement-create')->name('reimbursement.create');
    Volt::route('/reimbursement/view/{id}', 'transactions.reimbursement.reimbursement-view')->name('reimbursement.view');
    Volt::route('/reimbursement/edit/{id}', 'transactions.reimbursement.reimbursement-edit')->name('reimbursement.edit');
    Volt::route('/reimbursement/validation-summary', 'transactions.reimbursement.reimbursement-validation-approval-summary')->name('reimbursement.validation-summary');
    Volt::route('/reimbursement/validation-review-show/{id}', 'transactions.reimbursement.reimbursement-validation-approval-view')->name('reimbursement.validation.approval-view');



    // REVOLVING FUND
    Volt::route('/revolving-fund/overview', 'transactions.revolving-fund.revolving-fund-overview')->name('revolving-fund.overview');




    // VALIDATION

    //Fixed Asset
    Volt::route('/fixed-asset/validation-summary', 'inventory.fixed-asset.fixed-asset-validation-summary')->name('fixed-asset.validation-summary');
    volt::route('/fixed-asset/validation-review-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-review-view')->name('fixed-asset.validation.review-view');
    volt::route('/fixed-asset/validation-approval-view/{id}', 'inventory.fixed-asset.fixed-asset-validation-approval-view')->name('fixed-asset.validation.approval-view');

    //CASH ADVANCES
    volt::route('/cash-advances/approval-summary', 'transactions.employees-advances.employees-advances-validation-approval-summary')->name('cash-advances.validation.approval-summary');
    volt::route('/cash-advances/approval-view/{id}', 'transactions.employees-advances.employees-advances-validation-approval-view')->name('cash-advances.validation.approval-view');
});

require __DIR__ . '/auth.php';
