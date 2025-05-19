<?php

use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\MerchantFeeController;
use App\Http\Controllers\MerchantSettingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopFeeController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\SettlementController;
use App\Livewire\MerchantAnalytics;
use App\Livewire\MerchantManagement;
use App\Livewire\MerchantView;
use App\Livewire\RoleManagement;
use App\Livewire\ShopManagement;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:web', 'verified', '2fa.required'
])->group(function () {
    Route::get('/', function () {
        return redirect('/admin/dashboard');
    });
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('admin.dashboard');

        Route::middleware(['can:manage-users'])->group(function () {
            // User Management Routes
            Route::resource('users', \App\Http\Controllers\UserController::class)
                ->names([
                    'index' => 'admin.users.index',
                    'create' => 'admin.users.create',
                    'store' => 'admin.users.store',
                    'edit' => 'admin.users.edit',
                    'update' => 'admin.users.update',
                    'destroy' => 'admin.users.destroy',
                ]);
        });

        Route::middleware(['can:manage-roles'])->group(function () {
            Route::resource('roles', \App\Http\Controllers\RoleController::class)
                ->names([
                    'index' => 'admin.roles.index',
                    'create' => 'admin.roles.create',
                    'store' => 'admin.roles.store',
                    'edit' => 'admin.roles.edit',
                    'update' => 'admin.roles.update',
                    'destroy' => 'admin.roles.destroy',
                ]);
        });

        // Merchant Management
        Route::middleware(['can:manage-merchants'])->group(function () {
            Route::get('/merchants', MerchantManagement::class)->name('admin.merchants');
            Route::get('/merchants/{merchant}/view', MerchantView::class)->name('merchant.view');
            Route::get('/merchants/{merchant}/analytics', MerchantAnalytics::class)->name('merchant.analytics');
            Route::get('/merchants/{merchant}/edit', [MerchantController::class, 'edit'])->name('merchants.edit');
            Route::put('/merchants/{merchant}', [MerchantController::class, 'update'])->name('merchants.update');


            // Shop Management for Merchants
            Route::get('/merchants/{merchant}/shops', [ShopController::class, 'index'])->name('admin.merchants.shops');
            Route::get('/shops/create/{merchant}', [ShopController::class, 'create'])->name('admin.shops.create');
            Route::post('/shops/{merchant}', [ShopController::class, 'store'])->name('admin.shops.store');
            Route::get('/shops/{shop}/edit', [ShopController::class, 'edit'])->name('admin.shops.edit');
            Route::put('/shops/{shop}', [ShopController::class, 'update'])->name('admin.shops.update');
            Route::get('/shops/{shop}/settings', [ShopController::class, 'settings'])->name('admin.shops.settings');
        });

        // Merchant Fees and Settings
        Route::middleware(['can:manage-merchants-fees'])->group(function () {
            // Shop Fees and Settings
            Route::resource('shop-fees', ShopFeeController::class)
                ->names([
                    'index' => 'admin.shop-fees.index',
                    'create' => 'admin.shop-fees.create',
                    'store' => 'admin.shop-fees.store',
                    'edit' => 'admin.shop-fees.edit',
                    'update' => 'admin.shop-fees.update',
                    'destroy' => 'admin.shop-fees.destroy',
                ]);
            Route::resource('shop-settings', ShopSettingController::class)
                ->names([
                    'index' => 'admin.shop-settings.index',
                    'create' => 'admin.shop-settings.create',
                    'store' => 'admin.shop-settings.store',
                    'edit' => 'admin.shop-settings.edit',
                    'update' => 'admin.shop-settings.update',
                    'destroy' => 'admin.shop-settings.destroy',
                ]);
        });

        // for API key management
        Route::middleware(['can:manage-merchants-api-keys'])->group(function () {
            Route::get('/merchants/{merchant}/api', [App\Http\Controllers\Admin\ApiKeyController::class, 'show'])
                ->name('merchant.api');

            Route::post('/merchants/{merchant}/api/generate', [App\Http\Controllers\Admin\ApiKeyController::class, 'generate'])
                ->name('merchant.api.generate');

            Route::delete('/merchants/{merchant}/api/revoke', [App\Http\Controllers\Admin\ApiKeyController::class, 'revokeAllTokens'])
                ->name('merchant.api.revoke');
        });

        Route::middleware(['can:manage-fees'])->group(function () {
// Fee Type Management Routes
            Route::resource('fee-types', \App\Http\Controllers\FeeTypeController::class)
                ->names([
                    'index' => 'admin.fee-types.index',
                    'create' => 'admin.fee-types.create',
                    'store' => 'admin.fee-types.store',
                    'edit' => 'admin.fee-types.edit',
                    'update' => 'admin.fee-types.update',
                    'destroy' => 'admin.fee-types.destroy',
                ]);        });

        //Settlement Report
        Route::middleware(['can:manage-settlements'])->group(function () {
            Route::controller(SettlementController::class)->group(function () {
                Route::get('/settlements', 'index')->name('settlements.index');
                Route::get('/settlements/generate', 'showGenerateForm')->name('settlements.generate-form');
                Route::post('/settlements/generate', 'generate')->name('settlements.generate');
                Route::get('/settlements/download/{id}', 'download')->name('settlements.download');

                Route::prefix('settlements/archives')->group(function () {
                    Route::get('/', 'archives')->name('settlements.archives');
                    Route::get('/{id}/download', 'downloadZip')->name('settlements.archives.download');
                });
            });
        });
    });
});
