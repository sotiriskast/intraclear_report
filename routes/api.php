<?php

use App\Http\Controllers\Api\MerchantApiAuthController;
use App\Http\Controllers\Api\RollingReserveController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Route;


// API Version Prefix
// API Version Prefix
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/login', [MerchantApiAuthController::class, 'login'])
        ->name('api.auth.login')
        ->middleware('throttle:10,60'); // Limit login attempts

    // Protected merchant routes
    Route::middleware(['auth:sanctum', 'ability:merchant:read'])->group(function () {
        // Authentication
        Route::post('/auth/logout', [MerchantApiAuthController::class, 'logout'])
            ->name('api.auth.logout');

        // Rolling Reserves
        Route::prefix('rolling-reserves')->group(function () {
            Route::get('/', [RollingReserveController::class, 'index'])
                ->name('api.rolling-reserves.index')
                ->middleware('throttle:60,1'); // Rate limit for list endpoint

            Route::get('/summary', [RollingReserveController::class, 'summary'])
                ->name('api.rolling-reserves.summary')
                ->middleware('throttle:30,1');

            Route::get('/{id}', [RollingReserveController::class, 'show'])
                ->name('api.rolling-reserves.show')
                ->middleware('throttle:30,1');
        });
    });
});
