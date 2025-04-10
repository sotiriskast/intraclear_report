<?php

use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\SettlementController;
use App\Livewire\FeeTypeManagement;
use App\Livewire\MerchantAnalytics;
use App\Livewire\MerchantFeeManagement;
use App\Livewire\MerchantManagement;
use App\Livewire\MerchantSettingsManagement;
use App\Livewire\MerchantSpecificFees;
use App\Livewire\MerchantView;
use App\Livewire\RoleManagement;
use App\Livewire\UserManagement;
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
            Route::get('/users', UserManagement::class)->name('admin.users');
        });

        Route::middleware(['can:manage-roles'])->group(function () {
            Route::get('/roles', RoleManagement::class)->name('admin.roles');
        });

        // Merchant Management
        Route::middleware(['can:manage-merchants'])->group(function () {
            Route::get('/merchants', MerchantManagement::class)->name('admin.merchants');
            Route::get('/merchants/{merchant}/view', MerchantView::class)->name('merchant.view');
            Route::get('/merchants/{merchant}/analytics', MerchantAnalytics::class)->name('merchant.analytics');
        });

        // Merchant Fees and Settings
        Route::middleware(['can:manage-merchants-fees'])->group(function () {
            Route::get('/merchants/{merchant}/fees', MerchantSpecificFees::class)->name('merchant.fees');
            Route::get('/merchant-fees', MerchantFeeManagement::class)->name('admin.merchant-fees');
            Route::get('/merchant-settings', MerchantSettingsManagement::class)->name('admin.merchant-settings');
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
            Route::get('/fee-types', FeeTypeManagement::class)->name('admin.fee-types');
        });

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
