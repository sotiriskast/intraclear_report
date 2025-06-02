<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaNotificationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DectaTestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:test-notification
                            {--type=all : Type of notification to test (download|processing|matching|health|all)}
                            {--success : Test success notification (default)}
                            {--failure : Test failure notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test email notifications to verify Decta email configuration';

    /**
     * @var DectaNotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(DectaNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Decta email notifications...');

        // Check if notifications are enabled
        if (!config('decta.notifications.enabled', false)) {
            $this->error('Decta notifications are disabled. Enable them in your configuration first.');
            $this->info('Set DECTA_NOTIFICATIONS_ENABLED=true in your .env file or update the config.');
            return 1;
        }

        // Check if recipients are configured
        $recipients = config('decta.notifications.recipients', []);
        if (empty($recipients)) {
            $adminEmail = config('app.admin_email');
            if (!$adminEmail) {
                $this->error('No email recipients configured for Decta notifications.');
                $this->info('Configure recipients in DECTA_NOTIFICATION_EMAIL_1 (and _2, _3, etc.) or set app.admin_email');
                return 1;
            }
            $recipients = [$adminEmail];
        }

        $this->info('Email recipients: ' . implode(', ', $recipients));

        $type = $this->option('type');
        $testSuccess = !$this->option('failure'); // Default to success unless --failure is specified

        try {
            if ($type === 'all' || $type === 'download') {
                $this->testDownloadNotification($testSuccess);
            }

            if ($type === 'all' || $type === 'processing') {
                $this->testProcessingNotification($testSuccess);
            }

            if ($type === 'all' || $type === 'matching') {
                $this->testMatchingNotification($testSuccess);
            }

            if ($type === 'all' || $type === 'health') {
                $this->testHealthNotification();
            }

            // Send general test notification
            if ($type === 'all') {
                $this->info('Sending general test notification...');
                if ($this->notificationService->sendTestNotification()) {
                    $this->info('✅ General test notification sent successfully!');
                } else {
                    $this->error('❌ Failed to send general test notification');
                }
            }

            $this->newLine();
            $this->info('🎉 Test notifications completed!');
            $this->info('Check your email inbox for the test messages.');

            return 0;

        } catch (Exception $e) {
            $this->error("Test notification failed: {$e->getMessage()}");
            Log::error('Test notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Test download notification
     */
    private function testDownloadNotification(bool $success): void
    {
        $this->info('Testing download notification...');

        if ($success) {
            $results = [
                'downloaded' => 3,
                'skipped' => 1,
                'errors' => 0,
                'target_date' => Carbon::yesterday()->toDateString(),
                'files' => [
                    'INTCL_transact2_20250601.csv (2.3 MB)',
                    'INTCL_transact_20250601.csv (1.8 MB)',
                    'transact2_20250601.csv (2.1 MB)',
                ],
                'duration' => 5,
            ];
        } else {
            $results = [
                'downloaded' => 1,
                'skipped' => 0,
                'errors' => 2,
                'target_date' => Carbon::yesterday()->toDateString(),
                'files' => ['INTCL_transact2_20250601.csv (2.3 MB)'],
                'error_messages' => [
                    'Failed to download INTCL_transact_20250601.csv: Connection timeout',
                    'Failed to download transact2_20250601.csv: File not found',
                ],
                'duration' => 8,
            ];
        }

        $this->notificationService->sendDownloadNotification($results, $success);
        $this->info($success ? '✅ Download success notification sent' : '❌ Download failure notification sent');
    }

    /**
     * Test processing notification
     */
    private function testProcessingNotification(bool $success): void
    {
        $this->info('Testing processing notification...');

        if ($success) {
            $results = [
                'processed' => 3,
                'skipped' => 1,
                'failed' => 0,
                'total_matched' => 1247,
                'total_unmatched' => 53,
                'duration' => 12,
            ];
        } else {
            $results = [
                'processed' => 2,
                'skipped' => 0,
                'failed' => 1,
                'total_matched' => 892,
                'total_unmatched' => 34,
                'error_messages' => [
                    'Failed to process INTCL_transact_20250601.csv: Invalid CSV format',
                ],
                'duration' => 15,
            ];
        }

        $this->notificationService->sendProcessingNotification($results, $success);
        $this->info($success ? '✅ Processing success notification sent' : '❌ Processing failure notification sent');
    }

    /**
     * Test matching notification
     */
    private function testMatchingNotification(bool $success): void
    {
        $this->info('Testing matching notification...');

        if ($success) {
            $results = [
                'files_processed' => 4,
                'total_matched' => 1124,
                'total_unmatched' => 176,
                'total_errors' => 0,
                'duration' => 8,
            ];
        } else {
            $results = [
                'files_processed' => 3,
                'total_matched' => 823,
                'total_unmatched' => 201,
                'total_errors' => 1,
                'error_messages' => [
                    'File INTCL_transact2_20250601.csv: Payment gateway connection failed',
                ],
                'duration' => 10,
            ];
        }

        $this->notificationService->sendMatchingNotification($results, $success);
        $this->info($success ? '✅ Matching success notification sent' : '❌ Matching failure notification sent');
    }

    /**
     * Test health notification
     */
    private function testHealthNotification(): void
    {
        $this->info('Testing health check notification...');

        $issues = [
            'Low disk space: 8.2% free',
            '3 files stuck in processing state',
            'Payment gateway database connection timeout',
        ];

        $this->notificationService->sendHealthCheckNotification($issues);
        $this->info('⚠️ Health check notification sent');
    }
}
