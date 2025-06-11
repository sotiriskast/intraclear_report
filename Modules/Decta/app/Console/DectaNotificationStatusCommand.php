<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaNotificationService;

class DectaNotificationStatusCommand extends Command
{
    protected $signature = 'decta:notification-status';
    protected $description = 'Check Decta notification configuration and status';

    public function handle()
    {
        $notificationService = app(DectaNotificationService::class);
        $status = $notificationService->getNotificationStatus();

        $this->info('=== Decta Notification Status ===');
        $this->line('');

        // Overall status
        $overallStatus = $status['overall_enabled'] ? '<info>ENABLED</info>' : '<error>DISABLED</error>';
        $this->line("Overall Status: {$overallStatus}");
        $this->line('');

        // Configuration details
        $this->line('<comment>Configuration:</comment>');
        $this->line("  Notifications Enabled: " . ($status['notifications_enabled'] ? 'Yes' : 'No'));
        $this->line("  Current Environment: <info>{$status['current_environment']}</info>");
        $this->line("  Allowed Environments: " . implode(', ', $status['allowed_environments']));
        $this->line("  Environment Allowed: " . ($status['environment_allowed'] ? 'Yes' : 'No'));
        $this->line("  Recipients Count: {$status['recipients_count']}");
        $this->line('');

        // Reasons why disabled (if applicable)
        if (!$status['overall_enabled']) {
            $this->line('<comment>Reasons for disabled status:</comment>');
            if (!$status['notifications_enabled']) {
                $this->line('  - Notifications are disabled in configuration (DECTA_NOTIFICATIONS_ENABLED=false)');
            }
            if (!$status['environment_allowed']) {
                $this->line("  - Current environment '{$status['current_environment']}' is not in allowed environments");
                $this->line("  - Allowed environments: " . implode(', ', $status['allowed_environments']));
            }
            $this->line('');
        }

        // Test notification option
        if ($status['overall_enabled']) {
            if ($this->confirm('Would you like to send a test notification?')) {
                $this->info('Sending test notification...');

                $testResult = $notificationService->sendTestNotification();

                if ($testResult) {
                    $this->info('✅ Test notification sent successfully!');
                } else {
                    $this->error('❌ Test notification failed. Check logs for details.');
                }
            }
        } else {
            $this->line('<comment>Test notification not available - notifications are disabled.</comment>');
        }

        return 0;
    }
}
