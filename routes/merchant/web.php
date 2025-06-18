<?php

use Illuminate\Support\Facades\Route;

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
