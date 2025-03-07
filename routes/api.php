<?php

use App\Api\V1\Controllers\Auth\MerchantApiAuthController;
use App\Api\V1\Controllers\RollingReserve\RollingReserveController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with '/api' by default in RouteServiceProvider
| Version prefix adds '/v1' making the full prefix '/api/v1'
|
*/

Route::prefix('v1')->group(function () {
    // Public routes
    Route::middleware('throttle:10,60')->group(function () {
        Route::post('/auth/login', [MerchantApiAuthController::class, 'login'])
            ->name('api.v1.auth.login');
    });

    // Protected routes
    Route::middleware([
        'auth:sanctum',
        'ability:merchant:read',
        'merchant.active',
    ])->group(function () {
        Route::post('/auth/logout', [MerchantApiAuthController::class, 'logout'])
            ->name('api.v1.auth.logout');
        Route::prefix('rolling-reserves')->group(function () {
            Route::get('/', [RollingReserveController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('api.v1.rolling-reserves.index');

            Route::get('/summary', [RollingReserveController::class, 'summary'])
                ->middleware('throttle:30,1')
                ->name('api.v1.rolling-reserves.summary');

            Route::get('/{id}', [RollingReserveController::class, 'show'])
                ->middleware('throttle:30,1')
                ->whereNumber('id')
                ->name('api.v1.rolling-reserves.show');
        });
    });
    Route::prefix('/dashboard')->middleware(['dashboard-api'])->group(function () {
        Route::get('/merchants', [DashboardController::class, 'getMerchants']);
        Route::get('/rolling-reserve/summary', [DashboardController::class, 'getReserveSummary']);
        Route::get('/rolling-reserve', [DashboardController::class, 'getRollingReserves']);
        Route::get('/fees/history', [DashboardController::class, 'getFeeHistory']);
    });
});


