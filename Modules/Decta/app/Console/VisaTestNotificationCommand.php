<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaNotificationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class VisaTestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:test-notification
                            {--type=all : Type of notification to test (sms-download|sms-processing|issues-download|issues-processing|all)}
                            {--success : Test success notification (default)}
                            {--failure : Test failure notification}
                            {--email= : Send to specific email instead of configured recipients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test email notifications to verify Visa email configuration';

    /**
     * @var VisaNotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Visa email notifications...');

        // Get notification status
        $status = $this->notificationService->getNotificationStatus();

        // Display current configuration
        $this->displayNotificationStatus($status);

        // Check if notifications are enabled
        if (!$status['overall_enabled']) {
            $this->error('Visa notifications are disabled.');
            $this->info('Enable them in your configuration:');
            $this->line('- Set VISA_NOTIFICATIONS_ENABLED=true in your .env file');
            $this->line('- Check your environment is in allowed list: ' . implode(', ', $status['allowed_environments']));
            return 1;
        }

        // Check if recipients are configured
        if ($status['recipients_count'] === 0) {
            $this->error('No email recipients configured for Visa notifications.');
            $this->info('Configure recipients using these environment variables:');
            $this->line('- VISA_NOTIFICATION_EMAIL_1=your-email@company.com');
            $this->line('- VISA_NOTIFICATION_EMAIL_2=another-email@company.com');
            $this->line('- etc.');
            return 1;
        }

        $type = $this->option('type');
        $testSuccess = !$this->option('failure'); // Default to success unless --failure is specified
        $customEmail = $this->option('email');

        // Override recipients if specific email provided
        if ($customEmail) {
            $originalRecipients = config('visa.notifications.recipients');
            config(['visa.notifications.recipients' => [$customEmail]]);
            $this->info("Sending test notification to: {$customEmail}");
        }

        try {
            $testsSent = 0;

            if ($type === 'all' || $type === 'sms-download') {
                $this->testSmsDownloadNotification($testSuccess);
                $testsSent++;
            }

            if ($type === 'all' || $type === 'sms-processing') {
                $this->testSmsProcessingNotification($testSuccess);
                $testsSent++;
            }

            if ($type === 'all' || $type === 'issues-download') {
                $this->testIssuesDownloadNotification($testSuccess);
                $testsSent++;
            }

            if ($type === 'all' || $type === 'issues-processing') {
                $this->testIssuesProcessingNotification($testSuccess);
                $testsSent++;
            }

            // Send general test notification if testing all
            if ($type === 'all') {
                $this->info('Sending general Visa test notification...');
                if ($this->notificationService->sendTestNotification('sms')) {
                    $this->info('✅ General SMS test notification sent successfully!');
                    $testsSent++;
                } else {
                    $this->error('❌ Failed to send general SMS test notification');
                }

                if ($this->notificationService->sendTestNotification('issues')) {
                    $this->info('✅ General Issues test notification sent successfully!');
                    $testsSent++;
                } else {
                    $this->error('❌ Failed to send general Issues test notification');
                }
            }

            // Restore original recipients if they were overridden
            if ($customEmail) {
                config(['visa.notifications.recipients' => $originalRecipients]);
            }

            $this->newLine();
            $this->info("🎉 Test notifications completed! Sent {$testsSent} notification(s).");
            $this->info('Check your email inbox for the test messages.');
            $this->newLine();

            $this->info('💡 Tips:');
            $this->line('- Check spam folder if emails don\'t arrive');
            $this->line('- Verify your mail configuration is correct');
            $this->line('- Test individual notification types with --type option');
            $this->line('- Test failure notifications with --failure option');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to send test notifications: ' . $e->getMessage());
            Log::error('Visa test notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Restore original recipients if they were overridden
            if ($customEmail) {
                config(['visa.notifications.recipients' => $originalRecipients]);
            }

            return self::FAILURE;
        }
    }

    /**
     * Display current notification status
     */
    protected function displayNotificationStatus(array $status): void
    {
        $this->line('');
        $this->info('📧 Current Notification Configuration');
        $this->line('====================================');
        $this->line('Notifications enabled: ' . ($status['notifications_enabled'] ? '✅ Yes' : '❌ No'));
        $this->line('Current environment: ' . $status['current_environment']);
        $this->line('Allowed environments: ' . implode(', ', $status['allowed_environments']));
        $this->line('Environment allowed: ' . ($status['environment_allowed'] ? '✅ Yes' : '❌ No'));
        $this->line('Overall enabled: ' . ($status['overall_enabled'] ? '✅ Yes' : '❌ No'));
        $this->line('Recipients configured: ' . $status['recipients_count']);

        if ($status['recipients_count'] > 0) {
            $recipients = config('visa.notifications.recipients', []);
            $this->line('Recipients: ' . implode(', ', $recipients));
        }

        $this->line('');
    }

    /**
     * Test SMS download notification
     */
    protected function testSmsDownloadNotification(bool $success): void
    {
        $this->info('Testing Visa SMS download notification...');

        $results = [
            'files_found' => 3,
            'files_downloaded' => $success ? 2 : 0,
            'files_skipped' => 1,
            'errors' => $success ? 0 : 2,
            'details' => [
                [
                    'filename' => 'INTCL_visa_sms_tr_det_20250125.csv',
                    'date' => '2025-01-25',
                    'downloaded' => $success,
                    'skipped' => false,
                    'message' => $success ? 'Downloaded successfully' : 'Download failed: Connection timeout'
                ],
                [
                    'filename' => 'INTCL_visa_sms_tr_det_20250124.csv',
                    'date' => '2025-01-24',
                    'downloaded' => false,
                    'skipped' => true,
                    'message' => 'File already exists'
                ]
            ],
            'duration' => 5,
            'error_messages' => $success ? [] : ['SFTP connection timeout', 'Authentication failed']
        ];

        try {
            $this->notificationService->sendSmsDownloadNotification($results, $success);
            $this->info($success ? '✅ SMS download success notification sent!' : '✅ SMS download failure notification sent!');
        } catch (Exception $e) {
            $this->error('❌ Failed to send SMS download notification: ' . $e->getMessage());
        }
    }

    /**
     * Test SMS processing notification
     */
    protected function testSmsProcessingNotification(bool $success): void
    {
        $this->info('Testing Visa SMS processing notification...');

        $results = [
            'files_processed' => $success ? 2 : 1,
            'files_failed' => $success ? 0 : 1,
            'total_transactions_updated' => $success ? 1250 : 850,
            'details' => [
                [
                    'filename' => 'INTCL_visa_sms_tr_det_20250125.csv',
                    'file_id' => 123,
                    'success' => true,
                    'updated_count' => 850,
                    'not_found_count' => 15,
                    'error_count' => 0
                ],
                [
                    'filename' => 'INTCL_visa_sms_tr_det_20250124.csv',
                    'file_id' => 124,
                    'success' => $success,
                    'updated_count' => $success ? 400 : 0,
                    'not_found_count' => $success ? 8 : 0,
                    'error_count' => $success ? 0 : 1,
                    'error' => $success ? null : 'Database connection lost during processing'
                ]
            ],
            'duration' => 12,
            'error_messages' => $success ? [] : ['Database connection lost during processing']
        ];

        try {
            $this->notificationService->sendSmsProcessingNotification($results, $success);
            $this->info($success ? '✅ SMS processing success notification sent!' : '✅ SMS processing failure notification sent!');
        } catch (Exception $e) {
            $this->error('❌ Failed to send SMS processing notification: ' . $e->getMessage());
        }
    }

    /**
     * Test Issues download notification
     */
    protected function testIssuesDownloadNotification(bool $success): void
    {
        $this->info('Testing Visa Issues download notification...');

        $results = [
            'downloaded' => $success,
            'skipped' => false,
            'success' => $success,
            'filename' => 'INTCL_visa_sms_tr_det_20250101-20250131.csv',
            'message' => $success ? 'Downloaded successfully' : 'File not found on SFTP server',
            'local_path' => $success ? 'visa/issues/INTCL_visa_sms_tr_det_20250101-20250131.csv' : null,
            'duration' => 3,
            'error_messages' => $success ? [] : ['File not found: /in_file/Different issues/INTCL_visa_sms_tr_det_20250101-20250131.csv']
        ];

        try {
            $this->notificationService->sendIssuesDownloadNotification($results, $success);
            $this->info($success ? '✅ Issues download success notification sent!' : '✅ Issues download failure notification sent!');
        } catch (Exception $e) {
            $this->error('❌ Failed to send Issues download notification: ' . $e->getMessage());
        }
    }

    /**
     * Test Issues processing notification
     */
    protected function testIssuesProcessingNotification(bool $success): void
    {
        $this->info('Testing Visa Issues processing notification...');

        $results = [
            'files_processed' => $success ? 1 : 0,
            'files_failed' => $success ? 0 : 1,
            'total_transactions_updated' => $success ? 2150 : 0,
            'details' => [
                [
                    'filename' => 'INTCL_visa_sms_tr_det_20250101-20250131.csv',
                    'file_id' => 125,
                    'success' => $success,
                    'updated_count' => $success ? 2150 : 0,
                    'not_found_count' => $success ? 45 : 0,
                    'error_count' => $success ? 0 : 1,
                    'error' => $success ? null : 'Invalid CSV format: missing required columns'
                ]
            ],
            'duration' => 18,
            'error_messages' => $success ? [] : ['Invalid CSV format: missing required columns']
        ];

        try {
            $this->notificationService->sendIssuesProcessingNotification($results, $success);
            $this->info($success ? '✅ Issues processing success notification sent!' : '✅ Issues processing failure notification sent!');
        } catch (Exception $e) {
            $this->error('❌ Failed to send Issues processing notification: ' . $e->getMessage());
        }
    }
}
