<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaIssuesService;
use Modules\Decta\Services\VisaNotificationService;
use Illuminate\Support\Facades\Log;
use Exception;

class VisaIssuesDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:download-issues-reports
                            {filename? : Specific filename to download}
                            {--path= : Full remote path to the file (overrides default directory)}
                            {--remote-dir= : Remote directory path (e.g., "/in_file/Different issues")}
                            {--list : List available files on SFTP server}
                            {--list-dir= : Directory to list files from}
                            {--process-immediately : Process file immediately after download}
                            {--force : Force download even if file exists}
                            {--dry-run : Show what would be downloaded without downloading}
                            {--no-email : Disable email notifications for this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Visa Issues reports from SFTP server with flexible path options';

    /**
     * Visa Issues Service
     */
    protected VisaIssuesService $visaIssuesService;

    /**
     * Visa Notification Service
     */
    protected VisaNotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaIssuesService $visaIssuesService, VisaNotificationService $notificationService)
    {
        parent::__construct();
        $this->visaIssuesService = $visaIssuesService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📋 Visa Issues Reports Download');
        $this->line('================================');

        $startTime = now();
        $errorMessages = [];

        try {
            $filename = $this->argument('filename');
            $listOnly = $this->option('list');
            $isDryRun = $this->option('dry-run');
            $customPath = $this->option('path');
            $remoteDir = $this->option('remote-dir');
            $listDir = $this->option('list-dir');

            // If list option is provided, show available files
            if ($listOnly || (!$filename && !$customPath)) {
                $this->listAvailableFiles($listDir);

                if (!$filename && !$customPath && !$listOnly) {
                    $this->line('');
                    $this->warn('Please specify a filename to download or use --list to see available files.');
                    $this->line('');
                    $this->info('💡 Usage Examples:');
                    $this->line('Basic: php artisan visa:download-issues-reports INTCL_visa_sms_tr_det_20250501-20250531.csv');
                    $this->line('Custom directory: php artisan visa:download-issues-reports FILENAME --remote-dir="/in_file/Different issues"');
                    $this->line('Full path: php artisan visa:download-issues-reports --path="/in_file/Different issues/FILENAME.csv"');
                    $this->line('List custom dir: php artisan visa:download-issues-reports --list --list-dir="/in_file/Different issues"');

                    // Send neutral notification for list/help case
                    $this->sendNotificationIfEnabled([
                        'downloaded' => false,
                        'skipped' => false,
                        'success' => true,
                        'message' => 'Command executed for listing files or showing help',
                        'filename' => $filename,
                        'duration' => $startTime->diffInMinutes(now()),
                    ], true, false); // Don't send notification for help/list commands

                    return Command::SUCCESS;
                }

                if ($listOnly) {
                    return Command::SUCCESS;
                }
            }

            // Determine the file to download and its path
            if ($customPath) {
                // Full path provided
                $filename = basename($customPath);
                $remotePath = $customPath;
                $this->info("📥 Downloading from custom path: {$remotePath}");
            } else {
                // Filename provided, construct path
                if (!$filename) {
                    $error = 'Filename is required when not using --path option';
                    $this->error($error);
                    $errorMessages[] = $error;

                    $this->sendNotificationIfEnabled([
                        'downloaded' => false,
                        'skipped' => false,
                        'success' => false,
                        'message' => $error,
                        'filename' => $filename,
                        'error_messages' => $errorMessages,
                        'duration' => $startTime->diffInMinutes(now()),
                    ], false);

                    return Command::FAILURE;
                }

                $remoteDir = $remoteDir ?: $this->visaIssuesService->getConfig('sftp.remote_path');
                $remotePath = rtrim($remoteDir, '/') . '/' . $filename;
                $this->info("📥 Downloading: {$filename}");
                $this->line("📂 From directory: {$remoteDir}");
            }

            // Download specific file
            if ($isDryRun) {
                $this->info('🔍 DRY RUN MODE - No actual download will be performed');
                $this->line('');
            }

            $options = [
                'force' => $this->option('force'),
                'dry_run' => $isDryRun,
                'custom_remote_path' => $remotePath
            ];

            $result = $this->visaIssuesService->downloadFile($filename, $options);

            // Display result
            $this->displayDownloadResult($result);

            // Process immediately if requested
            if ($result['downloaded'] && $this->option('process-immediately') && !$isDryRun) {
                $this->line('');
                $this->info('🔄 Processing immediately...');
                $processResult = $this->processFile($result['file_record']);
                $result['process_result'] = $processResult;
            }

            // Prepare notification data
            $notificationData = [
                'downloaded' => $result['downloaded'] ?? false,
                'skipped' => $result['skipped'] ?? false,
                'success' => $result['success'] ?? false,
                'filename' => $filename,
                'message' => $result['message'] ?? '',
                'local_path' => $result['local_path'] ?? '',
                'file_record' => $result['file_record'] ?? null,
                'process_result' => $result['process_result'] ?? null,
                'error_messages' => $errorMessages,
                'duration' => $startTime->diffInMinutes(now()),
                'options' => $options
            ];

            // Send notification
            $this->sendNotificationIfEnabled($notificationData, $result['success'] || $result['skipped']);

            return $result['success'] || $result['skipped'] ? Command::SUCCESS : Command::FAILURE;

        } catch (Exception $e) {
            $error = "💥 Fatal error: " . $e->getMessage();
            $this->error($error);
            $errorMessages[] = $error;

            Log::error('Visa Issues download command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Send failure notification
            $this->sendNotificationIfEnabled([
                'downloaded' => false,
                'skipped' => false,
                'success' => false,
                'filename' => $filename ?? 'Unknown',
                'message' => $error,
                'error_messages' => $errorMessages,
                'duration' => $startTime->diffInMinutes(now()),
            ], false);

            return Command::FAILURE;
        }
    }

    /**
     * Send notification if enabled and not disabled by --no-email option
     */
    private function sendNotificationIfEnabled(array $results, bool $success, bool $force = true): void
    {
        // Skip if --no-email option is used
        if ($this->option('no-email')) {
            return;
        }

        // Skip for list/help commands unless forced
        if (!$force && ($this->option('list') || (!$this->argument('filename') && !$this->option('path')))) {
            return;
        }

        // Check if Issues download notifications are enabled
        if (!config('decta.visa_issues.notifications.enabled', false)) {
            return;
        }

        // Check specific success/failure settings
        if ($success && !config('decta.visa_issues.notifications.notify_on_success', false)) {
            return;
        }

        if (!$success && !config('decta.visa_issues.notifications.notify_on_failure', true)) {
            return;
        }

        try {
            $this->notificationService->sendIssuesDownloadNotification($results, $success);
            $this->line($success ? '📧 Success notification sent' : '📧 Failure notification sent');
        } catch (Exception $e) {
            $this->warn("Failed to send email notification: {$e->getMessage()}");
            Log::warning('Failed to send Visa Issues download notification', [
                'error' => $e->getMessage(),
                'results' => $results,
                'success' => $success,
            ]);
        }
    }

    /**
     * List available files on SFTP server
     */
    protected function listAvailableFiles(?string $customDir = null): void
    {
        $directory = $customDir ?: $this->visaIssuesService->getConfig('sftp.remote_path');

        $this->info('📂 Available Files on SFTP Server');
        $this->line('==================================');
        $this->line("Directory: {$directory}");
        $this->line('');

        try {
            $files = $this->visaIssuesService->listAvailableFiles($customDir);

            if (empty($files)) {
                $this->warn('No Visa Issues files found on SFTP server.');
                $this->line("Location checked: {$directory}");
                $this->line('Expected pattern: INTCL_visa_sms_tr_det_YYYYMMDD-YYYYMMDD.csv');
                $this->line('');
                $this->info('💡 Try different directories:');
                $this->line('php artisan visa:download-issues-reports --list --list-dir="/in_file/reports"');
                $this->line('php artisan visa:download-issues-reports --list --list-dir="/in_file/Different issues"');
                return;
            }

            $this->line("Found " . count($files) . " file(s):");
            $this->line('');

            $tableData = [];
            foreach ($files as $file) {
                $status = $file['is_downloaded'] ? '✅ Downloaded' : '📥 Available';
                if ($file['is_downloaded'] && $file['local_status']) {
                    $status .= " ({$file['local_status']})";
                }

                $dateRange = $file['date_range']['period'] ?? 'Unknown';

                $tableData[] = [
                    $file['filename'],
                    $file['size_human'],
                    $file['modified_human'],
                    $dateRange,
                    $status
                ];
            }

            $this->table(
                ['Filename', 'Size', 'Modified', 'Period', 'Status'],
                $tableData
            );

            $this->line('');
            $this->info('💡 Usage Examples:');
            $this->line('Download: php artisan visa:download-issues-reports ' . $files[0]['filename']);
            $this->line('Custom dir: php artisan visa:download-issues-reports ' . $files[0]['filename'] . ' --remote-dir="' . $directory . '"');
            $this->line('Full path: php artisan visa:download-issues-reports --path="' . $directory . '/' . $files[0]['filename'] . '"');
            $this->line('Download and process: php artisan visa:download-issues-reports ' . $files[0]['filename'] . ' --process-immediately');

        } catch (Exception $e) {
            $this->error("Failed to list files from {$directory}: " . $e->getMessage());
            $this->line('');
            $this->info('💡 Try different directories:');
            $this->line('php artisan visa:download-issues-reports --list --list-dir="/in_file/reports"');
            $this->line('php artisan visa:download-issues-reports --list --list-dir="/in_file/Different issues"');
            $this->line('php artisan visa:download-issues-reports --list --list-dir="/in_file"');
        }
    }

    /**
     * Display download result
     */
    protected function displayDownloadResult(array $result): void
    {
        if ($result['success']) {
            $this->line("   ✅ Downloaded successfully");
            if (isset($result['file_record'])) {
                $this->line("   📁 File ID: {$result['file_record']->id}");
                $this->line("   📂 Local path: {$result['local_path']}");
            }
        } elseif ($result['skipped']) {
            $this->line("   ⏭️  File already downloaded");
            if (isset($result['file_record'])) {
                $this->line("   📁 File ID: {$result['file_record']->id}");
                $this->line("   📊 Status: {$result['file_record']->status}");
            }
        } else {
            $this->line("   ❌ Download failed: " . $result['message']);

            // Provide helpful suggestions based on error type
            if (str_contains($result['message'], 'not found')) {
                $this->line('');
                $this->info('💡 Troubleshooting:');
                $this->line('1. Check available files: php artisan visa:download-issues-reports --list');
                $this->line('2. Try different directory: php artisan visa:download-issues-reports --list --list-dir="/in_file/reports"');
                $this->line('3. Use full path: php artisan visa:download-issues-reports --path="/full/path/to/file.csv"');
            }
        }
    }

    /**
     * Process file immediately
     */
    protected function processFile($fileRecord): array
    {
        try {
            $result = $this->visaIssuesService->processFile($fileRecord);

            if ($result['success']) {
                $updated = $result['updated_count'] ?? 0;
                $notFound = $result['not_found_count'] ?? 0;
                $errors = $result['error_count'] ?? 0;

                $this->line("   ✅ Processing completed");
                $this->line("   📊 Updated: {$updated}, Not found: {$notFound}, Errors: {$errors}");
            } else {
                $this->line("   ❌ Processing failed: " . ($result['error'] ?? 'Unknown error'));
            }

            return $result;

        } catch (Exception $e) {
            $this->line("   💥 Processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'updated_count' => 0,
                'not_found_count' => 0,
                'error_count' => 1
            ];
        }
    }
}
