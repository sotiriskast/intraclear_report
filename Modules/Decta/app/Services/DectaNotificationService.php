<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DectaNotificationService
{
    /**
     * Get notification recipients from config
     */
    protected function getRecipients(): array
    {
        $recipients = config('decta.notifications.recipients', []);
        return array_filter($recipients); // Remove empty values
    }

    /**
     * Check if notifications are enabled and environment allows emails
     */
    protected function isNotificationsEnabled(): bool
    {
        $configEnabled = config('decta.notifications.enabled', true);
        $environmentAllowed = $this->isEnvironmentAllowedForEmails();

        if (!$environmentAllowed) {
            Log::info('Email notifications skipped - environment not allowed', [
                'environment' => app()->environment(),
                'allowed_environments' => $this->getAllowedEnvironments()
            ]);
        }

        return $configEnabled && $environmentAllowed;
    }

    /**
     * Check if current environment allows email sending
     */
    protected function isEnvironmentAllowedForEmails(): bool
    {
        $currentEnv = app()->environment();
        $allowedEnvironments = $this->getAllowedEnvironments();

        return in_array($currentEnv, $allowedEnvironments);
    }

    /**
     * Get list of environments where emails are allowed
     */
    protected function getAllowedEnvironments(): array
    {
        $allowedEnvs = config('decta.notifications.allowed_environments', ['staging', 'production']);

        // If it's a string (from env), convert to array
        if (is_string($allowedEnvs)) {
            $allowedEnvs = array_map('trim', explode(',', $allowedEnvs));
        }

        // Filter out empty values
        return array_filter($allowedEnvs);
    }

    /**
     * Send declined transactions notification
     */
    public function sendDeclinedTransactionsNotification(string $subject, array $summaryData): void
    {
        if (!$this->isNotificationsEnabled()) {
            Log::info('Declined transactions notification skipped', [
                'reason' => !config('decta.notifications.enabled', true) ? 'notifications disabled' : 'environment not allowed',
                'environment' => app()->environment()
            ]);
            return;
        }

        $recipients = $this->getRecipients();
        if (empty($recipients)) {
            Log::warning('No recipients configured for declined transactions notification');
            return;
        }

        try {
            $mailable = new DeclinedTransactionsMail($subject, $summaryData);

            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send($mailable);
                Log::info("Declined transactions notification sent to: {$recipient}", [
                    'environment' => app()->environment()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send declined transactions notification', [
                'error' => $e->getMessage(),
                'recipients' => $recipients,
                'summary_data' => $summaryData,
                'environment' => app()->environment()
            ]);
            throw $e;
        }
    }

    /**
     * Send error notification
     */
    public function sendErrorNotification(string $subject, string $message, array $details = []): void
    {
        if (!$this->isNotificationsEnabled()) {
            Log::info('Error notification skipped', [
                'reason' => !config('decta.notifications.enabled', true) ? 'notifications disabled' : 'environment not allowed',
                'environment' => app()->environment(),
                'original_message' => $message
            ]);
            return;
        }

        $recipients = $this->getRecipients();
        if (empty($recipients)) {
            Log::warning('No recipients configured for error notification', [
                'message' => $message
            ]);
            return;
        }

        try {
            $mailable = new ErrorNotificationMail($subject, $message, $details);

            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send($mailable);
                Log::info("Error notification sent to: {$recipient}", [
                    'environment' => app()->environment()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send error notification', [
                'error' => $e->getMessage(),
                'original_message' => $message,
                'environment' => app()->environment()
            ]);
        }
    }

    /**
     * Send general notification
     */
    public function sendGeneralNotification(string $subject, string $message, array $data = []): void
    {
        if (!$this->isNotificationsEnabled()) {
            Log::info('General notification skipped', [
                'reason' => !config('decta.notifications.enabled', true) ? 'notifications disabled' : 'environment not allowed',
                'environment' => app()->environment(),
                'subject' => $subject
            ]);
            return;
        }

        $recipients = $this->getRecipients();
        if (empty($recipients)) {
            Log::warning('No recipients configured for general notification', [
                'subject' => $subject
            ]);
            return;
        }

        try {
            $mailable = new GeneralNotificationMail($subject, $message, $data);

            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send($mailable);
                Log::info("General notification sent to: {$recipient}", [
                    'environment' => app()->environment()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send general notification', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'environment' => app()->environment()
            ]);
        }
    }

    /**
     * Send notification for download process
     */
    public function sendDownloadNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Decta Download Completed Successfully"
            : "❌ Decta Download Failed";

        $data = [
            'process_type' => 'Download',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmail($subject, 'decta-download-notification', $data);
    }

    /**
     * Send notification for file processing
     */
    public function sendProcessingNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Decta Processing Completed Successfully"
            : "❌ Decta Processing Failed";

        $data = [
            'process_type' => 'Processing',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmail($subject, 'decta-processing-notification', $data);
    }

    /**
     * Send notification for transaction matching
     */
    public function sendMatchingNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Decta Matching Completed Successfully"
            : "❌ Decta Matching Failed";

        $data = [
            'process_type' => 'Matching',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmail($subject, 'decta-matching-notification', $data);
    }

    /**
     * Send notification for system health issues
     */
    public function sendHealthCheckNotification(array $issues): void
    {
        if (!$this->shouldSendNotifications() || empty($issues)) {
            return;
        }

        $subject = "⚠️ Decta System Health Alert";

        $data = [
            'process_type' => 'Health Check',
            'success' => false,
            'issues' => $issues,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmail($subject, 'decta-health-notification', $data);
    }

    /**
     * Send generic email notification (uses the new environment check)
     */
    private function sendEmail(string $subject, string $view, array $data): void
    {
        try {
            $recipients = $this->getNotificationRecipients();

            if (empty($recipients)) {
                Log::warning('No email recipients configured for Decta notifications');
                return;
            }

            // Send using Laravel's Mail facade with a simple view
            Mail::send([], [], function ($message) use ($subject, $recipients, $data) {
                $message->to($recipients)
                    ->subject($subject)
                    ->html($this->generateEmailHtml($data));
            });

            Log::info('Decta notification email sent', [
                'subject' => $subject,
                'recipients' => $recipients,
                'process_type' => $data['process_type'] ?? 'Unknown',
                'environment' => app()->environment()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Decta notification email', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'data' => $data,
                'environment' => app()->environment()
            ]);
        }
    }

    /**
     * Generate HTML email content
     */
    private function generateEmailHtml(array $data): string
    {
        $status = $data['success'] ? 'SUCCESS' : 'FAILED';
        $statusColor = $data['success'] ? '#10B981' : '#EF4444';
        $statusIcon = $data['success'] ? '✅' : '❌';
        $environment = $data['environment'] ?? app()->environment();

        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$statusColor}; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .status { font-size: 24px; font-weight: bold; }
                .details { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid {$statusColor}; }
                .timestamp { color: #666; font-size: 14px; }
                .environment { background: #e3f2fd; color: #1976d2; padding: 5px 10px; border-radius: 4px; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f2f2f2; }
                .error { color: #EF4444; background: #FEE2E2; padding: 10px; border-radius: 4px; margin: 5px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='status'>{$statusIcon} Decta {$data['process_type']} - {$status}</div>
                    <div class='timestamp'>Server: {$data['server']}</div>
                    <div class='timestamp'>Time: {$data['timestamp']}</div>
                    <div class='timestamp'>Environment: <span class='environment'>{$environment}</span></div>
                </div>
                <div class='content'>
        ";

        // Add process-specific content
        if ($data['process_type'] === 'Download') {
            $html .= $this->generateDownloadContent($data);
        } elseif ($data['process_type'] === 'Processing') {
            $html .= $this->generateProcessingContent($data);
        } elseif ($data['process_type'] === 'Matching') {
            $html .= $this->generateMatchingContent($data);
        } elseif ($data['process_type'] === 'Health Check') {
            $html .= $this->generateHealthContent($data);
        }

        $html .= "
                </div>
            </div>
        </body>
        </html>
        ";

        return $html;
    }

    /**
     * Generate download-specific content
     */
    private function generateDownloadContent(array $data): string
    {
        $results = $data['results'];

        $html = "<div class='details'>";
        $html .= "<h3>Download Summary</h3>";
        $html .= "<table>";
        $html .= "<tr><th>Metric</th><th>Count</th></tr>";
        $html .= "<tr><td>Downloaded</td><td>" . ($results['downloaded'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Skipped</td><td>" . ($results['skipped'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Errors</td><td>" . ($results['errors'] ?? 0) . "</td></tr>";
        $html .= "</table>";

        if (!empty($results['target_date'])) {
            $html .= "<p><strong>Target Date:</strong> {$results['target_date']}</p>";
        }

        if (!empty($results['files'])) {
            $html .= "<h4>Downloaded Files:</h4><ul>";
            foreach ($results['files'] as $file) {
                $html .= "<li>{$file}</li>";
            }
            $html .= "</ul>";
        }

        if (!empty($results['error_messages'])) {
            $html .= "<h4>Errors:</h4>";
            foreach ($results['error_messages'] as $error) {
                $html .= "<div class='error'>{$error}</div>";
            }
        }

        $html .= "</div>";
        return $html;
    }

    /**
     * Generate processing-specific content
     */
    private function generateProcessingContent(array $data): string
    {
        $results = $data['results'];

        $html = "<div class='details'>";
        $html .= "<h3>Processing Summary</h3>";
        $html .= "<table>";
        $html .= "<tr><th>Metric</th><th>Count</th></tr>";
        $html .= "<tr><td>Files Processed</td><td>" . ($results['processed'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files Skipped</td><td>" . ($results['skipped'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files Failed</td><td>" . ($results['failed'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Transactions Matched</td><td>" . ($results['total_matched'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Transactions Unmatched</td><td>" . ($results['total_unmatched'] ?? 0) . "</td></tr>";
        $html .= "</table>";

        if (isset($results['total_matched']) && isset($results['total_unmatched'])) {
            $total = $results['total_matched'] + $results['total_unmatched'];
            if ($total > 0) {
                $matchRate = round(($results['total_matched'] / $total) * 100, 2);
                $html .= "<p><strong>Match Rate:</strong> {$matchRate}%</p>";
            }
        }

        if (!empty($results['error_messages'])) {
            $html .= "<h4>Errors:</h4>";
            foreach ($results['error_messages'] as $error) {
                $html .= "<div class='error'>{$error}</div>";
            }
        }

        $html .= "</div>";
        return $html;
    }

    /**
     * Generate matching-specific content
     */
    private function generateMatchingContent(array $data): string
    {
        $results = $data['results'];

        $html = "<div class='details'>";
        $html .= "<h3>Matching Summary</h3>";
        $html .= "<table>";
        $html .= "<tr><th>Metric</th><th>Count</th></tr>";
        $html .= "<tr><td>Files Processed</td><td>" . ($results['files_processed'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Transactions Matched</td><td>" . ($results['total_matched'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Transactions Unmatched</td><td>" . ($results['total_unmatched'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files with Errors</td><td>" . ($results['total_errors'] ?? 0) . "</td></tr>";
        $html .= "</table>";

        if (isset($results['total_matched']) && isset($results['total_unmatched'])) {
            $total = $results['total_matched'] + $results['total_unmatched'];
            if ($total > 0) {
                $matchRate = round(($results['total_matched'] / $total) * 100, 2);
                $html .= "<p><strong>Overall Match Rate:</strong> {$matchRate}%</p>";
            }
        }

        if (!empty($results['error_messages'])) {
            $html .= "<h4>Errors:</h4>";
            foreach ($results['error_messages'] as $error) {
                $html .= "<div class='error'>{$error}</div>";
            }
        }

        $html .= "</div>";
        return $html;
    }

    /**
     * Generate health check content
     */
    private function generateHealthContent(array $data): string
    {
        $html = "<div class='details'>";
        $html .= "<h3>System Health Issues</h3>";

        if (!empty($data['issues'])) {
            foreach ($data['issues'] as $issue) {
                $html .= "<div class='error'>{$issue}</div>";
            }
        }

        $html .= "</div>";
        return $html;
    }

    /**
     * Check if notifications should be sent (now uses the new environment check)
     */
    private function shouldSendNotifications(): bool
    {
        return $this->isNotificationsEnabled();
    }

    /**
     * Get notification recipients
     */
    private function getNotificationRecipients(): array
    {
        $recipients = config('decta.notifications.recipients', []);

        // Fallback to admin email if no specific recipients configured
        if (empty($recipients)) {
            $adminEmail = config('app.admin_email');
            if ($adminEmail) {
                $recipients = [$adminEmail];
            }
        }

        return array_filter($recipients);
    }

    /**
     * Send test notification (respects environment restrictions)
     */
    public function sendTestNotification(): bool
    {
        if (!$this->isNotificationsEnabled()) {
            Log::info('Test notification skipped', [
                'reason' => !config('decta.notifications.enabled', true) ? 'notifications disabled' : 'environment not allowed',
                'environment' => app()->environment(),
                'allowed_environments' => $this->getAllowedEnvironments()
            ]);
            return false;
        }

        try {
            $data = [
                'process_type' => 'Test',
                'success' => true,
                'results' => [
                    'message' => 'This is a test notification to verify email configuration.',
                    'test_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'environment_note' => 'Emails are only sent in: ' . implode(', ', $this->getAllowedEnvironments())
                ],
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                'server' => config('app.url', 'Unknown Server'),
                'environment' => app()->environment(),
            ];

            $this->sendEmail('🧪 Decta Notification Test', 'decta-test-notification', $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send test notification', [
                'error' => $e->getMessage(),
                'environment' => app()->environment()
            ]);
            return false;
        }
    }

    /**
     * Get the current environment status for notifications
     */
    public function getNotificationStatus(): array
    {
        return [
            'notifications_enabled' => config('decta.notifications.enabled', true),
            'current_environment' => app()->environment(),
            'allowed_environments' => $this->getAllowedEnvironments(),
            'environment_allowed' => $this->isEnvironmentAllowedForEmails(),
            'overall_enabled' => $this->isNotificationsEnabled(),
            'recipients_count' => count($this->getRecipients())
        ];
    }
}
