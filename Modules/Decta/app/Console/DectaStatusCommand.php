<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Models\DectaTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class DectaStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:status
                            {--detailed : Show detailed statistics}
                            {--days=7 : Number of days to analyze}
                            {--file-id= : Show status for specific file}
                            {--export= : Export report to file (csv|json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Decta processing status and statistics';

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaTransactionRepository|null
     */
    protected $transactionRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaFileRepository $fileRepository,
        ?DectaTransactionRepository $transactionRepository = null
    ) {
        parent::__construct();
        $this->fileRepository = $fileRepository;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $detailed = $this->option('detailed');
            $days = (int) $this->option('days');
            $fileId = $this->option('file-id') ? (int) $this->option('file-id') : null;
            $export = $this->option('export');

            $this->info('Decta Processing Status Report');
            $this->info('Generated at: ' . Carbon::now()->format('Y-m-d H:i:s'));
            $this->line(str_repeat('=', 60));

            $reportData = [];

            if ($fileId) {
                $reportData = $this->generateFileReport($fileId);
            } else {
                $reportData = $this->generateSystemReport($days, $detailed);
            }

            // Export if requested
            if ($export) {
                $this->exportReport($reportData, $export);
            }

            return 0;

        } catch (Exception $e) {
            $this->error("Status report failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Generate system-wide report
     */
    private function generateSystemReport(int $days, bool $detailed): array
    {
        $reportData = [
            'overview' => $this->getSystemOverview(),
            'file_statistics' => $this->getFileStatistics($days),
            'health_status' => $this->getHealthStatus(),
        ];

        // Only include transaction stats if we have transaction repository
        if ($this->transactionRepository) {
            $reportData['transaction_statistics'] = $this->getTransactionStatistics($days);
        }

        // Display overview
        $this->displaySystemOverview($reportData['overview']);

        // Display file statistics
        $this->displayFileStatistics($reportData['file_statistics']);

        // Display transaction statistics if available
        if (isset($reportData['transaction_statistics'])) {
            $this->displayTransactionStatistics($reportData['transaction_statistics']);
        }

        // Display health status
        $this->displayHealthStatus($reportData['health_status']);

        if ($detailed) {
            $reportData['detailed'] = $this->getDetailedStatistics($days);
            $this->displayDetailedStatistics($reportData['detailed']);
        }

        return $reportData;
    }

    /**
     * Generate file-specific report
     */
    private function generateFileReport(int $fileId): array
    {
        $file = DectaFile::findOrFail($fileId);

        $reportData = [
            'file' => $file->toArray(),
            'transactions' => [],
        ];

        // Get transaction stats if available
        if ($this->transactionRepository) {
            $stats = $this->transactionRepository->getStatistics($fileId);
            $reportData['statistics'] = $stats;
            $reportData['transactions'] = $this->getFileTransactionBreakdown($fileId);
            $this->displayFileReport($file, $stats, $reportData['transactions']);
        } else {
            // Just display basic file info
            $this->displayBasicFileReport($file);
        }

        return $reportData;
    }

    /**
     * Get system overview
     */
    private function getSystemOverview(): array
    {
        $overview = [
            'total_files' => DectaFile::count(),
            'files_today' => DectaFile::whereDate('created_at', Carbon::today())->count(),
            'last_file_processed' => DectaFile::latest('updated_at')->first()?->updated_at,
        ];

        // Add transaction counts if table exists
        if ($this->tableExists('decta_transactions')) {
            $overview['total_transactions'] = DectaTransaction::count();
            $overview['transactions_today'] = DectaTransaction::whereDate('created_at', Carbon::today())->count();
            $overview['last_transaction_matched'] = DectaTransaction::whereNotNull('matched_at')->latest('matched_at')->first()?->matched_at;
        }

        return $overview;
    }

    /**
     * Get file statistics
     */
    private function getFileStatistics(int $days): array
    {
        return $this->fileRepository->getStatistics($days);
    }

    /**
     * Get transaction statistics
     */
    private function getTransactionStatistics(int $days): array
    {
        if (!$this->transactionRepository || !$this->tableExists('decta_transactions')) {
            return [];
        }

        $dateRange = [
            'start' => Carbon::now()->subDays($days)->toDateString(),
            'end' => Carbon::now()->toDateString(),
        ];

        return $this->transactionRepository->getStatistics(null, $dateRange);
    }

    /**
     * Get health status
     */
    private function getHealthStatus(): array
    {
        $issues = [];
        $warnings = [];

        // Check for stuck files
        $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->count();

        if ($stuckFiles > 0) {
            $issues[] = "{$stuckFiles} files stuck in processing state";
        }

        // Check recent processing activity
        $recentFiles = DectaFile::whereDate('created_at', '>=', Carbon::now()->subDays(1))->count();
        if ($recentFiles === 0) {
            $warnings[] = 'No files processed in the last 24 hours';
        }

        // Check match rates if transactions exist
        if ($this->tableExists('decta_transactions')) {
            $recentTransactions = DectaTransaction::whereDate('created_at', '>=', Carbon::now()->subDays(1));
            $totalRecent = $recentTransactions->count();
            $matchedRecent = (clone $recentTransactions)->where('is_matched', true)->count();
            $matchRate = $totalRecent > 0 ? ($matchedRecent / $totalRecent) * 100 : 100;

            if ($matchRate < 70 && $totalRecent > 10) {
                $warnings[] = "Low match rate: " . round($matchRate, 1) . "% (last 24h)";
            }
        }

        // Check failed files
        $failedFiles = DectaFile::where('status', DectaFile::STATUS_FAILED)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        if ($failedFiles > 5) {
            $warnings[] = "{$failedFiles} files failed in the last 7 days";
        }

        $status = 'healthy';
        if (!empty($issues)) {
            $status = 'error';
        } elseif (!empty($warnings)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'warnings' => $warnings,
            'metrics' => [
                'stuck_files' => $stuckFiles,
                'recent_files' => $recentFiles,
                'failed_files' => $failedFiles,
            ],
        ];
    }

    /**
     * Get detailed statistics
     */
    private function getDetailedStatistics(int $days): array
    {
        $startDate = Carbon::now()->subDays($days);
        $detailed = [];

        if ($this->tableExists('decta_transactions')) {
            $detailed = [
                'top_merchants' => $this->getTopMerchants($startDate),
                'currency_breakdown' => $this->getCurrencyBreakdown($startDate),
                'error_analysis' => $this->getErrorAnalysis($startDate),
                'processing_times' => $this->getProcessingTimes($startDate),
            ];
        }

        return $detailed;
    }

    /**
     * Display system overview
     */
    private function displaySystemOverview(array $overview): void
    {
        $this->info("\nSystem Overview:");

        $tableData = [
            ['Total Files', number_format($overview['total_files'])],
            ['Files Today', number_format($overview['files_today'])],
            ['Last File Processed', $overview['last_file_processed'] ? Carbon::parse($overview['last_file_processed'])->diffForHumans() : 'Never'],
        ];

        if (isset($overview['total_transactions'])) {
            $tableData[] = ['Total Transactions', number_format($overview['total_transactions'])];
            $tableData[] = ['Transactions Today', number_format($overview['transactions_today'])];
            $tableData[] = ['Last Transaction Matched', $overview['last_transaction_matched'] ? Carbon::parse($overview['last_transaction_matched'])->diffForHumans() : 'Never'];
        }

        $this->table(['Metric', 'Value'], $tableData);
    }

    /**
     * Display file statistics
     */
    private function displayFileStatistics(array $stats): void
    {
        $this->info("\nFile Statistics:");

        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', $stats['total'], '100%'],
                ['Processed', $stats['processed'], $this->percentage($stats['processed'], $stats['total'])],
                ['Failed', $stats['failed'], $this->percentage($stats['failed'], $stats['total'])],
                ['Pending', $stats['pending'], $this->percentage($stats['pending'], $stats['total'])],
            ]
        );

        if ($stats['total'] > 0) {
            $successRate = ($stats['processed'] / $stats['total']) * 100;
            $this->info("Success Rate: " . round($successRate, 1) . "%");
        }
    }

    /**
     * Display transaction statistics
     */
    private function displayTransactionStatistics(array $stats): void
    {
        $this->info("\nTransaction Statistics:");

        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total', number_format($stats['total']), '100%'],
                ['Matched', number_format($stats['matched']), $this->percentage($stats['matched'], $stats['total'])],
                ['Unmatched', number_format($stats['unmatched']), $this->percentage($stats['unmatched'], $stats['total'])],
                ['Failed', number_format($stats['failed']), $this->percentage($stats['failed'], $stats['total'])],
                ['Pending', number_format($stats['pending']), $this->percentage($stats['pending'], $stats['total'])],
            ]
        );

        $this->info("Match Rate: " . round($stats['match_rate'], 1) . "%");
        $this->info("Total Amount: €" . number_format($stats['total_amount'], 2));
        $this->info("Matched Amount: €" . number_format($stats['matched_amount'], 2));

        // Currency breakdown
        if (!empty($stats['currency_breakdown'])) {
            $this->info("\nCurrency Breakdown:");
            $currencyTable = [];
            foreach ($stats['currency_breakdown'] as $currency => $data) {
                $currencyTable[] = [
                    $currency,
                    number_format($data['count']),
                    '€' . number_format($data['total_amount'], 2)
                ];
            }
            $this->table(['Currency', 'Count', 'Amount'], $currencyTable);
        }
    }

    /**
     * Display health status
     */
    private function displayHealthStatus(array $health): void
    {
        $this->info("\nHealth Status:");

        $statusColor = match($health['status']) {
            'healthy' => 'info',
            'warning' => 'warn',
            'error' => 'error',
        };

        $this->$statusColor("Overall Status: " . strtoupper($health['status']));

        if (!empty($health['issues'])) {
            $this->error("Issues:");
            foreach ($health['issues'] as $issue) {
                $this->line("  ✗ {$issue}");
            }
        }

        if (!empty($health['warnings'])) {
            $this->warn("Warnings:");
            foreach ($health['warnings'] as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }

        if (empty($health['issues']) && empty($health['warnings'])) {
            $this->info("  ✓ All systems operating normally");
        }
    }

    /**
     * Display file-specific report
     */
    private function displayFileReport(DectaFile $file, array $stats, array $transactions): void
    {
        $this->info("\nFile Report: {$file->filename}");
        $this->line("File ID: {$file->id}");
        $this->line("Status: {$file->status}");
        $this->line("Created: {$file->created_at->format('Y-m-d H:i:s')}");
        $this->line("Size: " . $this->formatBytes($file->file_size ?? 0));

        if ($file->processed_at) {
            $this->line("Processed: {$file->processed_at->format('Y-m-d H:i:s')}");
        }

        if ($file->error_message) {
            $this->error("Error: {$file->error_message}");
        }

        $this->displayTransactionStatistics($stats);
    }

    /**
     * Display basic file report (when transactions are not available)
     */
    private function displayBasicFileReport(DectaFile $file): void
    {
        $this->info("\nFile Report: {$file->filename}");
        $this->line("File ID: {$file->id}");
        $this->line("Status: {$file->status}");
        $this->line("Created: {$file->created_at->format('Y-m-d H:i:s')}");
        $this->line("Size: " . $this->formatBytes($file->file_size ?? 0));

        if ($file->processed_at) {
            $this->line("Processed: {$file->processed_at->format('Y-m-d H:i:s')}");
        }

        if ($file->error_message) {
            $this->error("Error: {$file->error_message}");
        }

        $this->warn("Transaction details not available - decta_transactions table may not exist");
    }

    /**
     * Check if a table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($tableName);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Calculate percentage
     */
    private function percentage(int $part, int $total): string
    {
        if ($total === 0) return '0%';
        return round(($part / $total) * 100, 1) . '%';
    }

    /**
     * Format bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Export report to file
     */
    private function exportReport(array $data, string $format): void
    {
        $filename = 'decta_status_' . Carbon::now()->format('Y-m-d_H-i-s') . '.' . $format;
        $path = storage_path("app/reports/{$filename}");

        // Create directory if it doesn't exist
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            switch ($format) {
                case 'json':
                    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                    break;

                case 'csv':
                    // Flatten data for CSV export
                    $this->exportToCsv($data, $path);
                    break;

                default:
                    $this->error("Unsupported export format: {$format}");
                    return;
            }

            $this->info("Report exported to: {$path}");

        } catch (Exception $e) {
            $this->error("Failed to export report: {$e->getMessage()}");
        }
    }

    /**
     * Export data to CSV
     */
    private function exportToCsv(array $data, string $path): void
    {
        $handle = fopen($path, 'w');

        // Write headers for overview
        if (isset($data['overview'])) {
            fputcsv($handle, ['Section', 'Metric', 'Value']);
            foreach ($data['overview'] as $key => $value) {
                fputcsv($handle, ['Overview', $key, $value]);
            }
        }

        fclose($handle);
    }

    /**
     * Get additional detailed statistics (only if transactions exist)
     */
    private function getTopMerchants(Carbon $startDate): array
    {
        if (!$this->tableExists('decta_transactions')) {
            return [];
        }

        return DectaTransaction::where('created_at', '>=', $startDate)
            ->whereNotNull('merchant_id')
            ->selectRaw('merchant_id, COUNT(*) as transaction_count, SUM(tr_amount) as total_amount')
            ->groupBy('merchant_id')
            ->orderBy('transaction_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getCurrencyBreakdown(Carbon $startDate): array
    {
        if (!$this->tableExists('decta_transactions')) {
            return [];
        }

        return DectaTransaction::where('created_at', '>=', $startDate)
            ->whereNotNull('tr_ccy')
            ->selectRaw('tr_ccy, COUNT(*) as count, SUM(tr_amount) as total_amount')
            ->groupBy('tr_ccy')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    private function getErrorAnalysis(Carbon $startDate): array
    {
        if (!$this->tableExists('decta_transactions')) {
            return [];
        }

        return DectaTransaction::where('created_at', '>=', $startDate)
            ->where('status', DectaTransaction::STATUS_FAILED)
            ->whereNotNull('error_message')
            ->selectRaw('error_message, COUNT(*) as count')
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getProcessingTimes(Carbon $startDate): array
    {
        try {
            $result = DectaFile::where('created_at', '>=', $startDate)
                ->whereNotNull('processed_at')
                ->selectRaw('
                    AVG(EXTRACT(EPOCH FROM (processed_at - created_at))/60) as avg_processing_time,
                    MIN(EXTRACT(EPOCH FROM (processed_at - created_at))/60) as min_processing_time,
                    MAX(EXTRACT(EPOCH FROM (processed_at - created_at))/60) as max_processing_time
                ')
                ->first();

            return $result ? $result->toArray() : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function getFileTransactionBreakdown(int $fileId): array
    {
        if (!$this->tableExists('decta_transactions')) {
            return [];
        }

        return DectaTransaction::where('decta_file_id', $fileId)
            ->selectRaw('status, is_matched, COUNT(*) as count')
            ->groupBy('status', 'is_matched')
            ->get()
            ->toArray();
    }

    /**
     * Display detailed statistics
     */
    private function displayDetailedStatistics(array $detailed): void
    {
        if (empty($detailed)) {
            $this->info("\nDetailed statistics not available.");
            return;
        }

        $this->info("\nDetailed Statistics:");

        // Top merchants
        if (!empty($detailed['top_merchants'])) {
            $this->info("\nTop Merchants by Transaction Count:");
            $merchantTable = [];
            foreach (array_slice($detailed['top_merchants'], 0, 5) as $merchant) {
                $merchantTable[] = [
                    $merchant['merchant_id'],
                    number_format($merchant['transaction_count']),
                    '€' . number_format($merchant['total_amount'] / 100, 2)
                ];
            }
            $this->table(['Merchant ID', 'Transactions', 'Total Amount'], $merchantTable);
        }

        // Processing times
        if (!empty($detailed['processing_times']) && isset($detailed['processing_times']['avg_processing_time'])) {
            $this->info("\nProcessing Times:");
            $this->line("Average: " . round($detailed['processing_times']['avg_processing_time'], 1) . " minutes");
            $this->line("Minimum: " . round($detailed['processing_times']['min_processing_time'] ?? 0, 1) . " minutes");
            $this->line("Maximum: " . round($detailed['processing_times']['max_processing_time'] ?? 0, 1) . " minutes");
        }

        // Error analysis
        if (!empty($detailed['error_analysis'])) {
            $this->info("\nTop Errors:");
            foreach (array_slice($detailed['error_analysis'], 0, 3) as $error) {
                $this->line("• " . substr($error['error_message'], 0, 50) . "... ({$error['count']} times)");
            }
        }
    }
}
