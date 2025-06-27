<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaNotificationService;
use Illuminate\Support\Facades\Config;

class VisaNotificationStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:notification-status
                            {--json : Output in JSON format}
                            {--check-config : Validate configuration completeness}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the current status and configuration of Visa notifications';

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
        $status = $this->notificationService->getNotificationStatus();
        $configCheck = $this->option('check-config') ? $this->checkConfiguration() : null;

        if ($this->option('json')) {
            $output = [
                'status' => $status,
                'timestamp' => now()->toISOString(),
            ];

            if ($configCheck) {
                $output['configuration_check'] = $configCheck;
            }

            $this->line(json_encode($output, JSON_PRETTY_PRINT));
        } else {
            $this->displayStatus($status);

            if ($configCheck) {
                $this->line('');
                $this->displayConfigurationCheck($configCheck);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Display notification status in a readable format
     */
    protected function displayStatus(array $status): void
    {
        $this->info('📧 Visa Notification Status');
        $this->line('===========================');
        $this->line('');

        // Overall status
        $overallStatus = $status['overall_enabled'] ? '✅ ENABLED' : '❌ DISABLED';
        $this->line("Overall Status: {$overallStatus}");
        $this->line('');

        // Configuration details
        $this->line('Configuration Details:');
        $this->line('---------------------');
        $this->line('• Notifications enabled: ' . ($status['notifications_enabled'] ? '✅ Yes' : '❌ No'));
        $this->line('• Current environment: ' . $status['current_environment']);
        $this->line('• Allowed environments: ' . implode(', ', $status['allowed_environments']));
        $this->line('• Environment allowed: ' . ($status['environment_allowed'] ? '✅ Yes' : '❌ No'));
        $this->line('• Recipients configured: ' . $status['recipients_count']);

        if ($status['recipients_count'] > 0) {
            $recipients = config('decta.notifications.recipients', []);
            $this->line('• Recipients:');
            foreach ($recipients as $index => $recipient) {
                $this->line("  " . ($index + 1) . ". {$recipient}");
            }
        }

        $this->line('');

        // Individual notification types
        $this->displayNotificationTypeStatus('SMS Notifications', 'decta.visa_sms.notifications');
        $this->displayNotificationTypeStatus('Issues Notifications', 'decta.visa_issues.notifications');

        // Recommendations
        $this->line('');
        $this->displayRecommendations($status);
    }

    /**
     * Display status for individual notification type
     */
    protected function displayNotificationTypeStatus(string $name, string $configPath): void
    {
        $enabled = config("{$configPath}.enabled", true);
        $onSuccess = config("{$configPath}.notify_on_success", false);
        $onFailure = config("{$configPath}.notify_on_failure", true);

        $status = $enabled ? '✅ Enabled' : '❌ Disabled';
        $this->line("{$name}: {$status}");

        if ($enabled) {
            $successStatus = $onSuccess ? '✅' : '❌';
            $failureStatus = $onFailure ? '✅' : '❌';
            $this->line("  • On Success: {$successStatus}  • On Failure: {$failureStatus}");
        }
    }

    /**
     * Display recommendations based on current status
     */
    protected function displayRecommendations(array $status): void
    {
        $this->info('💡 Recommendations');
        $this->line('==================');

        if (!$status['overall_enabled']) {
            if (!$status['notifications_enabled']) {
                $this->line('• Enable notifications: Set DECTA_NOTIFICATIONS_ENABLED=true in .env');
            }

            if (!$status['environment_allowed']) {
                $currentEnv = $status['current_environment'];
                $allowedEnvs = implode(',', $status['allowed_environments']);
                $this->line("• Current environment '{$currentEnv}' is not in allowed list");
                $this->line("• Update DECTA_NOTIFICATION_ENVIRONMENTS=\"{$allowedEnvs},{$currentEnv}\" in .env");
                $this->line("  OR change current environment to one of: " . implode(', ', $status['allowed_environments']));
            }
        }

        if ($status['recipients_count'] === 0) {
            $this->line('• Configure email recipients in Decta config:');
            $this->line('  DECTA_NOTIFICATION_EMAIL_1="your-email@company.com"');
            $this->line('  DECTA_NOTIFICATION_EMAIL_2="another-email@company.com"');
        }

        // Check individual Visa notification settings
        if (!$status['visa_sms_enabled']) {
            $this->line('• Enable Visa SMS notifications: Set VISA_SMS_NOTIFICATIONS_ENABLED=true in .env');
        }

        if (!$status['visa_issues_enabled']) {
            $this->line('• Enable Visa Issues notifications: Set VISA_ISSUES_NOTIFICATIONS_ENABLED=true in .env');
        }

        if ($status['overall_enabled']) {
            $this->line('• ✅ Configuration looks good!');
            $this->line('• Test notifications: php artisan visa:test-notification');
            $this->line('• Test specific type: php artisan visa:test-notification --type=sms-download');
        }
    }

    /**
     * Check configuration completeness
     */
    protected function checkConfiguration(): array
    {
        $issues = [];
        $warnings = [];
        $suggestions = [];

        // Check if config file exists
        if (!config('decta.visa_sms') || !config('decta.visa_issues')) {
            $issues[] = 'Visa configuration sections not found in decta config. Check config/decta.php';
        }

        // Check email configuration
        $mailConfig = config('mail.default');
        if (!$mailConfig || $mailConfig === 'log') {
            $warnings[] = 'Mail driver is set to "log" - emails will not be sent';
        }

        // Check SMTP configuration if using SMTP
        if ($mailConfig === 'smtp') {
            $requiredKeys = ['mail.mailers.smtp.host', 'mail.mailers.smtp.port', 'mail.from.address'];
            foreach ($requiredKeys as $key) {
                if (!config($key)) {
                    $issues[] = "SMTP configuration missing: {$key}";
                }
            }
        }

        // Check if any notification types are completely disabled
        $notificationTypes = [
            'visa_sms' => 'Visa SMS',
            'visa_issues' => 'Visa Issues'
        ];

        foreach ($notificationTypes as $type => $name) {
            $enabled = config("decta.{$type}.notifications.enabled", $type === 'visa_sms');
            $onSuccess = config("decta.{$type}.notifications.notify_on_success", false);
            $onFailure = config("decta.{$type}.notifications.notify_on_failure", true);

            if (!$enabled) {
                $suggestions[] = "Consider enabling {$name} notifications";
            } elseif (!$onSuccess && !$onFailure) {
                $warnings[] = "{$name} is enabled but won't send any notifications (both success/failure disabled)";
            }
        }

        // Check environment variables
        $envVars = [
            'DECTA_NOTIFICATIONS_ENABLED',
            'DECTA_NOTIFICATION_EMAIL_1',
            'VISA_SMS_NOTIFICATIONS_ENABLED',
            'VISA_ISSUES_NOTIFICATIONS_ENABLED'
        ];

        foreach ($envVars as $var) {
            if (!env($var)) {
                $suggestions[] = "Consider setting {$var} in .env file";
            }
        }

        return [
            'issues' => $issues,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'overall_health' => empty($issues) ? 'good' : 'needs_attention',
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Display configuration check results
     */
    protected function displayConfigurationCheck(array $check): void
    {
        $this->info('🔍 Configuration Check');
        $this->line('======================');

        if (!empty($check['issues'])) {
            $this->line('');
            $this->error('❌ Issues Found:');
            foreach ($check['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }

        if (!empty($check['warnings'])) {
            $this->line('');
            $this->warn('⚠️  Warnings:');
            foreach ($check['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }

        if (!empty($check['suggestions'])) {
            $this->line('');
            $this->info('💡 Suggestions:');
            foreach ($check['suggestions'] as $suggestion) {
                $this->line("  • {$suggestion}");
            }
        }

        if (empty($check['issues']) && empty($check['warnings'])) {
            $this->line('');
            $this->info('✅ Configuration check passed!');
        }

        $this->line('');
        $healthStatus = $check['overall_health'] === 'good' ? '✅ Good' : '⚠️  Needs Attention';
        $this->line("Overall Health: {$healthStatus}");
    }
}
