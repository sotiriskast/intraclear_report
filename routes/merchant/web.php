<?php

use Illuminate\Support\Facades\Route;

Route::prefix('merchant')->name('merchant.')->group(function () {
    // Authenticated merchant routes - ISOLATED from admin
    Route::middleware(['auth:web', 'verified','merchant.access'])->group(function () {
        Route::get('/dashboard', function () {
            return view('merchant.dashboard');
        })->name('dashboard');
    });
});
