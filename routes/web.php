<?php

use App\Http\Controllers\MerchantController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShopFeeController;
use App\Http\Controllers\ShopSettingController;
use App\Http\Controllers\SettlementController;
use App\Livewire\MerchantAnalytics;
use App\Livewire\MerchantManagement;
use App\Livewire\MerchantView;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

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
        Route::middleware(['can:create-merchant-users'])->group(function () {
            Route::resource('merchant-users', \App\Http\Controllers\MerchantUserController::class)
                ->names([
                    'index' => 'admin.merchant-users.index',
                    'create' => 'admin.merchant-users.create',
                    'store' => 'admin.merchant-users.store',
                    'show' => 'admin.merchant-users.show',
                    'edit' => 'admin.merchant-users.edit',
                    'update' => 'admin.merchant-users.update',
                    'destroy' => 'admin.merchant-users.destroy',
                ]);
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
    Route::get('/health', function () {
        $healthChecks = [
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'version' => config('app.version', '1.0.0'),
            'services' => []
        ];

        // Database connectivity check
        try {
            DB::connection()->getPdo();
            $healthChecks['services']['database'] = 'ok';
        } catch (\Exception $e) {
            $healthChecks['services']['database'] = 'error';
            $healthChecks['status'] = 'degraded';
        }

        // Payment Database connectivity check
        try {
            DB::connection('payment_gateway_mysql')->getPdo();
            $healthChecks['services']['payment_database'] = 'ok';
        } catch (\Exception $e) {
            $healthChecks['services']['payment_database'] = 'error';
            $healthChecks['status'] = 'degraded';
        }

        // Redis connectivity check
        try {
            Redis::connection()->ping();
            $healthChecks['services']['redis'] = 'ok';
        } catch (\Exception $e) {
            $healthChecks['services']['redis'] = 'error';
            $healthChecks['status'] = 'degraded';
        }

        // S3 storage check
        try {
            Storage::disk('s3')->exists('health-check');
            $healthChecks['services']['s3_storage'] = 'ok';
        } catch (\Exception $e) {
            $healthChecks['services']['s3_storage'] = 'error';
            $healthChecks['status'] = 'degraded';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 10);
            $healthChecks['services']['cache'] = Cache::get('health_check') === 'ok' ? 'ok' : 'error';
        } catch (\Exception $e) {
            $healthChecks['services']['cache'] = 'error';
            $healthChecks['status'] = 'degraded';
        }

        // Queue check (simplified)
        try {
            $healthChecks['services']['queue'] = config('queue.default') === 'redis' &&
            $healthChecks['services']['redis'] === 'ok' ? 'ok' : 'degraded';
        } catch (\Exception $e) {
            $healthChecks['services']['queue'] = 'error';
        }

        // Third-party services status
        $healthChecks['services']['cesop'] = env('CESOP_PSP_NAME') ? 'configured' : 'not_configured';
        $healthChecks['services']['decta'] = env('DECTA_SFTP_HOST') ? 'configured' : 'not_configured';

        // Overall status
        $errorCount = count(array_filter($healthChecks['services'], fn($status) => $status === 'error'));
        if ($errorCount > 0) {
            $healthChecks['status'] = $errorCount > 2 ? 'critical' : 'degraded';
        }

        $statusCode = match($healthChecks['status']) {
            'ok' => Response::HTTP_OK,
            'degraded' => Response::HTTP_OK, // Still return 200 for degraded
            'critical' => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };

        return response()->json($healthChecks, $statusCode);
    });

// Readiness check (for Kubernetes/Docker health checks)
    Route::get('/ready', function () {
        try {
            // Quick essential checks only
            DB::connection()->getPdo();
            return response()->json(['status' => 'ready'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'not_ready', 'error' => $e->getMessage()], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    });

// Liveness check (for Kubernetes/Docker health checks)
    Route::get('/live', function () {
        return response()->json(['status' => 'alive'], Response::HTTP_OK);
    });

});
Route::prefix('merchant')->name('merchant.')->group(function () {
    // Guest routes (not authenticated)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [\App\Http\Controllers\Auth\MerchantAuthController::class, 'showLoginForm'])
            ->name('login');
        Route::post('/login', [\App\Http\Controllers\Auth\MerchantAuthController::class, 'login'])
            ->name('login.submit');
    });

    // Authenticated merchant routes - ISOLATED from admin
    Route::middleware(['auth:web', 'merchant.access'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Auth\MerchantAuthController::class, 'logout'])
            ->name('logout');

        Route::get('/dashboard', function () {
            return view('merchant.dashboard');
        })->name('dashboard');
    });
});

// Default redirect for root - check user type
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->user_type === 'merchant') {
            return redirect('/merchant/dashboard');
        }

        if (in_array($user->user_type, ['admin', 'super-admin'])) {
            return redirect('/admin/dashboard');
        }
    }

    return redirect('/login');
})->name('home');
