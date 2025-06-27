<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaSmsService;
use Modules\Decta\Services\VisaNotificationService;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VisaSmsDownloadCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'visa:download-sms-reports
                            {--date= : Specific date to download (YYYY-MM-DD)}
                            {--days-back=7 : Number of days back to check for files}
                            {--process-immediately : Process files immediately after download}
                            {--force : Force download even if files exist}
                            {--dry-run : Show what would be downloaded without downloading}
                            {--no-email : Disable email notifications for this run}
                            {--debug : Enable debug output}';

    /**
     * The console command description.
     */
    protected $description = 'Download Visa SMS transaction detail reports and update interchange field';

    /**
     * Visa SMS Service
     */
    protected VisaSmsService $visaSmsService;

    /**
     * Visa Notification Service
     */
    protected VisaNotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaSmsService $visaSmsService, VisaNotificationService $notificationService)
    {
        parent::__construct();
        $this->visaSmsService = $visaSmsService;
        $this->notificationService = $notificationService;
    }

    /**
     * Configure the command.
     */
    protected function configure()
    {
        parent::configure();

        // Additional validation and help
        $this->setHelp('
This command downloads Visa SMS transaction detail reports from the SFTP server.

Examples:
  Download reports from last 7 days:
    php artisan visa:download-sms-reports

  Download reports from last 14 days:
    php artisan visa:download-sms-reports --days-back=14

  Download and process immediately:
    php artisan visa:download-sms-reports --process-immediately

  Test what would be downloaded:
    php artisan visa:download-sms-reports --dry-run

  Download specific date:
    php artisan visa:download-sms-reports --date=2025-06-25

  Force download existing files:
    php artisan visa:download-sms-reports --force

  Disable email notifications:
    php artisan visa:download-sms-reports --no-email
        ');
    }

    /**
     * Initialize the command.
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        // Validate input early to prevent issues
        $this->validateInputs();
    }

    /**
     * Validate command inputs
     */
    protected function validateInputs(): void
    {
        // Validate date format if provided
        if ($this->option('date')) {
            $date = $this->option('date');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error('Invalid date format. Please use YYYY-MM-DD format.');
                exit(1);
            }

            try {
                $dateObj = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                    throw new \Exception('Invalid date');
                }
            } catch (\Exception $e) {
                $this->error('Invalid date provided. Please use a valid date in YYYY-MM-DD format.');
                exit(1);
            }
        }

        // Validate days-back option
        $daysBack = (int) $this->option('days-back');
        if ($daysBack < 1 || $daysBack > 365) {
            $this->error('Days back must be between 1 and 365.');
            exit(1);
        }

        // Check for conflicting options
        if ($this->option('date') && $this->option('days-back') !== '7') {
            $this->warn('Note: --date option will override --days-back setting.');
        }
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Start with debug information if requested
            if ($this->option('debug')) {
                $this->displayDebugInfo();
            }

            if (!$this->option('quiet')) {
                $this->info('🚀 Starting Visa SMS Reports Download Process');
                $this->line('==========================================');
            }

            $startTime = now();
            $errorMessages = [];

            // Prepare options from command arguments
            $options = $this->prepareOptions();

            if (!$this->option('quiet')) {
                $this->displayConfiguration($options);
            }

            // Use the service to download files
            $results = $this->visaSmsService->downloadFiles($options);

            // Display progress during download
            if (!$this->option('quiet')) {
                $this->displayProgress($results);
                $this->displaySummary($results);
            }

            // Display detailed results table if there were any files processed
            if (!empty($results['details']) && !$this->option('quiet')) {
                $this->displayDetailsTable($results['details']);
            }

            // Collect error messages from results
            if (!empty($results['error_messages'])) {
                $errorMessages = array_merge($errorMessages, $results['error_messages']);
            }

            // Prepare notification data
            $notificationData = [
                'files_found' => $results['files_found'] ?? 0,
                'files_downloaded' => $results['files_downloaded'] ?? 0,
                'files_skipped' => $results['files_skipped'] ?? 0,
                'files_processed' => $results['files_processed'] ?? 0,
                'errors' => $results['errors'] ?? 0,
                'details' => $results['details'] ?? [],
                'error_messages' => $errorMessages,
                'duration' => $startTime->diffInMinutes(now()),
                'options' => $options
            ];

            // Send notification if enabled
            $success = ($results['errors'] ?? 0) === 0;
            $this->sendNotificationIfEnabled($notificationData, $success);

            // Final status message
            if (!$this->option('quiet')) {
                $this->displayFinalStatus($success, $results);
            }

            return $success ? self::SUCCESS : self::FAILURE;

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Prepare options from command arguments with validation
     */
    protected function prepareOptions(): array
    {
        $options = [
            'date' => $this->option('date'),
            'days_back' => (int) $this->option('days-back'),
            'process_immediately' => $this->option('process-immediately'),
            'force' => $this->option('force'),
            'dry_run' => $this->option('dry-run'),
            'debug' => $this->option('debug'),
            'quiet' => $this->option('quiet')
        ];

        // Filter out null values
        return array_filter($options, function ($value) {
            return $value !== null && $value !== false;
        });
    }

    /**
     * Display debug information
     */
    protected function displayDebugInfo(): void
    {
        $this->line('🔍 Debug Information');
        $this->line('==================');
        $this->line('Environment: ' . app()->environment());
        $this->line('PHP Version: ' . phpversion());
        $this->line('Laravel Version: ' . app()->version());
        $this->line('Memory Limit: ' . ini_get('memory_limit'));
        $this->line('Max Execution Time: ' . ini_get('max_execution_time'));

        // Check configuration
        $this->line('');
        $this->line('Configuration Check:');
        $this->line('- SFTP Host: ' . config('decta.sftp.host'));
        $this->line('- SFTP Port: ' . config('decta.sftp.port'));
        $this->line('- SFTP Username: ' . config('decta.sftp.username'));
        $this->line('- Private Key Exists: ' . (file_exists(config('decta.sftp.private_key_path')) ? 'Yes' : 'No'));
        $this->line('- Notifications Enabled: ' . (config('decta.notifications.enabled') ? 'Yes' : 'No'));
        $this->line('- Mail Driver: ' . config('mail.default'));
        $this->line('');
    }

    /**
     * Display configuration being used
     */
    protected function displayConfiguration(array $options): void
    {
        $this->info("Configuration:");
        $this->line("- Days back: " . ($options['days_back'] ?? 7));

        if (!empty($options['date'])) {
            $this->line("- Specific date: " . $options['date']);
        }

        if ($options['dry_run'] ?? false) {
            $this->line("- Mode: DRY RUN (no actual downloads)");
        }

        if ($options['force'] ?? false) {
            $this->line("- Force download: Yes");
        }

        if ($options['process_immediately'] ?? false) {
            $this->line("- Process immediately: Yes");
        }

        $this->line('');
    }

    /**
     * Send notification if enabled and not disabled by --no-email option
     */
    private function sendNotificationIfEnabled(array $results, bool $success): void
    {
        // Skip if --no-email option is used
        if ($this->option('no-email')) {
            if ($this->option('debug')) {
                $this->line('📧 Email notifications disabled by --no-email option');
            }
            return;
        }

        // Check if SMS download notifications are enabled
        if (!config('decta.visa_sms.notifications.enabled', true)) {
            if ($this->option('debug')) {
                $this->line('📧 SMS notifications disabled in configuration');
            }
            return;
        }

        // Check specific success/failure settings
        if ($success && !config('decta.visa_sms.notifications.notify_on_success', false)) {
            if ($this->option('debug')) {
                $this->line('📧 Success notifications disabled in configuration');
            }
            return;
        }

        if (!$success && !config('decta.visa_sms.notifications.notify_on_failure', true)) {
            if ($this->option('debug')) {
                $this->line('📧 Failure notifications disabled in configuration');
            }
            return;
        }

        try {
            $this->notificationService->sendSmsDownloadNotification($results, $success);
            if (!$this->option('quiet')) {
                $this->line($success ? '📧 Success notification sent' : '📧 Failure notification sent');
            }
        } catch (Exception $e) {
            if (!$this->option('quiet')) {
                $this->warn("Failed to send email notification: {$e->getMessage()}");
            }

            Log::warning('Failed to send Visa SMS download notification', [
                'error' => $e->getMessage(),
                'results' => $results,
                'success' => $success,
                'command_options' => $this->options()
            ]);
        }
    }

    /**
     * Display progress during download
     */
    protected function displayProgress(array $results): void
    {
        if (empty($results['details'])) {
            $this->warn('No files found to process.');
            return;
        }

        foreach ($results['details'] as $detail) {
            $this->line("📅 Checking: {$detail['filename']}");

            if (!$detail['found']) {
                $this->line("   ❌ File not found on SFTP server");
                continue;
            }

            $this->line("   ✅ File found on SFTP server");

            if ($detail['skipped']) {
                $this->line("   ⏭️  File already downloaded");
                continue;
            }

            if (isset($detail['error'])) {
                $this->line("   ❌ Download failed: " . $detail['error']);
                continue;
            }

            if ($detail['downloaded']) {
                $this->line("   ✅ Downloaded successfully");

                // Show processing result if it was processed immediately
                if (isset($detail['process_result'])) {
                    $this->displayProcessingResult($detail['process_result']);
                }
            } else {
                $this->line("   🔍 [DRY RUN] Would download: {$detail['filename']}");
            }
        }
    }

    /**
     * Display processing result
     */
    protected function displayProcessingResult(array $processResult): void
    {
        if ($processResult['success']) {
            $updated = $processResult['updated_count'] ?? 0;
            $notFound = $processResult['not_found_count'] ?? 0;
            $errors = $processResult['error_count'] ?? 0;
            $this->line("   🔄 Processed: {$updated} updated, {$notFound} not found, {$errors} errors");
        } else {
            $this->line("   ❌ Processing failed: " . ($processResult['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Display summary
     */
    protected function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('📊 Download Summary');
        $this->line('==================');
        $this->line("Files found on SFTP: {$results['files_found']}");
        $this->line("Files downloaded: {$results['files_downloaded']}");
        $this->line("Files skipped: {$results['files_skipped']}");

        if ($results['files_processed'] > 0) {
            $this->line("Files processed: {$results['files_processed']}");
        }

        if ($results['errors'] > 0) {
            $this->line("Errors encountered: {$results['errors']}");
        }
    }

    /**
     * Display detailed results table
     */
    protected function displayDetailsTable(array $details): void
    {
        $this->line('');
        $this->info('📋 Detailed Results');
        $this->line('===================');

        $tableData = [];
        foreach ($details as $detail) {
            $status = $this->determineFileStatus($detail);
            $message = $this->getFileMessage($detail);

            $tableData[] = [
                $detail['filename'],
                $detail['date'],
                $status,
                $message
            ];
        }

        $this->table(
            ['Filename', 'Date', 'Status', 'Message'],
            $tableData
        );
    }

    /**
     * Determine file status for display
     */
    protected function determineFileStatus(array $detail): string
    {
        if ($detail['downloaded']) {
            return '✅ Downloaded';
        } elseif ($detail['skipped']) {
            return '⏭️ Skipped';
        } elseif (!$detail['found']) {
            return '❌ Not Found';
        } elseif (isset($detail['message']) && str_contains($detail['message'], 'DRY RUN')) {
            return '🔍 Dry Run';
        } else {
            return '❌ Failed';
        }
    }

    /**
     * Get file message for display
     */
    protected function getFileMessage(array $detail): string
    {
        if (isset($detail['error'])) {
            return $detail['error'];
        }

        return $detail['message'] ?? '';
    }

    /**
     * Display final status
     */
    protected function displayFinalStatus(bool $success, array $results): void
    {
        $this->line('');

        if ($success) {
            $this->info('✅ Command completed successfully!');
        } else {
            $this->error('❌ Command completed with errors.');
        }

        // Provide helpful next steps
        if (!empty($results['details'])) {
            $downloadedFiles = array_filter($results['details'], fn($d) => $d['downloaded'] ?? false);

            if (!empty($downloadedFiles) && !$this->option('process-immediately')) {
                $this->line('');
                $this->info('💡 Next Steps:');
                $this->line('Process downloaded files: php artisan visa:process-sms-reports');
                $this->line('Check file status: php artisan visa:sms-status');
            }
        }
    }

    /**
     * Handle exceptions with proper logging and user feedback
     */
    protected function handleException(Exception $e): int
    {
        $error = "💥 Fatal error: " . $e->getMessage();

        if (!$this->option('quiet')) {
            $this->error($error);
        }

        Log::error('Visa SMS download command failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'command_options' => $this->options(),
            'environment' => app()->environment()
        ]);

        // Send failure notification
        if (!$this->option('no-email')) {
            try {
                $this->notificationService->sendSmsDownloadNotification([
                    'files_found' => 0,
                    'files_downloaded' => 0,
                    'files_skipped' => 0,
                    'errors' => 1,
                    'error_messages' => [$error],
                    'duration' => 0,
                    'command_error' => true,
                    'options' => $this->options()
                ], false);
            } catch (Exception $notificationException) {
                Log::error('Failed to send failure notification', [
                    'original_error' => $e->getMessage(),
                    'notification_error' => $notificationException->getMessage()
                ]);
            }
        }

        return self::FAILURE;
    }
}
