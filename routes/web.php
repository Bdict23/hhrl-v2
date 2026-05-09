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
});

require __DIR__.'/auth.php';
