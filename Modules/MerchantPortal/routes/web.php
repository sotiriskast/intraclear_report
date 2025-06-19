<?php

use Illuminate\Support\Facades\Route;
use Modules\MerchantPortal\Http\Controllers\DashboardController;
use Modules\MerchantPortal\Http\Controllers\TransactionController;
use Modules\MerchantPortal\Http\Controllers\ShopController;
use Modules\MerchantPortal\Http\Controllers\RollingReserveController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');

// Transactions
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::get('/{id}', [TransactionController::class, 'show'])->name('show');
});

// Shops
Route::prefix('shops')->name('shops.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::get('/{id}', [ShopController::class, 'show'])->name('show');
});

// Rolling Reserves
Route::prefix('rolling-reserves')->name('rolling-reserves.')->group(function () {
    Route::get('/', [RollingReserveController::class, 'index'])->name('index');
    Route::get('/summary', [RollingReserveController::class, 'summary'])->name('summary');
});
