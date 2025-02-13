<?php

use App\Http\Controllers\UserNotificationRecipientController;
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
        Route::get('/users', UserManagement::class)->name('admin.users')->middleware(['can:manage-users']);
        Route::get('/roles', RoleManagement::class)->name('admin.roles')->middleware(['can:manage-roles']);
        Route::get('/merchants', MerchantManagement::class)->name('admin.merchants')->middleware(['can:manage-merchants']);
        Route::get('/merchants/{merchant}/view', MerchantView::class)->name('merchant.view')->middleware(['can:manage-merchants']);
        Route::get('/merchants/{merchant}/fees', MerchantSpecificFees::class)->name('merchant.fees')->middleware(['can:manage-merchants-fees']);
        Route::get('/merchant-fees', MerchantFeeManagement::class)->name('admin.merchant-fees')->middleware(['can:manage-merchants-fees']);
        Route::get('/fee-types', FeeTypeManagement::class)->name('admin.fee-types')->middleware(['can:manage-fees']);
        Route::get('/merchant-settings', MerchantSettingsManagement::class)->name('admin.merchant-settings')->middleware(['can:manage-merchants-fees']);

        Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
        Route::get('/settlements/generate', [SettlementController::class, 'showGenerateForm'])->name('settlements.generate-form');
        Route::post('/settlements/generate', [SettlementController::class, 'generate'])->name('settlements.generate');
        Route::get('/settlements/download/{id}', [SettlementController::class, 'download'])->name('settlements.download');
        Route::get('/settlements/download-batch/{ids}', [SettlementController::class, 'downloadBatch'])->name('settlements.download-batch');


        Route::resource('notification-recipients', UserNotificationRecipientController::class)->only(['index', 'destroy']);
        Route::post('notification-recipients/{recipient}/toggle', [UserNotificationRecipientController::class, 'toggleActive'])->name('notification-recipients.toggle');
    });
});
