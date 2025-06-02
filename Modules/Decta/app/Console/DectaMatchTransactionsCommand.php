<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Services\DectaNotificationService;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class DectaMatchTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:match-transactions
                            {--file-id= : Match transactions for a specific file}
                            {--limit=100 : Limit number of files to process for matching}
                            {--retry-failed : Retry previously failed matches}
                            {--max-attempts=3 : Maximum matching attempts per transaction}
                            {--force : Force re-matching of already matched transactions}
                            {--all : Process all files with unmatched transactions}
                            {--no-email : Disable email notifications for this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match Decta transactions with payment gateway database using the same logic as file processing';

    /**
     * @var DectaTransactionService
     */
    protected $transactionService;

    /**
     * @var DectaTransactionRepository
     */
    protected $transactionRepository;

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaNotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaTransactionService $transactionService,
        DectaTransactionRepository $transactionRepository,
        DectaFileRepository $fileRepository,
        DectaNotificationService $notificationService
    ) {
        parent::__construct();
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->fileRepository = $fileRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta transaction matching process...');

        $startTime = now();
        $errorMessages = [];

        try {
            // Check system health
            if (!$this->checkSystemHealth()) {
                $error = 'System health check failed. Aborting matching process.';
                $this->error($error);

                $this->sendNotificationIfEnabled([
                    'files_processed' => 0,
                    'total_matched' => 0,
                    'total_unmatched' => 0,
                    'total_errors' => 1,
                    'error_messages' => [$error],
                    'duration' => $startTime->diffInMinutes(now()),
                ], false);

                return 1;
            }

            $fileId = $this->option('file-id') ? (int) $this->option('file-id') : null;
            $limit = (int) $this->option('limit');
            $retryFailed = $this->option('retry-failed');
            $maxAttempts = (int) $this->option('max-attempts');
            $force = $this->option('force');
            $all = $this->option('all');

            // Get files to process for matching
            $filesToProcess = $this->getFilesToProcess($fileId, $limit, $retryFailed, $force, $all);

            if ($filesToProcess->isEmpty()) {
                $this->info('No files found with transactions to match.');

                $this->sendNotificationIfEnabled([
                    'files_processed' => 0,
                    'total_matched' => 0,
                    'total_unmatched' => 0,
                    'total_errors' => 0,
                    'message' => 'No files found with transactions to match',
                    'duration' => $startTime->diffInMinutes(now()),
                ], true);

                return 0;
            }

            $this->info(sprintf('Found %d file(s) with transactions to match.', $filesToProcess->count()));

            // Display initial overall statistics
            $this->displayOverallStats();

            $totalMatched = 0;
            $totalUnmatched = 0;
            $totalErrors = 0;
            $filesProcessed = 0;

            $progressBar = $this->output->createProgressBar($filesToProcess->count());
            $progressBar->start();

            foreach ($filesToProcess as $file) {
                try {
                    $this->line(" Processing file: {$file->filename} (ID: {$file->id})");

                    // Check if matching is needed for this file
                    if (!$force && $this->isMatchingComplete($file)) {
                        $this->info("  All transactions already matched, skipping...");
                        $progressBar->advance();
                        continue;
                    }

                    // Get file statistics before matching
                    $beforeStats = $this->getFileMatchingStats($file);
                    $this->info("  Before matching: {$beforeStats['unmatched']} unmatched, {$beforeStats['matched']} matched");

                    // Use the same matching logic as ProcessFilesCommand
                    $matchingResults = $this->handleMatching($file, $force, $maxAttempts);

                    $totalMatched += $matchingResults['matched'];
                    $totalUnmatched += $matchingResults['failed'];

                    if (isset($matchingResults['error'])) {
                        $totalErrors++;
                        $errorMessages[] = "File {$file->filename}: {$matchingResults['error']}";
                    }

                    $filesProcessed++;

                    // Display results for this file
                    $this->info("  Results:");
                    $this->info("   - Transactions matched: {$matchingResults['matched']}");
                    $this->info("   - Transactions unmatched: {$matchingResults['failed']}");

                    if (isset($matchingResults['error'])) {
                        $this->warn("   - Error occurred: {$matchingResults['error']}");
                    }

                    // Log successful matching
                    Log::info('File matching completed', [
                        'file_id' => $file->id,
                        'filename' => $file->filename,
                        'matching_results' => $matchingResults,
                    ]);

                } catch (Exception $e) {
                    $totalErrors++;
                    $error = "Failed to match transactions for {$file->filename}: {$e->getMessage()}";
                    $errorMessages[] = $error;
                    $this->error("  {$error}");

                    Log::error('Error matching transactions for file', [
                        'file_id' => $file->id,
                        'filename' => $file->filename,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display final results
            $this->displayFinalResults($filesProcessed, $totalMatched, $totalUnmatched, $totalErrors);

            // Prepare notification data
            $notificationData = [
                'files_processed' => $filesProcessed,
                'total_matched' => $totalMatched,
                'total_unmatched' => $totalUnmatched,
                'total_errors' => $totalErrors,
                'error_messages' => $errorMessages,
                'duration' => $startTime->diffInMinutes(now()),
            ];

            // Send notification
            $success = $totalErrors === 0;
            $this->sendNotificationIfEnabled($notificationData, $success);

            return $totalErrors > 0 ? 1 : 0;

        } catch (Exception $e) {
            $error = "Matching process failed: {$e->getMessage()}";
            $this->error($error);

            Log::error('Decta transaction matching failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification
            $this->sendNotificationIfEnabled([
                'files_processed' => 0,
                'total_matched' => 0,
                'total_unmatched' => 0,
                'total_errors' => 1,
                'error_messages' => [$error],
                'duration' => $startTime->diffInMinutes(now()),
            ], false);

            return 1;
        }
    }

    /**
     * Send notification if enabled and not disabled by --no-email option
     */
    private function sendNotificationIfEnabled(array $results, bool $success): void
    {
        // Skip if --no-email option is used
        if ($this->option('no-email')) {
            return;
        }

        // Check if matching notifications are enabled
        if (!config('decta.notifications.matching.enabled', true)) {
            return;
        }

        // Check specific success/failure settings
        if ($success && !config('decta.notifications.matching.send_on_success', true)) {
            return;
        }

        if (!$success && !config('decta.notifications.matching.send_on_failure', true)) {
            return;
        }

        try {
            $this->notificationService->sendMatchingNotification($results, $success);
            $this->line($success ? '📧 Success notification sent' : '📧 Failure notification sent');
        } catch (Exception $e) {
            $this->warn("Failed to send email notification: {$e->getMessage()}");
            Log::warning('Failed to send matching notification', [
                'error' => $e->getMessage(),
                'results' => $results,
                'success' => $success,
            ]);
        }
    }

    /**
     * Check system health before processing
     */
    private function checkSystemHealth(): bool
    {
        try {
            // Test payment gateway database connection
            DB::connection('payment_gateway_mysql')->getPdo();
            $this->line('✓ Payment gateway database connection OK');

            // Test main database connection
            DB::connection()->getPdo();
            $this->line('✓ Main database connection OK');

            return true;
        } catch (Exception $e) {
            $this->error("Database connection failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get files to process for matching
     */
    private function getFilesToProcess(
        ?int $fileId,
        int $limit,
        bool $retryFailed,
        bool $force,
        bool $all
    ) {
        if ($fileId) {
            // Process specific file
            $file = $this->fileRepository->findById($fileId);
            return collect($file ? [$file] : []);
        }

        if ($all) {
            // Get all processed files that have transactions
            return DectaFile::where('status', DectaFile::STATUS_PROCESSED)
                ->whereHas('dectaTransactions')
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();
        }

        if ($retryFailed) {
            // Get files that have failed matching attempts or unmatched transactions
            return DectaFile::where('status', DectaFile::STATUS_PROCESSED)
                ->whereHas('dectaTransactions', function($query) {
                    $query->where('status', DectaTransaction::STATUS_FAILED)
                        ->orWhere('is_matched', false);
                })
                ->orderBy('updated_at', 'desc')
                ->take($limit)
                ->get();
        }

        // Default: Get files with unmatched transactions
        return DectaFile::where('status', DectaFile::STATUS_PROCESSED)
            ->whereHas('dectaTransactions', function($query) {
                $query->where('is_matched', false)
                    ->where('status', '!=', DectaTransaction::STATUS_FAILED);
            })
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Handle transaction matching for a file (same logic as ProcessFilesCommand)
     */
    private function handleMatching(DectaFile $file, bool $forceMatching, int $maxAttempts): array
    {
        $this->line("  Matching transactions with payment gateway...");

        try {
            // Check if matching is needed
            if (!$forceMatching && $this->isMatchingComplete($file)) {
                $this->info("  All transactions already matched, skipping...");
                return ['matched' => 0, 'failed' => 0, 'skipped' => true];
            }

            // Reset failed transactions if force matching
            if ($forceMatching) {
                $this->resetFailedTransactions($file, $maxAttempts);
            }

            // Use the transaction service (same as ProcessFilesCommand)
            return $this->transactionService->matchTransactions($file->id);

        } catch (Exception $e) {
            $this->warn("  Warning: Matching failed: {$e->getMessage()}");
            Log::error('Transaction matching failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);

            return ['matched' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if matching is complete for a file (same logic as ProcessFilesCommand)
     */
    private function isMatchingComplete(DectaFile $file): bool
    {
        $totalTransactions = $file->dectaTransactions()->count();
        $matchedTransactions = $file->dectaTransactions()->where('is_matched', true)->count();
        $failedTransactions = $file->dectaTransactions()->where('status', DectaTransaction::STATUS_FAILED)->count();

        // Consider matching complete if all transactions are either matched or permanently failed
        $processedTransactions = $matchedTransactions + $failedTransactions;

        return $totalTransactions > 0 && $processedTransactions >= $totalTransactions;
    }

    /**
     * Reset failed transactions for retry if force matching
     */
    private function resetFailedTransactions(DectaFile $file, int $maxAttempts): void
    {
        $failedTransactions = $file->dectaTransactions()
            ->where('status', DectaTransaction::STATUS_FAILED)
            ->get();

        if ($failedTransactions->count() > 0) {
            $this->line("  Resetting {$failedTransactions->count()} failed transactions for retry...");

            foreach ($failedTransactions as $transaction) {
                $transaction->update([
                    'status' => DectaTransaction::STATUS_PENDING,
                    'error_message' => null,
                    'matching_attempts' => [], // Reset attempts
                ]);
            }

            Log::info('Reset failed transactions for retry', [
                'file_id' => $file->id,
                'reset_count' => $failedTransactions->count(),
            ]);
        }
    }

    /**
     * Get file matching statistics
     */
    private function getFileMatchingStats(DectaFile $file): array
    {
        $total = $file->dectaTransactions()->count();
        $matched = $file->dectaTransactions()->where('is_matched', true)->count();
        $failed = $file->dectaTransactions()->where('status', DectaTransaction::STATUS_FAILED)->count();
        $pending = $file->dectaTransactions()->where('status', DectaTransaction::STATUS_PENDING)->count();

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $total - $matched,
            'failed' => $failed,
            'pending' => $pending,
            'match_rate' => $total > 0 ? ($matched / $total) * 100 : 0,
        ];
    }

    /**
     * Display overall statistics
     */
    private function displayOverallStats(): void
    {
        $stats = $this->transactionRepository->getStatistics();

        $this->info("\nOverall Statistics:");
        $this->info(" - Total transactions: " . number_format($stats['total']));
        $this->info(" - Matched: " . number_format($stats['matched']) . " (" . round($stats['match_rate'], 1) . "%)");
        $this->info(" - Unmatched: " . number_format($stats['unmatched']));
        $this->info(" - Failed: " . number_format($stats['failed']));
        $this->info(" - Pending: " . number_format($stats['pending']));

        if ($stats['total'] > 0) {
            $this->newLine();
        }
    }

    /**
     * Display final results
     */
    private function displayFinalResults(int $filesProcessed, int $totalMatched, int $totalUnmatched, int $totalErrors): void
    {
        $this->info("Matching process completed:");
        $this->info(" - Files processed: {$filesProcessed}");
        $this->info(" - Total transactions matched: {$totalMatched}");
        $this->info(" - Total transactions unmatched: {$totalUnmatched}");
        $this->info(" - Files with errors: {$totalErrors}");

        if ($totalMatched + $totalUnmatched > 0) {
            $matchRate = round(($totalMatched / ($totalMatched + $totalUnmatched)) * 100, 2);
            $this->info(" - Overall match rate: {$matchRate}%");
        }

        // Display updated overall statistics
        $this->newLine();
        $this->displayOverallStats();

        if ($totalUnmatched > 0) {
            $this->warn("\nSome transactions could not be matched. Consider:");
            $this->info(" - Running with --retry-failed to retry failed matches");
            $this->info(" - Running with --force to retry all transactions");
            $this->info(" - Checking transaction data quality");
            $this->info(" - Reviewing gateway database for missing transactions");
        }

        if ($totalErrors > 0) {
            $this->warn("\nSome files had matching errors. Check logs for details.");
            $this->info("Use --retry-failed option to retry failed matches.");
        }

        // Provide usage examples
        $this->info("\nUsage examples:");
        $this->info(" - Match specific file: --file-id=123");
        $this->info(" - Retry failed matches: --retry-failed");
        $this->info(" - Force re-match all: --force --all");
        $this->info(" - Process only recent files: --limit=10");
    }
}
