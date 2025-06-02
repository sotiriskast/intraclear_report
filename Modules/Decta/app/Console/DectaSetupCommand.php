<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaSftpService;
use Modules\Decta\Services\DectaNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Exception;

class DectaSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:setup
                            {--check-only : Only check configuration without making changes}
                            {--skip-email : Skip email configuration check}
                            {--skip-sftp : Skip SFTP connection check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate and setup Decta module configuration';

    /**
     * @var DectaSftpService
     */
    protected $sftpService;

    /**
     * @var DectaNotificationService
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaSftpService $sftpService,
        DectaNotificationService $notificationService
    ) {
        parent::__construct();
        $this->sftpService = $sftpService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Decta Module Setup and Validation');
        $this->info('=====================================');

        $issues = [];
        $warnings = [];
        $checksPassed = 0;
        $totalChecks = 0;

        // Database Configuration Check
        $this->info("\n📊 Checking Database Configuration...");
        $dbResult = $this->checkDatabaseConfiguration();
        $issues = array_merge($issues, $dbResult['issues']);
        $warnings = array_merge($warnings, $dbResult['warnings']);
        $checksPassed += $dbResult['passed'];
        $totalChecks += $dbResult['total'];

        // Storage Configuration Check
        $this->info("\n💾 Checking Storage Configuration...");
        $storageResult = $this->checkStorageConfiguration();
        $issues = array_merge($issues, $storageResult['issues']);
        $warnings = array_merge($warnings, $storageResult['warnings']);
        $checksPassed += $storageResult['passed'];
        $totalChecks += $storageResult['total'];

        // SFTP Configuration Check
        if (!$this->option('skip-sftp')) {
            $this->info("\n🔌 Checking SFTP Configuration...");
            $sftpResult = $this->checkSftpConfiguration();
            $issues = array_merge($issues, $sftpResult['issues']);
            $warnings = array_merge($warnings, $sftpResult['warnings']);
            $checksPassed += $sftpResult['passed'];
            $totalChecks += $sftpResult['total'];
        }

        // Email Configuration Check
        if (!$this->option('skip-email')) {
            $this->info("\n📧 Checking Email Configuration...");
            $emailResult = $this->checkEmailConfiguration();
            $issues = array_merge($issues, $emailResult['issues']);
            $warnings = array_merge($warnings, $emailResult['warnings']);
            $checksPassed += $emailResult['passed'];
            $totalChecks += $emailResult['total'];
        }

        // Configuration Files Check
        $this->info("\n⚙️ Checking Configuration Files...");
        $configResult = $this->checkConfigurationFiles();
        $issues = array_merge($issues, $configResult['issues']);
        $warnings = array_merge($warnings, $configResult['warnings']);
        $checksPassed += $configResult['passed'];
        $totalChecks += $configResult['total'];

        // Scheduled Tasks Check
        $this->info("\n⏰ Checking Scheduled Tasks...");
        $scheduleResult = $this->checkScheduledTasks();
        $issues = array_merge($issues, $scheduleResult['issues']);
        $warnings = array_merge($warnings, $scheduleResult['warnings']);
        $checksPassed += $scheduleResult['passed'];
        $totalChecks += $scheduleResult['total'];

        // Display Results
        $this->displayResults($checksPassed, $totalChecks, $issues, $warnings);

        // Provide next steps
        $this->provideNextSteps($issues, $warnings);

        return empty($issues) ? 0 : 1;
    }

    /**
     * Check database configuration
     */
    private function checkDatabaseConfiguration(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check main database connection
        $result['total']++;
        try {
            DB::connection()->getPdo();
            $this->line('  ✓ Main database connection OK');
            $result['passed']++;
        } catch (Exception $e) {
            $result['issues'][] = "Main database connection failed: {$e->getMessage()}";
            $this->line('  ✗ Main database connection failed');
        }

        // Check payment gateway database connection
        $result['total']++;
        try {
            DB::connection('payment_gateway_mysql')->getPdo();
            $this->line('  ✓ Payment gateway database connection OK');
            $result['passed']++;
        } catch (Exception $e) {
            $result['issues'][] = "Payment gateway database connection failed: {$e->getMessage()}";
            $this->line('  ✗ Payment gateway database connection failed');
        }

        // Check required tables exist
        $requiredTables = ['decta_files', 'decta_transactions'];
        foreach ($requiredTables as $table) {
            $result['total']++;
            if (Schema::hasTable($table)) {
                $this->line("  ✓ Table '{$table}' exists");
                $result['passed']++;
            } else {
                $result['issues'][] = "Required table '{$table}' does not exist";
                $this->line("  ✗ Table '{$table}' missing");
            }
        }

        return $result;
    }

    /**
     * Check storage configuration
     */
    private function checkStorageConfiguration(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check decta disk configuration
        $result['total']++;
        try {
            $disk = Storage::disk('decta');
            $testFile = 'setup_test_' . time() . '.txt';

            $disk->put($testFile, 'test content');

            if (!$disk->exists($testFile)) {
                throw new Exception('Failed to create test file');
            }

            $content = $disk->get($testFile);
            if ($content !== 'test content') {
                throw new Exception('Failed to read test file correctly');
            }

            $disk->delete($testFile);

            $this->line('  ✓ Decta disk configuration OK');
            $result['passed']++;
        } catch (Exception $e) {
            $result['issues'][] = "Decta disk configuration failed: {$e->getMessage()}";
            $this->line('  ✗ Decta disk configuration failed');
        }

        // Check disk space
        $result['total']++;
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);

        if ($freeBytes !== false && $totalBytes !== false) {
            $freePercentage = round(($freeBytes / $totalBytes) * 100, 1);

            if ($freePercentage < 10) {
                $result['issues'][] = "Low disk space: {$freePercentage}% free";
                $this->line("  ✗ Low disk space: {$freePercentage}% free");
            } elseif ($freePercentage < 20) {
                $result['warnings'][] = "Disk space getting low: {$freePercentage}% free";
                $this->line("  ⚠ Disk space getting low: {$freePercentage}% free");
                $result['passed']++;
            } else {
                $this->line("  ✓ Disk space OK: {$freePercentage}% free");
                $result['passed']++;
            }
        } else {
            $result['warnings'][] = 'Could not check disk space';
            $this->line('  ⚠ Could not check disk space');
        }

        return $result;
    }

    /**
     * Check SFTP configuration
     */
    private function checkSftpConfiguration(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check SFTP configuration values
        $config = config('decta.sftp');
        $requiredKeys = ['host', 'port', 'username', 'private_key_path'];

        foreach ($requiredKeys as $key) {
            $result['total']++;
            if (!empty($config[$key])) {
                $this->line("  ✓ SFTP {$key} configured");
                $result['passed']++;
            } else {
                $result['issues'][] = "SFTP {$key} not configured";
                $this->line("  ✗ SFTP {$key} not configured");
            }
        }

        // Check private key file exists
        $result['total']++;
        if (!empty($config['private_key_path']) && file_exists($config['private_key_path'])) {
            $this->line('  ✓ Private key file exists');
            $result['passed']++;

            // Check private key permissions
            $permissions = substr(sprintf('%o', fileperms($config['private_key_path'])), -4);
            if ($permissions !== '0600') {
                $result['warnings'][] = "Private key permissions are {$permissions}, should be 0600";
                $this->line("  ⚠ Private key permissions: {$permissions} (should be 0600)");
            }
        } else {
            $result['issues'][] = 'Private key file not found';
            $this->line('  ✗ Private key file not found');
        }

        // Test SFTP connection
        $result['total']++;
        try {
            if ($this->sftpService->testConnection()) {
                $this->line('  ✓ SFTP connection test passed');
                $result['passed']++;
            } else {
                $result['issues'][] = 'SFTP connection test failed';
                $this->line('  ✗ SFTP connection test failed');
            }
        } catch (Exception $e) {
            $result['issues'][] = "SFTP connection error: {$e->getMessage()}";
            $this->line('  ✗ SFTP connection error');
        }

        return $result;
    }

    /**
     * Check email configuration
     */
    private function checkEmailConfiguration(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check if notifications are enabled
        $result['total']++;
        if (config('decta.notifications.enabled', false)) {
            $this->line('  ✓ Email notifications enabled');
            $result['passed']++;
        } else {
            $result['warnings'][] = 'Email notifications disabled';
            $this->line('  ⚠ Email notifications disabled');
        }

        // Check email recipients
        $result['total']++;
        $recipients = config('decta.notifications.recipients', []);
        $adminEmail = config('app.admin_email');

        if (!empty($recipients)) {
            $this->line('  ✓ Email recipients configured: ' . implode(', ', $recipients));
            $result['passed']++;
        } elseif ($adminEmail) {
            $this->line('  ⚠ Using fallback admin email: ' . $adminEmail);
            $result['warnings'][] = 'No specific notification recipients, using admin email';
        } else {
            $result['issues'][] = 'No email recipients configured';
            $this->line('  ✗ No email recipients configured');
        }

        // Check mail configuration
        $result['total']++;
        $mailConfig = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        ];

        $missingConfig = array_filter($mailConfig, fn($value) => empty($value));

        if (empty($missingConfig)) {
            $this->line('  ✓ Mail configuration complete');
            $result['passed']++;
        } else {
            $result['issues'][] = 'Mail configuration incomplete: ' . implode(', ', array_keys($missingConfig));
            $this->line('  ✗ Mail configuration incomplete');
        }

        // Test email sending
        $result['total']++;
        try {
            // Only test if we have recipients and mail is configured
            if ((config('decta.notifications.enabled') && (!empty($recipients) || $adminEmail)) && empty($missingConfig)) {
                $testResult = $this->notificationService->sendTestNotification();
                if ($testResult) {
                    $this->line('  ✓ Test email sent successfully');
                    $result['passed']++;
                } else {
                    $result['issues'][] = 'Test email failed to send';
                    $this->line('  ✗ Test email failed to send');
                }
            } else {
                $this->line('  ⚠ Skipping email test (configuration issues)');
                $result['warnings'][] = 'Email test skipped due to configuration issues';
            }
        } catch (Exception $e) {
            $result['issues'][] = "Email test error: {$e->getMessage()}";
            $this->line('  ✗ Email test error');
        }

        return $result;
    }

    /**
     * Check configuration files
     */
    private function checkConfigurationFiles(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check if config is published
        $result['total']++;
        $configPath = config_path('decta.php');
        if (file_exists($configPath)) {
            $this->line('  ✓ Decta config file published');
            $result['passed']++;
        } else {
            $result['warnings'][] = 'Decta config not published (using package defaults)';
            $this->line('  ⚠ Decta config not published (using package defaults)');
        }

        // Check important environment variables
        $requiredEnvVars = [
            'DECTA_SFTP_HOST',
            'DECTA_SFTP_USERNAME',
            'DECTA_SFTP_PRIVATE_KEY_PATH',
        ];

        foreach ($requiredEnvVars as $envVar) {
            $result['total']++;
            if (env($envVar)) {
                $this->line("  ✓ {$envVar} configured");
                $result['passed']++;
            } else {
                $result['warnings'][] = "{$envVar} not set in .env";
                $this->line("  ⚠ {$envVar} not set in .env");
            }
        }

        return $result;
    }

    /**
     * Check scheduled tasks
     */
    private function checkScheduledTasks(): array
    {
        $result = ['issues' => [], 'warnings' => [], 'passed' => 0, 'total' => 0];

        // Check if cron is likely configured (this is just informational)
        $result['total']++;
        $this->line('  ℹ Scheduled tasks are configured in DectaServiceProvider');
        $this->line('    - Daily download at 2 AM');
        $this->line('    - Daily processing at 3 AM');
        $this->line('    - Retry failures every 6 hours');
        $this->line('    - Retry matching every 4 hours');
        $result['warnings'][] = 'Verify cron is configured: * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1';
        $result['passed']++; // Count as passed since we can't actually verify cron

        return $result;
    }

    /**
     * Display results summary
     */
    private function displayResults(int $passed, int $total, array $issues, array $warnings): void
    {
        $this->info("\n📋 Setup Validation Results");
        $this->info("===========================");

        $this->info("Checks passed: {$passed}/{$total}");

        if (!empty($issues)) {
            $this->error("\n❌ Issues found:");
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
        }

        if (!empty($warnings)) {
            $this->warn("\n⚠️ Warnings:");
            foreach ($warnings as $warning) {
                $this->line("  • {$warning}");
            }
        }

        if (empty($issues) && empty($warnings)) {
            $this->info("\n🎉 All checks passed! Decta module is ready to use.");
        } elseif (empty($issues)) {
            $this->info("\n✅ Setup complete with minor warnings.");
        } else {
            $this->error("\n🔧 Setup requires attention. Please fix the issues above.");
        }
    }

    /**
     * Provide next steps
     */
    private function provideNextSteps(array $issues, array $warnings): void
    {
        if (!empty($issues) || !empty($warnings)) {
            $this->info("\n🚀 Next Steps:");

            if (!empty($issues)) {
                $this->info("\n1. Fix Critical Issues:");
                $this->info("   - Check database connections");
                $this->info("   - Verify SFTP configuration and credentials");
                $this->info("   - Run migrations: php artisan migrate");
                $this->info("   - Configure mail settings in .env");
            }

            if (!empty($warnings)) {
                $this->info("\n2. Address Warnings:");
                $this->info("   - Publish config: php artisan vendor:publish --tag=config --provider=\"Modules\\Decta\\Providers\\DectaServiceProvider\"");
                $this->info("   - Set private key permissions: chmod 600 /path/to/decta_rsa");
                $this->info("   - Configure email recipients in .env");
                $this->info("   - Set up cron job for scheduled tasks");
            }
        }

        $this->info("\n3. Test the Module:");
        $this->info("   - Test SFTP: php artisan decta:test-connection");
        $this->info("   - Test notifications: php artisan decta:test-notification");
        $this->info("   - Download files: php artisan decta:download-files --debug");
        $this->info("   - Process files: php artisan decta:process-files");

        $this->info("\n4. Documentation:");
        $this->info("   - Review the setup guide for detailed configuration");
        $this->info("   - Check logs in storage/logs/ for any issues");

        $this->info("\n📚 For more help, run: php artisan decta:status");
    }
}
