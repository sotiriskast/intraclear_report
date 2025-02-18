<?php

use App\Http\Controllers\SettlementController;
use App\Livewire\FeeTypeManagement;
use App\Livewire\MerchantFeeManagement;
use App\Livewire\MerchantManagement;
use App\Livewire\MerchantSettingsManagement;
use App\Livewire\MerchantSpecificFees;
use App\Livewire\MerchantView;
use App\Livewire\RoleManagement;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

Route::middleware(['auth:web', 'verified',
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
        });

        // Merchant Fees and Settings
        Route::middleware(['can:manage-merchants-fees'])->group(function () {
            Route::get('/merchants/{merchant}/fees', MerchantSpecificFees::class)->name('merchant.fees');
            Route::get('/merchant-fees', MerchantFeeManagement::class)->name('admin.merchant-fees');
            Route::get('/merchant-settings', MerchantSettingsManagement::class)->name('admin.merchant-settings');
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
    });
});
