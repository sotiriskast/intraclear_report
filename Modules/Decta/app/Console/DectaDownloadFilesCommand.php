<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaNotificationService;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Models\DectaFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DectaDownloadFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:download-files
                            {--date= : Specific date to download (YYYY-MM-DD format, defaults to yesterday)}
                            {--force : Force download even if files were already processed}
                            {--days-back=7 : Number of days to look back for files}
                            {--debug : Enable debug output}
                            {--no-email : Disable email notifications for this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download transaction files from Decta SFTP server (defaults to yesterday\'s files)';

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaNotificationService
     */
    protected $notificationService;

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
        DectaSftpService $sftpService,
        DectaFileRepository $fileRepository,
        DectaNotificationService $notificationService
    ) {
        parent::__construct();
        $this->sftpService = $sftpService;
        $this->fileRepository = $fileRepository;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta transaction file download process...');

        $startTime = now();
        $downloadedFiles = [];
        $errorMessages = [];
        $targetDate = null;

        try {
            // Check if server is running and accessible
            if (!$this->checkServerStatus()) {
                $error = 'Server connection failed. Aborting download to prevent incomplete imports.';
                $this->error($error);

                $this->sendNotificationIfEnabled([
                    'downloaded' => 0,
                    'skipped' => 0,
                    'errors' => 1,
                    'error_messages' => [$error],
                    'duration' => $startTime->diffInMinutes(now()),
                ], false);

                return 1;
            }

            $date = $this->option('date');
            $force = $this->option('force') ?? false;
            $daysBack = (int) $this->option('days-back');
            $debug = $this->option('debug') ?? false;

            // Default to yesterday if no date specified
            if (!$date) {
                $targetDate = Carbon::yesterday();
                $this->info("No date specified, using yesterday: {$targetDate->toDateString()}");
            } else {
                try {
                    $targetDate = Carbon::createFromFormat('Y-m-d', $date);
                } catch (Exception $e) {
                    $error = "Invalid date format. Use YYYY-MM-DD format.";
                    $this->error($error);

                    $this->sendNotificationIfEnabled([
                        'downloaded' => 0,
                        'skipped' => 0,
                        'errors' => 1,
                        'error_messages' => [$error],
                        'target_date' => $date,
                        'duration' => $startTime->diffInMinutes(now()),
                    ], false);

                    return 1;
                }
            }

            // Find files for the target date
            $filesToDownload = $this->findFilesForDate($targetDate, $daysBack);

            if (empty($filesToDownload)) {
                $this->warn("No transaction files found for {$targetDate->toDateString()}");

                // Try to find the latest file within the days-back range
                $this->info("Searching for latest available file within {$daysBack} days...");
                $latestFile = $this->sftpService->findLatestFile($daysBack);

                if ($latestFile) {
                    $this->info("Found latest file: {$latestFile['path']} ({$latestFile['formatted_date']})");
                    $filesToDownload = [$latestFile];
                } else {
                    $error = "No files found within the specified date range.";
                    $this->error($error);

                    $this->sendNotificationIfEnabled([
                        'downloaded' => 0,
                        'skipped' => 0,
                        'errors' => 1,
                        'error_messages' => [$error],
                        'target_date' => $targetDate->toDateString(),
                        'days_back' => $daysBack,
                        'duration' => $startTime->diffInMinutes(now()),
                    ], false);

                    return 1;
                }
            }

            $this->info(sprintf('Found %d file(s) to download.', count($filesToDownload)));

            $downloadedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar(count($filesToDownload));
            $progressBar->start();

            foreach ($filesToDownload as $file) {
                $filename = basename($file['path']);

                // Check if file was already processed
                if (!$force && $this->fileRepository->existsByFilename($filename)) {
                    $existingFile = $this->fileRepository->findByFilename($filename);

                    if ($existingFile->isProcessed()) {
                        $this->line(" - Skipped file: {$filename} (already processed)");
                        $skippedCount++;
                        $progressBar->advance();
                        continue;
                    } else {
                        $this->line(" - Re-downloading file: {$filename} (processing failed previously)");
                    }
                }

                try {
                    // Determine local path with date organization
                    $dateFolder = $targetDate->format('Y/m/d');
                    $localPath = config('decta.sftp.local_path') . "/{$dateFolder}/" . $filename;

                    if ($debug) {
                        $this->info("Debug info for {$filename}:");
                        $this->info("  - Remote path: {$file['path']}");
                        $this->info("  - Local path: {$localPath}");
                        $this->info("  - Full local path: " . Storage::disk($this->diskName)->path($localPath));
                        $this->info("  - Config local path: " . config('decta.sftp.local_path'));
                        $this->info("  - Using disk: {$this->diskName}");
                    }

                    // Ensure directory exists using the decta disk
                    $directory = dirname($localPath);
                    if (!Storage::disk($this->diskName)->exists($directory)) {
                        $this->info("Creating directory via Storage ({$this->diskName} disk): {$directory}");
                        Storage::disk($this->diskName)->makeDirectory($directory);
                    }

                    // Double-check directory exists
                    if (!Storage::disk($this->diskName)->exists($directory)) {
                        throw new Exception("Failed to create directory via Storage ({$this->diskName} disk): {$directory}");
                    }

                    // Download the file
                    $success = $this->sftpService->downloadFile($file['path'], $localPath);

                    if ($success) {
                        // Verify file exists and get size using the decta disk
                        if (!Storage::disk($this->diskName)->exists($localPath)) {
                            throw new Exception("File was downloaded but not accessible via Storage ({$this->diskName} disk): {$localPath}");
                        }

                        $fileSize = Storage::disk($this->diskName)->size($localPath);
                        if ($fileSize === false || $fileSize === 0) {
                            throw new Exception("Downloaded file is empty or unreadable via Storage ({$this->diskName} disk)");
                        }

                        $fileType = pathinfo($filename, PATHINFO_EXTENSION);

                        // Create or update file record
                        $fileRecord = $this->fileRepository->findByFilename($filename);

                        if ($fileRecord && !$force) {
                            // Update existing record
                            $fileRecord->update([
                                'local_path' => $localPath,
                                'file_size' => $fileSize,
                                'status' => DectaFile::STATUS_PENDING,
                                'error_message' => null,
                            ]);
                            $this->line(" - Updated existing record for: {$filename}");
                        } else {
                            // Create new record
                            $fileRecord = $this->fileRepository->create([
                                'filename' => $filename,
                                'original_path' => $file['path'],
                                'local_path' => $localPath,
                                'file_size' => $fileSize,
                                'file_type' => $fileType,
                                'status' => DectaFile::STATUS_PENDING,
                                'metadata' => [
                                    'download_date' => Carbon::now()->toISOString(),
                                    'target_date' => $targetDate->toDateString(),
                                    'file_date' => $file['file_date'] ?? null,
                                    'last_modified' => $file['lastModified'] ?? null,
                                    'file_size_remote' => $file['fileSize'] ?? null,
                                    'disk_used' => $this->diskName,
                                ],
                            ]);
                            $this->line(" - Created new record for: {$filename}");
                        }

                        $downloadedCount++;
                        $downloadedFiles[] = $filename . ' (' . $this->formatBytes($fileSize) . ')';

                        $this->line(" - Downloaded: {$filename} ({$this->formatBytes($fileSize)})");

                        // Log successful download
                        Log::info('Decta file downloaded successfully', [
                            'filename' => $filename,
                            'target_date' => $targetDate->toDateString(),
                            'file_size' => $fileSize,
                            'local_path' => $localPath,
                            'disk' => $this->diskName,
                        ]);

                    } else {
                        $errorCount++;
                        $error = "Failed to download file: {$filename}";
                        $this->error(" - {$error}");
                        $errorMessages[] = $error;

                        // Log additional debug info
                        Log::error('File download failed', [
                            'filename' => $filename,
                            'remote_path' => $file['path'],
                            'local_path' => $localPath,
                            'directory_exists' => Storage::disk($this->diskName)->exists($directory),
                            'disk' => $this->diskName,
                        ]);
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $error = "Error downloading file: {$filename} - {$e->getMessage()}";
                    $errorMessages[] = $error;

                    // Enhanced error logging
                    $errorContext = [
                        'file' => $filename,
                        'target_date' => $targetDate->toDateString(),
                        'error' => $e->getMessage(),
                        'disk' => $this->diskName,
                    ];

                    // Add debug info if available
                    if (isset($localPath)) {
                        $errorContext['local_path'] = $localPath;
                        $errorContext['storage_exists'] = Storage::disk($this->diskName)->exists($localPath);

                        $fullPath = Storage::disk($this->diskName)->path($localPath);
                        $errorContext['full_path'] = $fullPath;
                        $errorContext['file_exists'] = file_exists($fullPath);
                        if (file_exists($fullPath)) {
                            $errorContext['file_size'] = filesize($fullPath);
                            $errorContext['is_readable'] = is_readable($fullPath);
                        }
                    }

                    Log::error('Error downloading Decta file', $errorContext);
                    $this->error(" - {$error}");

                    if ($debug) {
                        $this->error("  Debug context: " . json_encode($errorContext, JSON_PRETTY_PRINT));
                    }
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Download process completed for {$targetDate->toDateString()}:");
            $this->info(" - Downloaded: {$downloadedCount} files");
            $this->info(" - Skipped: {$skippedCount} files");
            $this->info(" - Errors: {$errorCount} files");

            if ($downloadedCount > 0) {
                $this->info("Files are ready for processing. Run 'php artisan decta:process-files' to process them.");
            }

            // Prepare notification data
            $notificationData = [
                'downloaded' => $downloadedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'target_date' => $targetDate->toDateString(),
                'days_back' => $daysBack,
                'files' => $downloadedFiles,
                'error_messages' => $errorMessages,
                'duration' => $startTime->diffInMinutes(now()),
            ];

            // Send notification
            $success = $errorCount === 0;
            $this->sendNotificationIfEnabled($notificationData, $success);

            if ($errorCount > 0) {
                $this->warn("Some files failed to download. Check the logs for details.");
                if (!$debug) {
                    $this->info("Run with --debug flag for more detailed error information.");
                }
                return 1;
            }

            return 0;

        } catch (Exception $e) {
            $error = "Failed to download files: {$e->getMessage()}";
            $this->error($error);

            Log::error('Failed to download Decta files', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification
            $this->sendNotificationIfEnabled([
                'downloaded' => 0,
                'skipped' => 0,
                'errors' => 1,
                'error_messages' => [$error],
                'target_date' => $targetDate ? $targetDate->toDateString() : 'Unknown',
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

        // Check if download notifications are enabled
        if (!config('decta.notifications.download.enabled', true)) {
            return;
        }

        // Check specific success/failure settings
        if ($success && !config('decta.notifications.download.send_on_success', true)) {
            return;
        }

        if (!$success && !config('decta.notifications.download.send_on_failure', true)) {
            return;
        }

        try {
            $this->notificationService->sendDownloadNotification($results, $success);
            $this->line($success ? '📧 Success notification sent' : '📧 Failure notification sent');
        } catch (Exception $e) {
            $this->warn("Failed to send email notification: {$e->getMessage()}");
            Log::warning('Failed to send download notification', [
                'error' => $e->getMessage(),
                'results' => $results,
                'success' => $success,
            ]);
        }
    }

    /**
     * Check server status and connectivity
     */
    private function checkServerStatus(): bool
    {
        try {
            $this->info('Checking server connectivity...');

            // Test SFTP connection
            if (!$this->sftpService->testConnection()) {
                $this->error('SFTP connection test failed.');
                return false;
            }

            // Test database connections
            if (!$this->testDatabaseConnections()) {
                $this->error('Database connection test failed.');
                return false;
            }

            // Test storage permissions
            if (!$this->testStoragePermissions()) {
                $this->error('Storage permissions test failed.');
                return false;
            }

            $this->info('Server status check passed.');
            return true;

        } catch (Exception $e) {
            $this->error("Server status check failed: {$e->getMessage()}");
            Log::error('Server status check failed', [
                'error' => $e->getMessage(),
            ]);
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
            \DB::connection()->getPdo();

            // Test payment gateway database
            \DB::connection('payment_gateway_mysql')->getPdo();

            return true;
        } catch (Exception $e) {
            Log::error('Database connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test storage permissions and functionality using the decta disk
     */
    private function testStoragePermissions(): bool
    {
        try {
            // Test creating a directory
            $testDir = 'test_' . time();
            Storage::disk($this->diskName)->makeDirectory($testDir);

            if (!Storage::disk($this->diskName)->exists($testDir)) {
                throw new Exception('Failed to create test directory');
            }

            // Test writing a file
            $testFile = $testDir . '/test.txt';
            Storage::disk($this->diskName)->put($testFile, 'test content');

            if (!Storage::disk($this->diskName)->exists($testFile)) {
                throw new Exception('Failed to create test file');
            }

            // Test reading the file
            $content = Storage::disk($this->diskName)->get($testFile);
            if ($content !== 'test content') {
                throw new Exception('Failed to read test file correctly');
            }

            // Clean up
            Storage::disk($this->diskName)->deleteDirectory($testDir);

            $this->info("Storage permissions test passed (using {$this->diskName} disk).");
            return true;

        } catch (Exception $e) {
            Log::error('Storage permissions test failed', [
                'error' => $e->getMessage(),
                'disk' => $this->diskName,
            ]);

            // Try to clean up if possible
            if (isset($testDir) && Storage::disk($this->diskName)->exists($testDir)) {
                try {
                    Storage::disk($this->diskName)->deleteDirectory($testDir);
                } catch (Exception $cleanupException) {
                    Log::warning('Failed to clean up test directory', [
                        'directory' => $testDir,
                        'error' => $cleanupException->getMessage(),
                        'disk' => $this->diskName,
                    ]);
                }
            }

            return false;
        }
    }

    /**
     * Find files for a specific date
     */
    private function findFilesForDate(Carbon $targetDate, int $daysBack): array
    {
        try {
            $this->info("Searching for files for date: {$targetDate->toDateString()}...");

            // List all files from SFTP
            $allFiles = $this->sftpService->listFiles('in_file/reports');

            if (empty($allFiles)) {
                return [];
            }

            // Filter files for the target date
            $targetFiles = [];
            $dateString = $targetDate->format('Ymd'); // YYYYMMDD format

            // Expected patterns for transaction files
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
                        $file['pattern_matched'] = $pattern;
                        $targetFiles[] = $file;
                        break;
                    }
                }
            }

            if (!empty($targetFiles)) {
                $this->info("Found " . count($targetFiles) . " file(s) for {$targetDate->toDateString()}");
                foreach ($targetFiles as $file) {
                    $this->line(" - {$file['path']} ({$this->formatBytes($file['fileSize'])})");
                }
            }

            return $targetFiles;

        } catch (Exception $e) {
            Log::error('Error finding files for date', [
                'target_date' => $targetDate->toDateString(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
