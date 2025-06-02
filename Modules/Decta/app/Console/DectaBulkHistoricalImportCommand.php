<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaNotificationService;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DectaBulkHistoricalImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:bulk-historical-import
                            {--date-from= : Start date for historical import (YYYY-MM-DD)}
                            {--date-to= : End date for historical import (YYYY-MM-DD)}
                            {--download-only : Only download files, do not process}
                            {--process-only : Only process already downloaded files}
                            {--force : Force download even if files already exist}
                            {--skip-matching : Skip transaction matching during processing}
                            {--batch-size=10 : Number of files to process in each batch}
                            {--dry-run : Show what would be downloaded/processed without doing it}
                            {--no-email : Disable email notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk download and process historical Decta transaction files (one-time operation)';

    /**
     * Services
     */
    protected $sftpService;
    protected $fileRepository;
    protected $transactionService;
    protected $notificationService;
    protected $diskName = 'decta';

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaSftpService $sftpService,
        DectaFileRepository $fileRepository,
        DectaTransactionService $transactionService,
        DectaNotificationService $notificationService
    ) {
        parent::__construct();
        $this->sftpService = $sftpService;
        $this->fileRepository = $fileRepository;
        $this->transactionService = $transactionService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Decta Bulk Historical Import Process');
        $this->info('=' . str_repeat('=', 60));

        $startTime = now();

        try {
            // Validate options and get date range
            $dateRange = $this->validateAndGetDateRange();
            if (!$dateRange) {
                return 1;
            }

            $downloadOnly = $this->option('download-only');
            $processOnly = $this->option('process-only');
            $force = $this->option('force');
            $skipMatching = $this->option('skip-matching');
            $batchSize = (int) $this->option('batch-size');
            $dryRun = $this->option('dry-run');

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
            }

            // Check system health before starting
            if (!$dryRun && !$this->checkSystemHealth()) {
                $this->error('❌ System health check failed. Aborting to prevent issues.');
                return 1;
            }

            $results = [
                'total_files_found' => 0,
                'downloaded' => 0,
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'total_transactions' => 0,
                'total_matched' => 0,
                'total_unmatched' => 0,
                'errors' => [],
                'date_range' => [
                    'from' => $dateRange['from']->toDateString(),
                    'to' => $dateRange['to']->toDateString()
                ]
            ];

            // Step 1: Find historical files
            if (!$processOnly) {
                $this->info("\n📁 Step 1: Finding historical files...");
                $historicalFiles = $this->findHistoricalFiles($dateRange['from'], $dateRange['to']);

                if (empty($historicalFiles)) {
                    $this->warn('No historical files found in the specified date range.');
                    return 0;
                }

                $results['total_files_found'] = count($historicalFiles);
                $this->info("Found {$results['total_files_found']} historical files");

                // Step 2: Download files
                $this->info("\n⬇️ Step 2: Downloading files...");
                $downloadResults = $this->downloadHistoricalFiles($historicalFiles, $force, $dryRun);
                $results = array_merge($results, $downloadResults);
            }

            // Step 3: Process files (if not download-only)
            if (!$downloadOnly) {
                $this->info("\n⚙️ Step 3: Processing downloaded files...");
                $processResults = $this->processHistoricalFiles($dateRange, $skipMatching, $batchSize, $dryRun);
                $results = array_merge($results, $processResults);
            }

            // Display final summary
            $this->displayFinalSummary($results, $startTime);

            // Send notification if not disabled
            if (!$this->option('no-email') && !$dryRun) {
                $this->sendCompletionNotification($results, empty($results['errors']));
            }

            return empty($results['errors']) ? 0 : 1;

        } catch (Exception $e) {
            $this->error("❌ Bulk import failed: {$e->getMessage()}");
            Log::error('Bulk historical import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Validate options and get date range
     */
    private function validateAndGetDateRange(): ?array
    {
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');

        // If no dates provided, try to auto-detect from visible files
        if (!$dateFrom || !$dateTo) {
            $this->info('No date range specified. Based on your file list, suggesting:');
            $this->info('  --date-from=2025-04-20 --date-to=2025-06-02');

            if (!$this->confirm('Do you want to use this date range?')) {
                $this->error('Please specify --date-from and --date-to options');
                return null;
            }

            $dateFrom = '2025-04-20';
            $dateTo = '2025-06-02';
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $dateFrom);
            $toDate = Carbon::createFromFormat('Y-m-d', $dateTo);

            if ($fromDate > $toDate) {
                $this->error('Start date must be before or equal to end date');
                return null;
            }

            $daysDiff = $fromDate->diffInDays($toDate);
            if ($daysDiff > 365) {
                $this->warn("Large date range: {$daysDiff} days. This may take a long time.");
                if (!$this->confirm('Continue?')) {
                    return null;
                }
            }

            return ['from' => $fromDate, 'to' => $toDate];

        } catch (Exception $e) {
            $this->error('Invalid date format. Use YYYY-MM-DD format.');
            return null;
        }
    }

    /**
     * Check system health before starting bulk operations
     */
    private function checkSystemHealth(): bool
    {
        $this->info('🔍 Checking system health...');

        try {
            // Check database connections
            DB::connection()->getPdo();
            DB::connection('payment_gateway_mysql')->getPdo();
            $this->line('  ✅ Database connections OK');

            // Check SFTP connection
            if (!$this->sftpService->testConnection()) {
                $this->error('  ❌ SFTP connection failed');
                return false;
            }
            $this->line('  ✅ SFTP connection OK');

            // Check disk space
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);

            if ($freeBytes && $totalBytes) {
                $freePercentage = round(($freeBytes / $totalBytes) * 100, 1);
                if ($freePercentage < 15) {
                    $this->error("  ❌ Low disk space: {$freePercentage}% free");
                    return false;
                }
                $this->line("  ✅ Disk space OK: {$freePercentage}% free");
            }

            // Check memory limit for bulk operations
            $memoryLimit = ini_get('memory_limit');
            $this->line("  ✅ Memory limit: {$memoryLimit}");

            return true;

        } catch (Exception $e) {
            $this->error("  ❌ System health check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Find historical files in the date range
     */
    private function findHistoricalFiles(Carbon $fromDate, Carbon $toDate): array
    {
        try {
            $this->info("Searching SFTP for files from {$fromDate->toDateString()} to {$toDate->toDateString()}...");

            // Get all files from SFTP
            $allFiles = $this->sftpService->listFiles('in_file/reports');

            if (empty($allFiles)) {
                $this->warn('No files found on SFTP server');
                return [];
            }

            $historicalFiles = [];
            $currentDate = $fromDate->copy();

            // Generate expected file patterns for each date in range
            while ($currentDate <= $toDate) {
                $dateString = $currentDate->format('Ymd');

                $patterns = [
                    "/^INTCL_transact2_{$dateString}\.csv$/",
                    "/^INTCL_transact_{$dateString}\.csv$/",
                    "/^transact2_{$dateString}\.csv$/",
                    "/^transact_{$dateString}\.csv$/",
                ];

                foreach ($allFiles as $file) {
                    $filename = basename($file['path']);

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $filename)) {
                            $file['file_date'] = $dateString;
                            $file['parsed_date'] = $currentDate->copy();
                            $file['pattern_matched'] = $pattern;
                            $historicalFiles[] = $file;

                            $this->line("  📄 Found: {$filename} ({$this->formatBytes($file['fileSize'])})");
                            break 2; // Break both loops for this date
                        }
                    }
                }

                $currentDate->addDay();
            }

            // Sort files by date
            usort($historicalFiles, function($a, $b) {
                return $a['parsed_date']->timestamp <=> $b['parsed_date']->timestamp;
            });

            $this->info("Total files found: " . count($historicalFiles));
            return $historicalFiles;

        } catch (Exception $e) {
            $this->error("Error finding historical files: {$e->getMessage()}");
            Log::error('Error finding historical files', [
                'error' => $e->getMessage(),
                'date_range' => [$fromDate->toDateString(), $toDate->toDateString()]
            ]);
            return [];
        }
    }

    /**
     * Download historical files
     */
    private function downloadHistoricalFiles(array $files, bool $force, bool $dryRun): array
    {
        $results = [
            'downloaded' => 0,
            'skipped' => 0,
            'download_errors' => 0,
            'errors' => []
        ];

        if (empty($files)) {
            return $results;
        }

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            $filename = basename($file['path']);

            try {
                // Check if file already exists
                if (!$force && $this->fileRepository->existsByFilename($filename)) {
                    $existing = $this->fileRepository->findByFilename($filename);
                    if ($existing->status === DectaFile::STATUS_PROCESSED) {
                        $this->line("  ⏭️ Skipping {$filename} (already processed)");
                        $results['skipped']++;
                        $progressBar->advance();
                        continue;
                    }
                }

                if ($dryRun) {
                    $this->line("  🔍 Would download: {$filename}");
                    $results['downloaded']++;
                    $progressBar->advance();
                    continue;
                }

                // Organize files by date
                $dateFolder = $file['parsed_date']->format('Y/m/d');
                $localPath = config('decta.sftp.local_path') . "/{$dateFolder}/" . $filename;

                // Download the file
                $success = $this->sftpService->downloadFile($file['path'], $localPath);

                if ($success) {
                    // Verify and create database record
                    $fileSize = Storage::disk($this->diskName)->size($localPath);
                    $fileType = pathinfo($filename, PATHINFO_EXTENSION);

                    $this->fileRepository->create([
                        'filename' => $filename,
                        'original_path' => $file['path'],
                        'local_path' => $localPath,
                        'file_size' => $fileSize,
                        'file_type' => $fileType,
                        'status' => DectaFile::STATUS_PENDING,
                        'metadata' => [
                            'download_date' => Carbon::now()->toISOString(),
                            'target_date' => $file['parsed_date']->toDateString(),
                            'file_date' => $file['file_date'],
                            'bulk_import' => true,
                            'disk_used' => $this->diskName,
                        ],
                    ]);

                    $results['downloaded']++;
                    $this->line("  ✅ Downloaded: {$filename} ({$this->formatBytes($fileSize)})");

                } else {
                    $results['download_errors']++;
                    $error = "Failed to download: {$filename}";
                    $results['errors'][] = $error;
                    $this->line("  ❌ {$error}");
                }

            } catch (Exception $e) {
                $results['download_errors']++;
                $error = "Error downloading {$filename}: {$e->getMessage()}";
                $results['errors'][] = $error;
                $this->line("  ❌ {$error}");

                Log::error('Error downloading historical file', [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Download Summary:");
        $this->info("  ✅ Downloaded: {$results['downloaded']}");
        $this->info("  ⏭️ Skipped: {$results['skipped']}");
        $this->info("  ❌ Errors: {$results['download_errors']}");

        return $results;
    }

    /**
     * Process historical files in batches
     */
    private function processHistoricalFiles(array $dateRange, bool $skipMatching, int $batchSize, bool $dryRun): array
    {
        $results = [
            'processed' => 0,
            'process_errors' => 0,
            'total_transactions' => 0,
            'total_matched' => 0,
            'total_unmatched' => 0,
        ];

        // Get pending files in the date range
        $pendingFiles = DectaFile::where('status', DectaFile::STATUS_PENDING)
            ->where(function($query) use ($dateRange) {
                // Filter by bulk import flag or date range in metadata
                $query->whereJsonContains('metadata->bulk_import', true)
                    ->orWhereBetween('created_at', [
                        $dateRange['from']->startOfDay(),
                        $dateRange['to']->endOfDay()
                    ]);
            })
            ->orderBy('created_at')
            ->get();

        if ($pendingFiles->isEmpty()) {
            $this->info('No pending files found to process');
            return $results;
        }

        $this->info("Found {$pendingFiles->count()} files to process");

        // Process in batches
        $batches = $pendingFiles->chunk($batchSize);
        $batchNumber = 1;

        foreach ($batches as $batch) {
            $this->info("Processing batch {$batchNumber}/" . $batches->count() . " ({$batch->count()} files)");

            if ($dryRun) {
                $this->info("  🔍 Would process batch {$batchNumber}");
                foreach ($batch as $file) {
                    $this->line("    - {$file->filename}");
                }
                $results['processed'] += $batch->count();
                $batchNumber++;
                continue;
            }

            foreach ($batch as $file) {
                try {
                    $this->line("  📊 Processing: {$file->filename}");

                    // Mark as processing
                    $file->markAsProcessing();

                    // Process CSV content
                    $content = $this->fileRepository->getFileContent($file);
                    if (!$content) {
                        throw new Exception('Could not read file content');
                    }

                    $processingResults = $this->transactionService->processCsvFile($file, $content);

                    if ($processingResults['processed'] > 0) {
                        $results['total_transactions'] += $processingResults['processed'];

                        // Handle matching if not skipped
                        if (!$skipMatching) {
                            $matchingResults = $this->transactionService->matchTransactions($file->id);
                            $results['total_matched'] += $matchingResults['matched'];
                            $results['total_unmatched'] += $matchingResults['failed'];
                        }

                        // Mark as processed and move file
                        $file->markAsProcessed();
                        $this->sftpService->moveToProcessed($file->local_path);

                        $results['processed']++;

                        $this->line("    ✅ Success: {$processingResults['processed']} transactions");
                        if (!$skipMatching) {
                            $this->line("    🔗 Matched: {$matchingResults['matched']}, Unmatched: {$matchingResults['failed']}");
                        }

                    } else {
                        throw new Exception('No transactions were processed');
                    }

                } catch (Exception $e) {
                    $results['process_errors']++;
                    $error = "Failed to process {$file->filename}: {$e->getMessage()}";
                    $results['errors'][] = $error;

                    $file->markAsFailed($e->getMessage());
                    $this->sftpService->moveToFailed($file->local_path);

                    $this->line("    ❌ {$error}");

                    Log::error('Error processing historical file', [
                        'file_id' => $file->id,
                        'filename' => $file->filename,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Small pause between batches to prevent overwhelming the system
            if ($batchNumber < $batches->count()) {
                $this->info("  ⏸️ Brief pause before next batch...");
                sleep(2);
            }

            $batchNumber++;
        }

        $this->info("Processing Summary:");
        $this->info("  ✅ Processed: {$results['processed']}");
        $this->info("  📊 Total Transactions: {$results['total_transactions']}");
        if (!$skipMatching) {
            $this->info("  🔗 Matched: {$results['total_matched']}");
            $this->info("  ❓ Unmatched: {$results['total_unmatched']}");
        }
        $this->info("  ❌ Errors: {$results['process_errors']}");

        return $results;
    }

    /**
     * Display final summary
     */
    private function displayFinalSummary(array $results, Carbon $startTime): void
    {
        $duration = $startTime->diffInMinutes(now());

        $this->newLine();
        $this->info('🎉 Bulk Historical Import Completed!');
        $this->info('=' . str_repeat('=', 60));

        $this->table(['Metric', 'Count'], [
            ['Files Found', $results['total_files_found'] ?? 0],
            ['Files Downloaded', $results['downloaded'] ?? 0],
            ['Files Processed', $results['processed'] ?? 0],
            ['Files Skipped', $results['skipped'] ?? 0],
            ['Files Failed', $results['failed'] ?? 0],
            ['Total Transactions', $results['total_transactions'] ?? 0],
            ['Transactions Matched', $results['total_matched'] ?? 0],
            ['Transactions Unmatched', $results['total_unmatched'] ?? 0],
            ['Duration (minutes)', $duration],
        ]);

        if (!empty($results['errors'])) {
            $this->warn("\n⚠️ Errors encountered:");
            foreach (array_slice($results['errors'], 0, 10) as $error) {
                $this->line("  • {$error}");
            }
            if (count($results['errors']) > 10) {
                $this->line("  • ... and " . (count($results['errors']) - 10) . " more errors");
            }
        }

        $this->info("\n📋 Next Steps:");
        $this->info("  • Historical import is complete");
        $this->info("  • Daily automation will handle new files going forward");
        $this->info("  • Check logs for any issues: storage/logs/laravel.log");
        $this->info("  • Review unmatched transactions if needed");
    }

    /**
     * Send completion notification
     */
    private function sendCompletionNotification(array $results, bool $success): void
    {
        try {
            $this->notificationService->sendProcessingNotification([
                'type' => 'bulk_historical_import',
                'processed' => $results['processed'] ?? 0,
                'total_files' => $results['total_files_found'] ?? 0,
                'total_transactions' => $results['total_transactions'] ?? 0,
                'total_matched' => $results['total_matched'] ?? 0,
                'total_unmatched' => $results['total_unmatched'] ?? 0,
                'date_range' => $results['date_range'] ?? [],
                'errors' => $results['errors'] ?? [],
                'duration' => now()->diffInMinutes($this->startTime ?? now()),
            ], $success);

            $this->line('📧 Completion notification sent');
        } catch (Exception $e) {
            $this->warn("Failed to send notification: {$e->getMessage()}");
        }
    }

    /**
     * Format bytes to human readable format
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
}
