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

        // AJAX API Endpoints
        Route::post('/test-connection', [DectaSftpViewController::class, 'testConnection'])->name('decta.sftp.test-connection');
        Route::get('/list', [DectaSftpViewController::class, 'listFiles'])->name('decta.sftp.list-files');
        Route::get('/status', [DectaSftpViewController::class, 'getStatus'])->name('decta.sftp.status');

        // File Operations
        Route::post('/download', [DectaSftpViewController::class, 'download'])->name('decta.sftp.download');
        Route::post('/process', [DectaSftpViewController::class, 'process'])->name('decta.sftp.process');

        // File Download/View
        Route::get('/download-file', [DectaSftpViewController::class, 'downloadFile'])->name('decta.sftp.download-file');


    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [DectaTransactionController::class, 'index'])->name('decta.transactions.index');
        Route::get('/search', [DectaTransactionController::class, 'search'])->name('decta.transactions.search');
        Route::post('/export', [DectaTransactionController::class, 'export'])->name('decta.transactions.export');
        Route::post('/export-scheme', [DectaTransactionController::class, 'exportSchemeReport'])->name('decta.transactions.export-scheme');

        // NEW: Large export routes
        Route::get('/export-estimate', [DectaTransactionController::class, 'getExportEstimate'])->name('decta.transactions.export-estimate');
        Route::post('/export-large', [DectaTransactionController::class, 'exportLargeDataset'])->name('decta.transactions.export-large');

        // Keep the {id} route LAST with numeric constraint
        Route::get('/{id}', [DectaTransactionController::class, 'show'])
            ->where('id', '[0-9]+')
            ->name('decta.transactions.show');
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
        Route::post('/volume-breakdown', [DectaReportController::class, 'getVolumeBreakdown'])->name('decta.reports.volume-breakdown');

        // Merchants API endpoint
        Route::get('/merchants', [DectaReportController::class, 'getMerchants'])->name('decta.reports.merchants');

        Route::get('/shops', [DectaReportController::class, 'getShops'])->name('decta.reports.shops');
        Route::get('/merchants/{merchantId}/shops', [DectaReportController::class, 'getShopsForMerchant'])->name('decta.reports.merchant-shops');

        // Shop synchronization route (admin feature)
        Route::post('/shops/sync', [DectaReportController::class, 'syncShopsFromTransactions'])->name('decta.reports.shops.sync');

//        Route::get('/debug-unmatched', [DectaReportController::class, 'debugUnmatchedTransactions'])->name('decta.reports.debug-unmatched');
    });


// Add this route to your web.php - you can access it via browser

    Route::get('/decta/debug-scheme', function () {
        try {
            $reportService = app(\Modules\Decta\Services\DectaReportService::class);
            $exportService = app(\Modules\Decta\Services\DectaExportService::class);

            $filters = [
                'date_from' => '2025-06-01',
                'date_to' => '2025-06-05',
            ];

            echo "<h1>Debugging Decta Scheme Export</h1>";
            echo "<h2>Step 1: Environment Check</h2>";
            echo "<p><strong>Storage Path:</strong> " . storage_path('app') . "</p>";
            echo "<p><strong>Exports Dir:</strong> " . storage_path('app/exports') . "</p>";
            echo "<p><strong>Exports Dir Exists:</strong> " . (is_dir(storage_path('app/exports')) ? 'YES' : 'NO') . "</p>";
            echo "<p><strong>Storage Writable:</strong> " . (is_writable(storage_path('app')) ? 'YES' : 'NO') . "</p>";
            echo "<p><strong>PHP User:</strong> " . get_current_user() . "</p>";

            if (!is_dir(storage_path('app/exports'))) {
                echo "<p><strong>Creating exports directory...</strong></p>";
                mkdir(storage_path('app/exports'), 0755, true);
                echo "<p>Directory created: " . (is_dir(storage_path('app/exports')) ? 'SUCCESS' : 'FAILED') . "</p>";
            }

            echo "<h2>Step 2: Generate Scheme Data</h2>";
            $schemeData = $reportService->generateReport('scheme', $filters);
            echo "<p><strong>Data Count:</strong> " . count($schemeData) . "</p>";

            if (empty($schemeData)) {
                echo "<p><strong>ERROR:</strong> No scheme data found!</p>";
                echo "<p>This means the database query is not returning any results.</p>";
                echo "<p>Check your decta_transactions table for data in the date range.</p>";
                return;
            }

            echo "<h3>Sample Data:</h3>";
            echo "<pre>" . json_encode(array_slice($schemeData, 0, 2), JSON_PRETTY_PRINT) . "</pre>";

            echo "<h2>Step 3: Test Export</h2>";
            $filePath = $exportService->exportToCsv($schemeData, 'scheme', $filters);
            $fullPath = storage_path('app/' . $filePath);

            echo "<p><strong>File Path:</strong> " . $filePath . "</p>";
            echo "<p><strong>Full Path:</strong> " . $fullPath . "</p>";
            echo "<p><strong>File Exists:</strong> " . (file_exists($fullPath) ? 'YES' : 'NO') . "</p>";

            if (file_exists($fullPath)) {
                $fileSize = filesize($fullPath);
                echo "<p><strong>File Size:</strong> " . $fileSize . " bytes</p>";

                if ($fileSize > 0) {
                    echo "<h3>File Content Preview:</h3>";
                    echo "<pre>" . htmlspecialchars(substr(file_get_contents($fullPath), 0, 1000)) . "</pre>";
                    echo "<p><strong>SUCCESS:</strong> Export is working correctly!</p>";
                    echo "<p><a href='/storage/app/" . $filePath . "' target='_blank'>Try to download the file</a></p>";
                } else {
                    echo "<p><strong>ERROR:</strong> File exists but is empty!</p>";
                }
            } else {
                echo "<p><strong>ERROR:</strong> File was not created!</p>";
            }

        } catch (\Exception $e) {
            echo "<h2>ERROR</h2>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<h3>Stack Trace:</h3>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
    });
});
