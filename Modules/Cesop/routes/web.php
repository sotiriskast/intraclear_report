<?php

use Illuminate\Support\Facades\Route;
use Modules\Cesop\Http\Controllers\CesopController;
use Modules\Cesop\Http\Controllers\CesopReportController;

Route::middleware(['auth:web', 'verified'
])->group(function () {
    Route::prefix('admin/cesop')->group(function () {
        Route::get('/encrypt', [CesopController::class, 'index'])->name('cesop.encrypt.index');
        Route::post('/encrypt/upload', [CesopController::class, 'upload'])->name('cesop.encrypt.upload');
        Route::get('/encrypt/success', [CesopController::class, 'success'])->name('cesop.encrypt.success');
        Route::get('/encrypt/download/{filename}', [CesopController::class, 'download'])->name('cesop.encrypt.download');

        Route::get('report', [CesopReportController::class, 'index'])->name('cesop.report.index');
        Route::post('report/preview', [CesopReportController::class, 'preview'])->name('cesop.report.preview');
        Route::post('report/generate', [CesopReportController::class, 'generate'])->name('cesop.report.generate');
        Route::post('report/download', [CesopReportController::class, 'download'])->name('cesop.report.download');
        Route::post('report/shops', [CesopReportController::class, 'getShops'])->name('cesop.report.getShops');
        Route::get('report/import-excel', [CesopReportController::class, 'importExcelIndex'])->name('cesop.report.import-excel.index');
        Route::post('report/import-excel', [CesopReportController::class, 'importExcel'])->name('cesop.report.import-excel.upload');
    });
});
