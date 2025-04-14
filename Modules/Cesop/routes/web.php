<?php

use Illuminate\Support\Facades\Route;
use Modules\Cesop\Http\Controllers\CesopController;

Route::middleware(['auth:web', 'verified'
])->group(function () {
    Route::prefix('admin')->group(function () {
        Route::get('/cesop', [CesopController::class, 'index'])->name('cesop.index');
        Route::post('/cesop/upload', [CesopController::class, 'upload'])->name('cesop.upload');
        Route::get('/cesop/success', [CesopController::class, 'success'])->name('cesop.success');
        Route::get('/cesop/download/{filename}', [CesopController::class, 'download'])->name('cesop.download');
    });
});
