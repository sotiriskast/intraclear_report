<?php

use Illuminate\Support\Facades\Route;
use Modules\Decta\Http\Controllers\DectaController;
use Modules\Decta\Http\Controllers\DectaSftpViewController;
use Modules\Decta\Http\Controllers\DectaReportController;

Route::middleware(['auth', 'verified'])->prefix('decta')->group(function () {
    Route::get('/', [DectaController::class, 'index'])->name('decta.index');

    // SFTP Management Routes
    Route::prefix('sftp')->group(function () {
        Route::get('/', [DectaSftpViewController::class, 'index'])->name('decta.sftp.index');
        Route::get('/list', [DectaSftpViewController::class, 'listFiles'])->name('decta.sftp.list-files');
        Route::post('/download', [DectaSftpViewController::class, 'download'])->name('decta.sftp.download');
        Route::post('/process', [DectaSftpViewController::class, 'process'])->name('decta.sftp.process');
        Route::get('/download-file', [DectaSftpViewController::class, 'downloadFile'])->name('decta.sftp.download-file');
    });

    // Reports Routes
    Route::prefix('reports')->group(function () {
        // Main reports page
        Route::get('/', [DectaReportController::class, 'index'])->name('decta.reports.index');

        // Report generation
        Route::post('/generate', [DectaReportController::class, 'generateReport'])->name('decta.reports.generate');

        // Dashboard API endpoints
        Route::get('/dashboard-data', [DectaReportController::class, 'getDashboardData'])->name('decta.reports.dashboard');

        // Transaction details
        Route::get('/transaction/{paymentId}', [DectaReportController::class, 'getTransactionDetails'])->name('decta.reports.transaction');

        // Unmatched transactions for manual review
        Route::get('/unmatched', [DectaReportController::class, 'getUnmatchedTransactions'])->name('decta.reports.unmatched');
    });
});
