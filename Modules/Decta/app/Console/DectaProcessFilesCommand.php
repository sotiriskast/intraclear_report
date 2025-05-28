<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
                            {--retry-failed : Retry processing failed files}';

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

            // Get files to process
            $files = $this->getFilesToProcess($fileId, $limit, $retryFailed);

            if ($files->isEmpty()) {
                $this->info('No files to process.');
                return 0;
            }

            $this->info(sprintf('Found %d file(s) to process.', $files->count()));

            $processedCount = 0;
            $failedCount = 0;
            $totalMatched = 0;
            $totalUnmatched = 0;

            $progressBar = $this->output->createProgressBar($files->count());
            $progressBar->start();

            foreach ($files as $file) {
                try {
                    $this->line(" Processing: {$file->filename}");

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
                            $this->line("  Matching transactions with payment gateway...");
                            try {
                                $matchingResults = $this->transactionService->matchTransactions($file->id);
                            } catch (Exception $e) {
                                $this->warn("  Warning: Matching failed but file processing succeeded: {$e->getMessage()}");
                                // Don't fail the entire process if matching fails
                            }
                        }

                        // Mark file as processed and move to processed directory
                        $file->markAsProcessed();
                        $this->moveFileToProcessed($file);

                        $processedCount++;
                        $totalMatched += $matchingResults['matched'];
                        $totalUnmatched += $matchingResults['failed'];

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
            $this->displayProcessingSummary($processedCount, $failedCount, $totalMatched, $totalUnmatched, $skipMatching);

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

            $this->info('System health check passed.');
            return true;

        } catch (Exception $e) {
            $this->error("System health check failed: {$e->getMessage()}");
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

        $freePercentage = ($freeBytes / $totalBytes) * 100;

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
    private function getFilesToProcess(?string $fileId, int $limit, bool $retryFailed)
    {
        if ($fileId) {
            // Process specific file
            $file = $this->fileRepository->findById((int) $fileId);
            if ($file && ($file->status === DectaFile::STATUS_FAILED || $file->status === DectaFile::STATUS_PROCESSING)) {
                // Reset processing status for specific file if it's stuck or failed
                $file->update(['status' => DectaFile::STATUS_PENDING]);

                // Fix file path if it's in failed directory
                $this->fixFilePathIfNeeded($file);
            }
            return collect($file ? [$file] : []);
        }

        if ($retryFailed) {
            // Get failed files to retry, but also include stuck files
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

        // Get pending files
        return $this->fileRepository->getPendingFiles()->take($limit);
    }

    /**
     * Fix file path if the file is in failed/processed directory but database has wrong path
     */
    private function fixFilePathIfNeeded(DectaFile $file): void
    {
        // Check if file exists at current path
        if ($this->fileRepository->fileExistsInStorage($file)) {
            return; // File is where it should be
        }

        // Try to find file in failed directory
        $failedPath = $this->getFailedPath($file->local_path);
        $processedPath = $this->getProcessedPath($file->local_path);

        if (\Storage::disk('decta')->exists($failedPath)) {
            $this->line("  Found file in failed directory, updating path...");
            $file->update(['local_path' => $failedPath]);
            Log::info('Fixed file path for retry', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $failedPath
            ]);
        } elseif (\Storage::disk('decta')->exists($processedPath)) {
            $this->line("  Found file in processed directory, updating path...");
            $file->update(['local_path' => $processedPath]);
            Log::info('Fixed file path for retry', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $processedPath
            ]);
        } else {
            $this->warn("  File not found in any expected location for {$file->filename}");
        }
    }

    /**
     * Get the failed directory path for a file
     */
    private function getFailedPath(string $originalPath): string
    {
        $filename = basename($originalPath);
        $directory = dirname($originalPath);
        $failedDir = config('decta.files.failed_dir', 'failed');
        return $directory . '/' . $failedDir . '/' . $filename;
    }

    /**
     * Get the processed directory path for a file
     */
    private function getProcessedPath(string $originalPath): string
    {
        $filename = basename($originalPath);
        $directory = dirname($originalPath);
        $processedDir = config('decta.files.processed_dir', 'processed');
        return $directory . '/' . $processedDir . '/' . $filename;
    }

    /**
     * Process a transaction file
     */
    private function processTransactionFile(DectaFile $file): array
    {
        // Get file content
        $content = $this->fileRepository->getFileContent($file);
        if (!$content) {
            throw new Exception('Could not read file content from: ' . $file->local_path);
        }

        // Check if it's a CSV file
        if ($file->file_type !== 'csv') {
            throw new Exception("Unsupported file type: {$file->file_type}. Only CSV files are supported.");
        }

        // Check if processing was already started
        $existingTransactions = $file->dectaTransactions()->count();
        $totalRows = $this->transactionService->countCsvRows($content);

        if ($existingTransactions > 0) {
            $this->line("  Found {$existingTransactions} existing transactions out of {$totalRows} total rows");

            if ($existingTransactions >= $totalRows) {
                $this->info("  File appears to be completely processed");
                return ['processed' => $existingTransactions, 'failed' => 0];
            }

            $this->line("  Resuming from row " . ($existingTransactions + 1));

            // Resume processing from where we left off
            return $this->transactionService->processCsvFileWithResume($file, $content, $existingTransactions);
        }

        // Fresh processing
        return $this->transactionService->processCsvFile($file, $content);
    }

    /**
     * Move file to processed directory and update database
     */
    private function moveFileToProcessed(DectaFile $file): void
    {
        $newPath = $this->getProcessedPath($file->local_path);

        if ($this->sftpService->moveToProcessed($file->local_path)) {
            // Update database with new path
            $file->update(['local_path' => $newPath]);

            Log::info('File moved to processed and database updated', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $newPath
            ]);
        }
    }

    /**
     * Move file to failed directory and update database
     */
    private function moveFileToFailed(DectaFile $file): void
    {
        $newPath = $this->getFailedPath($file->local_path);

        if ($this->sftpService->moveToFailed($file->local_path)) {
            // Update database with new path
            $file->update(['local_path' => $newPath]);

            Log::info('File moved to failed and database updated', [
                'file_id' => $file->id,
                'old_path' => $file->local_path,
                'new_path' => $newPath
            ]);
        }
    }

    /**
     * Display processing summary
     */
    private function displayProcessingSummary(
        int $processedCount,
        int $failedCount,
        int $totalMatched,
        int $totalUnmatched,
        bool $skipMatching
    ): void {
        $this->info("Processing completed:");
        $this->info(" - Files processed: {$processedCount}");
        $this->info(" - Files failed: {$failedCount}");

        if (!$skipMatching) {
            $this->info(" - Total transactions matched: {$totalMatched}");
            $this->info(" - Total transactions unmatched: {$totalUnmatched}");

            if ($totalMatched + $totalUnmatched > 0) {
                $matchRate = round(($totalMatched / ($totalMatched + $totalUnmatched)) * 100, 2);
                $this->info(" - Match rate: {$matchRate}%");
            }
        }

        if ($processedCount > 0) {
            $this->info("\nNext steps:");

            if ($skipMatching) {
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
    }

    /**
     * Enhanced error handling for processing failures
     */
    private function handleProcessingError(DectaFile $file, Exception $e): void
    {
        // Check if this was a partial processing (some transactions were created)
        $transactionCount = $file->dectaTransactions()->count();

        if ($transactionCount > 0) {
            // Partial processing - don't mark as completely failed, leave as processing
            // so it can be resumed later
            $file->update([
                'status' => DectaFile::STATUS_PROCESSING,
                'error_message' => "Partial processing interrupted: {$e->getMessage()}. {$transactionCount} transactions were processed.",
                'updated_at' => now()
            ]);

            $this->warn("  File partially processed ({$transactionCount} transactions). Can be resumed later.");
        } else {
            // Complete failure - mark as failed and move to failed directory
            $file->markAsFailed($e->getMessage());
            $this->moveFileToFailed($file); // This now updates the database path too
        }
    }
}
