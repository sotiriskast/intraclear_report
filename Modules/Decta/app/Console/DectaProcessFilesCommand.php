<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class DectaProcessFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:process-files
                            {--limit=50 : Limit the number of files to process}
                            {--file-id= : Process a specific file by ID}
                            {--skip-matching : Skip transaction matching process}
                            {--retry-failed : Retry processing failed files}
                            {--force-reprocess : Force reprocessing of already processed files}
                            {--force-matching : Force re-matching even for already matched transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process downloaded Decta transaction files and match with payment gateway';

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * @var DectaTransactionService
     */
    protected $transactionService;

    /**
     * The disk to use for file operations
     *
     * @var string
     */
    protected $diskName = 'decta';

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaFileRepository $fileRepository,
        DectaSftpService $sftpService,
        DectaTransactionService $transactionService
    ) {
        parent::__construct();
        $this->fileRepository = $fileRepository;
        $this->sftpService = $sftpService;
        $this->transactionService = $transactionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Enhanced Decta transaction file processing...');

        try {
            // Check server status first
            if (!$this->checkSystemHealth()) {
                $this->error('System health check failed. Aborting to prevent data corruption.');
                return 1;
            }

            $limit = (int) $this->option('limit');
            $fileId = $this->option('file-id');
            $skipMatching = $this->option('skip-matching');
            $retryFailed = $this->option('retry-failed');
            $forceReprocess = $this->option('force-reprocess');
            $forceMatching = $this->option('force-matching');

            // Get files to process
            $files = $this->getFilesToProcess($fileId, $limit, $retryFailed, $forceReprocess);

            if ($files->isEmpty()) {
                $this->info('No files to process.');
                return 0;
            }

            $this->info(sprintf('Found %d file(s) to process.', $files->count()));

            $processedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            $totalMatched = 0;
            $totalUnmatched = 0;

            $progressBar = $this->output->createProgressBar($files->count());
            $progressBar->start();

            foreach ($files as $file) {
                try {
                    $this->line(" Processing: {$file->filename}");

                    // Check if file is already fully processed and shouldn't be reprocessed
                    if (!$forceReprocess && $this->isFileAlreadyProcessed($file)) {
                        $this->info("  File already fully processed, skipping CSV processing...");

                        // Only do matching if requested and not skipped
                        if (!$skipMatching) {
                            $matchingResults = $this->handleMatching($file, $forceMatching);
                            $totalMatched += $matchingResults['matched'];
                            $totalUnmatched += $matchingResults['failed'];

                            $this->info("  Matching results:");
                            $this->info("   - Transactions matched: {$matchingResults['matched']}");
                            $this->info("   - Transactions unmatched: {$matchingResults['failed']}");
                        } else {
                            $this->info("  Matching skipped as requested.");
                        }

                        $skippedCount++;
                        $progressBar->advance();
                        continue;
                    }

                    // Mark file as processing with timestamp
                    $file->update([
                        'status' => DectaFile::STATUS_PROCESSING,
                        'updated_at' => now()
                    ]);

                    // Enhanced processing with resume capability
                    $processingResults = $this->processTransactionFile($file);

                    if ($processingResults['processed'] > 0) {
                        // Process matching if not skipped
                        $matchingResults = ['matched' => 0, 'failed' => 0];

                        if (!$skipMatching) {
                            $matchingResults = $this->handleMatching($file, $forceMatching);
                            $totalMatched += $matchingResults['matched'];
                            $totalUnmatched += $matchingResults['failed'];
                        }

                        // Mark file as processed and move to processed directory
                        $file->markAsProcessed();
                        $this->moveFileToProcessed($file);

                        $processedCount++;

                        // Display results
                        $this->info("  Results:");
                        $this->info("   - CSV rows processed: {$processingResults['processed']}");
                        $this->info("   - CSV rows failed: {$processingResults['failed']}");

                        if (isset($processingResults['skipped']) && $processingResults['skipped'] > 0) {
                            $this->info("   - CSV rows resumed from: {$processingResults['skipped']}");
                        }

                        if (!$skipMatching) {
                            $this->info("   - Transactions matched: {$matchingResults['matched']}");
                            $this->info("   - Transactions unmatched: {$matchingResults['failed']}");
                        }

                        // Log successful processing
                        Log::info('Decta file processed successfully', [
                            'file_id' => $file->id,
                            'filename' => $file->filename,
                            'processing_results' => $processingResults,
                            'matching_results' => $matchingResults,
                        ]);

                    } else {
                        throw new Exception('No rows were processed from the CSV file');
                    }

                } catch (Exception $e) {
                    // Enhanced error handling
                    $this->handleProcessingError($file, $e);
                    $failedCount++;

                    $this->error("  Failed to process {$file->filename}: {$e->getMessage()}");

                    Log::error('Error processing Decta file', [
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

            // Display final summary
            $this->displayProcessingSummary($processedCount, $skippedCount, $failedCount, $totalMatched, $totalUnmatched, $skipMatching);

            if ($failedCount > 0) {
                return 1;
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to process files: {$e->getMessage()}");
            Log::error('Failed to process Decta files', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Check if file is already fully processed and has all transactions
     */
    private function isFileAlreadyProcessed(DectaFile $file): bool
    {
        // If file status is not processed, it's not complete
        if ($file->status !== DectaFile::STATUS_PROCESSED) {
            return false;
        }

        // Get detailed progress information
        $progress = $this->transactionService->getFileProgress($file);

        // Check if processing is actually complete
        if (!$progress['is_complete']) {
            return false;
        }

        // Check for data quality issues
        if (!empty($progress['issues'])) {
            Log::info('File marked as processed but has data quality issues', [
                'file_id' => $file->id,
                'issues' => $progress['issues'],
            ]);
            return false;
        }

        // Additional verification: check if we have reasonable transaction count
        $transactionCount = $file->dectaTransactions()->count();
        if ($transactionCount === 0) {
            Log::warning('File marked as processed but has no transactions', [
                'file_id' => $file->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle transaction matching for a file
     */
    private function handleMatching(DectaFile $file, bool $forceMatching): array
    {
        $this->line("  Matching transactions with payment gateway...");

        try {
            // Check if matching is needed
            if (!$forceMatching && $this->isMatchingComplete($file)) {
                $this->info("  All transactions already matched, skipping...");
                return ['matched' => 0, 'failed' => 0, 'skipped' => true];
            }

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
     * Check if matching is complete for a file
     */
    private function isMatchingComplete(DectaFile $file): bool
    {
        $totalTransactions = $file->dectaTransactions()->count();
        $matchedTransactions = $file->dectaTransactions()->where('is_matched', true)->count();
        $failedTransactions = $file->dectaTransactions()->where('status', 'failed')->count();

        // Consider matching complete if all transactions are either matched or permanently failed
        $processedTransactions = $matchedTransactions + $failedTransactions;

        return $totalTransactions > 0 && $processedTransactions >= $totalTransactions;
    }

    /**
     * Check system health before processing
     */
    private function checkSystemHealth(): bool
    {
        try {
            $this->info('Running system health checks...');

            // Check database connections
            if (!$this->testDatabaseConnections()) {
                return false;
            }

            // Check disk space
            if (!$this->checkDiskSpace()) {
                return false;
            }

            // Check memory usage
            if (!$this->checkMemoryUsage()) {
                return false;
            }

            // Check storage disk accessibility
            if (!$this->checkStorageAccess()) {
                return false;
            }

            $this->info('System health check passed.');
            return true;

        } catch (Exception $e) {
            $this->error("System health check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check storage disk accessibility
     */
    private function checkStorageAccess(): bool
    {
        try {
            // Test if the decta disk is accessible
            $testFile = 'health_check_' . time() . '.tmp';
            Storage::disk($this->diskName)->put($testFile, 'test');

            if (!Storage::disk($this->diskName)->exists($testFile)) {
                $this->error("  ✗ Cannot write to {$this->diskName} disk");
                return false;
            }

            $content = Storage::disk($this->diskName)->get($testFile);
            if ($content !== 'test') {
                $this->error("  ✗ Cannot read from {$this->diskName} disk");
                return false;
            }

            Storage::disk($this->diskName)->delete($testFile);
            $this->line("  ✓ Storage disk '{$this->diskName}' accessible");

            return true;

        } catch (Exception $e) {
            $this->error("  ✗ Storage disk access failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Test database connections
     */
    private function testDatabaseConnections(): bool
    {
        try {
            // Test main database
            DB::connection()->getPdo();
            $this->line('  ✓ Main database connection OK');

            // Test payment gateway database
            DB::connection('payment_gateway_mysql')->getPdo();
            $this->line('  ✓ Payment gateway database connection OK');

            return true;
        } catch (Exception $e) {
            $this->error("  ✗ Database connection failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check available disk space
     */
    private function checkDiskSpace(): bool
    {
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);

        if ($freeBytes === false || $totalBytes === false) {
            $this->warn('  ! Could not check disk space');
            return true; // Don't fail if we can't check
        }

        $freePercentage = round(($freeBytes / $totalBytes) * 100, 1);

        if ($freePercentage < 10) {
            $this->error("  ✗ Low disk space: {$freePercentage}% free");
            return false;
        }

        $this->line("  ✓ Disk space OK: {$freePercentage}% free");
        return true;
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        $currentUsage = memory_get_usage(true);
        $currentUsageMB = round($currentUsage / 1024 / 1024, 2);

        $this->line("  ✓ Memory usage: {$currentUsageMB}MB (limit: {$memoryLimit})");
        return true;
    }

    /**
     * Get files to process based on options
     */
    private function getFilesToProcess(?string $fileId, int $limit, bool $retryFailed, bool $forceReprocess)
    {
        if ($fileId) {
            // Process specific file
            $file = $this->fileRepository->findById((int) $fileId);
            if ($file) {
                // Reset processing status for specific file if it's stuck or failed (or if forced)
                if ($forceReprocess || in_array($file->status, [DectaFile::STATUS_FAILED, DectaFile::STATUS_PROCESSING])) {
                    $file->update(['status' => DectaFile::STATUS_PENDING]);
                }

                // Fix file path if needed
                $this->fixFilePathIfNeeded($file);
            }
            return collect($file ? [$file] : []);
        }

        if ($retryFailed) {
            // Get failed files to retry, and also include stuck files
            $files = $this->fileRepository->getFilesByStatus(DectaFile::STATUS_FAILED);

            // Also include files stuck in processing for more than 2 hours
            $stuckFiles = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
                ->where('updated_at', '<', now()->subHours(2))
                ->get();

            foreach ($stuckFiles as $stuckFile) {
                $stuckFile->update(['status' => DectaFile::STATUS_PENDING]);
            }

            $files = $files->merge($stuckFiles);

            // Fix file paths for retry
            foreach ($files->take($limit) as $file) {
                $this->fixFilePathIfNeeded($file);
            }

            return $files->take($limit);
        }

        if ($forceReprocess) {
            // Get all files (including processed ones) for reprocessing
            $files = DectaFile::orderBy('created_at', 'desc')->take($limit)->get();

            foreach ($files as $file) {
                $file->update(['status' => DectaFile::STATUS_PENDING]);
                $this->fixFilePathIfNeeded($file);
            }

            return $files;
        }

        // Get pending files
        return $this->fileRepository->getPendingFiles()->take($limit);
    }

    /**
     * Fix file path if the file is in failed/processed directory but database has wrong path
     * Ensures failed files stay in failed directory and don't get moved to nested directories
     */
    private function fixFilePathIfNeeded(DectaFile $file): void
    {
        // Use the repository method to check if file exists
        if ($this->fileRepository->fileExistsInStorage($file)) {
            return; // File is where it should be
        }

        // Use the repository method to find the actual path
        $actualPath = $this->fileRepository->findActualFilePath($file);

        if ($actualPath) {
            // Check if this is a failed file that should stay in failed directory
            $isCurrentlyFailed = $this->isFileInFailedDirectory($file->local_path);
            $isActuallyFailed = $this->isFileInFailedDirectory($actualPath);

            // If file is supposed to be failed but found in normal directory, keep it failed
            if ($isCurrentlyFailed && !$isActuallyFailed && $file->status === DectaFile::STATUS_FAILED) {
                $this->line("  Failed file found in normal directory, but keeping failed status");
                Log::info('Failed file found outside failed directory', [
                    'file_id' => $file->id,
                    'expected_failed_path' => $file->local_path,
                    'found_at' => $actualPath,
                    'action' => 'keeping_failed_status'
                ]);
                return;
            }

            $this->line("  Found file at different location, updating path...");
            $file->update(['local_path' => $actualPath]);

            Log::info('Fixed file path for processing', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $actualPath,
                'was_in_failed_dir' => $isCurrentlyFailed,
                'now_in_failed_dir' => $isActuallyFailed,
            ]);
        } else {
            $this->warn("  File not found in any expected location for {$file->filename}");

            // For failed files, check if we should create a specific warning
            if ($file->status === DectaFile::STATUS_FAILED) {
                $this->warn("  This is a failed file - it may have been manually removed");
            }

            // Log all possible paths that were checked
            Log::warning('File not found during path fixing', [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'current_path' => $file->local_path,
                'file_status' => $file->status,
                'is_failed_file' => $file->status === DectaFile::STATUS_FAILED,
            ]);
        }
    }

    /**
     * Process a transaction file
     */
    private function processTransactionFile(DectaFile $file): array
    {
        // Get file content using the repository (which handles path resolution)
        $content = $this->fileRepository->getFileContent($file);
        if (!$content) {
            throw new Exception('Could not read file content from: ' . $file->local_path);
        }

        // Check if it's a CSV file
        if ($file->file_type !== 'csv') {
            throw new Exception("Unsupported file type: {$file->file_type}. Only CSV files are supported.");
        }

        // Get detailed progress information
        $progress = $this->transactionService->getFileProgress($file);

        Log::info('File processing progress check', [
            'file_id' => $file->id,
            'progress' => $progress,
        ]);

        // Check for issues before processing
        if (!empty($progress['issues'])) {
            foreach ($progress['issues'] as $issue) {
                $this->warn("  Issue detected: {$issue}");
            }
        }

        $existingTransactions = $progress['processed_rows'];
        $totalRows = $progress['total_rows'];

        if ($totalRows === 0) {
            throw new Exception('CSV file appears to have no data rows');
        }

        if ($existingTransactions >= $totalRows) {
            $this->info("  File processing appears complete ({$existingTransactions}/{$totalRows} rows)");

            // Verify completion by checking for any missing data
            if ($this->shouldReprocessFile($file, $progress)) {
                $this->line("  Detected data quality issues, reprocessing...");
                return $this->reprocessFile($file, $content);
            }

            return [
                'processed' => $existingTransactions,
                'failed' => 0,
                'total_rows' => $totalRows,
                'status' => 'already_complete'
            ];
        }

        if ($existingTransactions > 0) {
            $this->line("  Resuming from transaction " . ($existingTransactions + 1) . " of {$totalRows}");

            // Show sample of what's already processed
            if (!empty($progress['sample_processed_payment_ids'])) {
                $this->line("  Already processed (sample): " . implode(', ', array_slice($progress['sample_processed_payment_ids'], 0, 3)));
            }

            // Resume processing from where we left off
            return $this->transactionService->processCsvFileWithResume($file, $content, $existingTransactions);
        }

        // Fresh processing
        $this->line("  Starting fresh processing of {$totalRows} rows");
        return $this->transactionService->processCsvFile($file, $content);
    }

    /**
     * Move file to processed directory and update database
     */
    private function moveFileToProcessed(DectaFile $file): void
    {
        try {
            if ($this->sftpService->moveToProcessed($file->local_path)) {
                Log::info('File moved to processed directory', [
                    'file_id' => $file->id,
                    'original_path' => $file->local_path,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to move file to processed directory', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the entire process if file move fails
        }
    }

    /**
     * Move file to failed directory and update database (only if not already in failed directory)
     */
    private function moveFileToFailed(DectaFile $file): void
    {
        try {
            // Check if file is already in failed directory
            if ($this->isFileInFailedDirectory($file->local_path)) {
                Log::info('File already in failed directory, not moving again', [
                    'file_id' => $file->id,
                    'current_path' => $file->local_path,
                ]);
                return;
            }

            // Calculate the target failed path
            $targetFailedPath = $this->calculateFailedPath($file->local_path);

            // Move file to failed directory
            if ($this->sftpService->moveToFailed($file->local_path)) {
                // Update database with new path
                $file->update(['local_path' => $targetFailedPath]);

                Log::info('File moved to failed directory', [
                    'file_id' => $file->id,
                    'original_path' => $file->local_path,
                    'new_path' => $targetFailedPath,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to move file to failed directory', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the entire process if file move fails
        }
    }

    /**
     * Check if file is already in failed directory
     */
    private function isFileInFailedDirectory(string $filePath): bool
    {
        $failedDir = config('decta.files.failed_dir', 'failed');
        $pathParts = explode('/', $filePath);

        // Check if 'failed' is in the path (but not as the filename)
        $directoryParts = array_slice($pathParts, 0, -1); // Remove filename
        return in_array($failedDir, $directoryParts);
    }

    /**
     * Calculate the correct failed path without nesting
     */
    private function calculateFailedPath(string $originalPath): string
    {
        $filename = basename($originalPath);
        $directory = dirname($originalPath);
        $failedDir = config('decta.files.failed_dir', 'failed');

        // Get the base directory (removing any existing failed/processed subdirs)
        $baseDirectory = $this->getCleanBaseDirectory($directory);

        return $baseDirectory . '/' . $failedDir . '/' . $filename;
    }

    /**
     * Get clean base directory without failed/processed subdirectories
     */
    private function getCleanBaseDirectory(string $directory): string
    {
        $failedDir = config('decta.files.failed_dir', 'failed');
        $processedDir = config('decta.files.processed_dir', 'processed');

        // Remove trailing /failed or /processed from the directory
        $cleanDirectory = preg_replace('/\/' . preg_quote($failedDir, '/') . '$/', '', $directory);
        $cleanDirectory = preg_replace('/\/' . preg_quote($processedDir, '/') . '$/', '', $cleanDirectory);

        return $cleanDirectory;
    }

    /**
     * Display processing summary
     */
    private function displayProcessingSummary(
        int $processedCount,
        int $skippedCount,
        int $failedCount,
        int $totalMatched,
        int $totalUnmatched,
        bool $skipMatching
    ): void {
        $this->info("Processing completed:");
        $this->info(" - Files processed: {$processedCount}");
        $this->info(" - Files skipped (already processed): {$skippedCount}");
        $this->info(" - Files failed: {$failedCount}");

        if (!$skipMatching) {
            $this->info(" - Total transactions matched: {$totalMatched}");
            $this->info(" - Total transactions unmatched: {$totalUnmatched}");

            if ($totalMatched + $totalUnmatched > 0) {
                $matchRate = round(($totalMatched / ($totalMatched + $totalUnmatched)) * 100, 2);
                $this->info(" - Match rate: {$matchRate}%");
            }
        }

        if ($processedCount > 0 || $skippedCount > 0) {
            $this->info("\nNext steps:");

            if ($skipMatching && ($processedCount > 0 || $skippedCount > 0)) {
                $this->info("- Run without --skip-matching to match transactions with payment gateway");
            }

            if ($totalUnmatched > 0) {
                $this->info("- Check unmatched transactions and consider running matching again");
                $this->info("- Review error logs for matching failures");
            }
        }

        if ($failedCount > 0) {
            $this->warn("\nSome files failed to process. Check logs for details.");
            $this->info("Use --retry-failed option to retry failed files.");
        }

        if ($skippedCount > 0) {
            $this->info("Use --force-reprocess to reprocess already completed files.");
        }
    }

    /**
     * Enhanced error handling for processing failures
     */
    private function handleProcessingError(DectaFile $file, Exception $e): void
    {
        // Get current progress to understand what was processed
        $progress = $this->transactionService->getFileProgress($file);
        $transactionCount = $progress['processed_rows'];

        Log::error('Processing error details', [
            'file_id' => $file->id,
            'error' => $e->getMessage(),
            'progress' => $progress,
            'trace' => $e->getTraceAsString(),
        ]);

        if ($transactionCount > 0) {
            // Partial processing - leave as processing so it can be resumed
            $errorMessage = "Processing interrupted after {$transactionCount} transactions: {$e->getMessage()}";

            $file->update([
                'status' => DectaFile::STATUS_PROCESSING,
                'error_message' => $errorMessage,
                'updated_at' => now()
            ]);

            $this->warn("  File partially processed ({$transactionCount} transactions). Can be resumed later.");

            // Show what percentage was completed
            if ($progress['total_rows'] > 0) {
                $percentage = round(($transactionCount / $progress['total_rows']) * 100, 1);
                $this->line("  Progress: {$percentage}% complete ({$transactionCount}/{$progress['total_rows']} rows)");
            }

        } else {
            // Complete failure - mark as failed and move to failed directory (if not already there)
            $file->markAsFailed($e->getMessage());

            // Only move to failed directory if not already there
            if (!$this->isFileInFailedDirectory($file->local_path)) {
                $this->moveFileToFailed($file);
                $this->error("  File processing completely failed - moved to failed directory.");
            } else {
                $this->error("  File processing failed again - keeping in failed directory.");

                // Update the failure message to include retry attempt info
                $retryCount = $this->getRetryCount($file);
                $file->update([
                    'error_message' => "Retry attempt #{$retryCount} failed: {$e->getMessage()}",
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Get the number of retry attempts for a file
     */
    private function getRetryCount(DectaFile $file): int
    {
        // Count how many times this file has been marked as failed
        // This is a simple approach - you could make this more sophisticated
        $errorMessage = $file->error_message ?? '';

        if (preg_match('/Retry attempt #(\d+)/', $errorMessage, $matches)) {
            return (int)$matches[1] + 1;
        }

        // If it's already failed before, this is at least retry #2
        return $file->status === DectaFile::STATUS_FAILED ? 2 : 1;
    }

    /**
     * Check if file should be reprocessed due to data quality issues
     */
    private function shouldReprocessFile(DectaFile $file, array $progress): bool
    {
        // Check if there are more transactions than CSV rows (indicates duplicates)
        if ($progress['processed_rows'] > $progress['total_rows']) {
            Log::warning('File has more database transactions than CSV rows', [
                'file_id' => $file->id,
                'db_count' => $progress['processed_rows'],
                'csv_count' => $progress['total_rows'],
            ]);
            return true;
        }

        // Check for missing payment IDs or other data quality issues
        $transactionsWithoutPaymentId = $file->dectaTransactions()
            ->whereNull('payment_id')
            ->orWhere('payment_id', '')
            ->count();

        if ($transactionsWithoutPaymentId > 0) {
            Log::warning('File has transactions without payment IDs', [
                'file_id' => $file->id,
                'count' => $transactionsWithoutPaymentId,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Reprocess file by cleaning up existing data and starting fresh
     */
    private function reprocessFile(DectaFile $file, string $content): array
    {
        Log::info('Reprocessing file due to data quality issues', [
            'file_id' => $file->id,
            'filename' => $file->filename,
        ]);

        // Delete existing transactions for this file
        $deletedCount = $file->dectaTransactions()->delete();

        Log::info('Deleted existing transactions for reprocessing', [
            'file_id' => $file->id,
            'deleted_count' => $deletedCount,
        ]);

        $this->line("  Deleted {$deletedCount} existing transactions for clean reprocessing");

        // Process fresh
        return $this->transactionService->processCsvFile($file, $content);
    }
}
