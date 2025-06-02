<?php

use Illuminate\Support\Facades\Route;
use Modules\Decta\Http\Controllers\DectaController;
use Modules\Decta\Http\Controllers\DectaSftpViewController;
use Modules\Decta\Http\Controllers\DectaReportController;
use Modules\Decta\Http\Controllers\DectaTransactionController;

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
    Route::prefix('transactions')->group(function () {
        Route::get('/', [DectaTransactionController::class, 'index'])->name('decta.transactions.index');
        Route::get('/search', [DectaTransactionController::class, 'search'])->name('decta.transactions.search');
        Route::post('/export', [DectaTransactionController::class, 'export'])->name('decta.transactions.export');
        Route::get('/{id}', [DectaTransactionController::class, 'show'])->name('decta.transactions.show');
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
        // Declined transactions endpoints
        Route::get('/declined-transactions', [DectaReportController::class, 'getDeclinedTransactions'])->name('decta.reports.declined');
        Route::get('/decline-reasons', [DectaReportController::class, 'getDeclineReasons'])->name('decta.reports.decline-reasons');
        Route::get('/decline-analysis', [DectaReportController::class, 'getDeclineAnalysis'])->name('decta.reports.decline-analysis');
        Route::post('/compare-decline-rates', [DectaReportController::class, 'compareDeclineRates'])->name('decta.reports.compare-decline-rates');


        // Merchants API endpoint
        Route::get('/merchants', [DectaReportController::class, 'getMerchants'])->name('decta.reports.merchants');
    });
});
