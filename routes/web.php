<?php

use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\MerchantFeeController;
use App\Http\Controllers\MerchantSettingController;
use App\Http\Controllers\SettlementController;
use App\Livewire\FeeTypeManagement;
use App\Livewire\MerchantAnalytics;
use App\Livewire\MerchantManagement;
use App\Livewire\MerchantView;
use App\Livewire\RoleManagement;
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
            Route::resource('admin/users', \App\Http\Controllers\UserController::class)
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
            Route::resource('admin/roles', \App\Http\Controllers\RoleController::class)
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
        });

        // Merchant Fees and Settings
        Route::middleware(['can:manage-merchants-fees'])->group(function () {
            Route::resource('admin/merchant-fees', MerchantFeeController::class)
                ->names([
                    'index' => 'admin.merchant-fees.index',
                    'create' => 'admin.merchant-fees.create',
                    'store' => 'admin.merchant-fees.store',
                    'edit' => 'admin.merchant-fees.edit',
                    'update' => 'admin.merchant-fees.update',
                    'destroy' => 'admin.merchant-fees.destroy',
                ]);
            Route::resource('admin/merchant-settings', MerchantSettingController::class)
                ->names([
                    'index' => 'admin.merchant-settings.index',
                    'create' => 'admin.merchant-settings.create',
                    'store' => 'admin.merchant-settings.store',
                    'edit' => 'admin.merchant-settings.edit',
                    'update' => 'admin.merchant-settings.update',
                    'destroy' => 'admin.merchant-settings.destroy',
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
            Route::resource('admin/fee-types', \App\Http\Controllers\FeeTypeController::class)
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

        if (app()->environment(['local', 'testing'])) {

            Route::prefix('test-emails')->group(function () {
                // Dashboard for testing all email types
                Route::get('/', [EmailTestController::class, 'dashboard'])
                    ->name('test.email.dashboard');

                // Test route for merchant sync failed email
                Route::get('/merchant-sync-failed', [EmailTestController::class, 'testMerchantSyncFailed'])
                    ->name('test.email.sync-failed');

                // Test route for new merchant created email
                Route::get('/new-merchant-created', [EmailTestController::class, 'testNewMerchantCreated'])
                    ->name('test.email.new-merchant');

                // Preview routes for email templates
                Route::prefix('preview')->group(function () {
                    Route::get('/merchant-sync-failed', function () {
                        return view('emails.settlements.merchant-sync-failed', [
                            'errorMessage' => 'This is a test error message for merchant sync failure',
                            'stackTrace' => "Exception: Test Exception\n at MerchantSyncService.php:123\n at SyncController.php:45"
                        ]);
                    })->name('preview.email.sync-failed');

                    Route::get('/new-merchant-created', function () {
                        return view('emails.settlements.new-merchant-created', [
                            'merchantId' => 12345,
                            'accountId' => 'ACC_98765',
                            'name' => 'Test Merchant Corp',
                            'email' => 'test@testmerchant.com',
                            'phone' => '555-123-4567',
                            'isActive' => true,
                            'timestamp' => now()->format('Y-m-d H:i:s')
                        ]);
                    })->name('preview.email.new-merchant');
                });
            });
        }
    });
});
