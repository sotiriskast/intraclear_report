<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class VisaNotificationService
{
    /**
     * Email sending throttle settings
     */
    protected array $throttleSettings = [
        'max_emails_per_minute' => 10,
        'delay_between_emails' => 6, // seconds
    ];

    /**
     * Get notification recipients from config
     */
    protected function getRecipients(): array
    {
        // Use Decta recipients as Visa is part of the Decta module
        $recipients = config('decta.notifications.recipients', []);
        return array_filter($recipients); // Remove empty values
    }

    /**
     * Check if notifications are enabled and environment allows emails
     */
    protected function isNotificationsEnabled(): bool
    {
        // Use Decta main notification settings since Visa is part of Decta module
        $configEnabled = config('decta.notifications.enabled', true);
        $environmentAllowed = $this->isEnvironmentAllowedForEmails();

        if (!$environmentAllowed) {
            Log::info('Visa email notifications skipped - environment not allowed', [
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
        // Use Decta environment settings since Visa is part of Decta module
        $allowedEnvs = config('decta.notifications.allowed_environments', 'staging,production,prod');

        // If it's a string (from env), convert to array
        if (is_string($allowedEnvs)) {
            $allowedEnvs = array_map('trim', explode(',', $allowedEnvs));
        }

        // Filter out empty values
        return array_filter($allowedEnvs);
    }

    /**
     * Send notification for SMS download process
     */
    public function sendSmsDownloadNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Visa SMS Download Completed Successfully"
            : "❌ Visa SMS Download Failed";

        $data = [
            'process_type' => 'Visa SMS Download',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmailWithThrottling($subject, 'visa-sms-download-notification', $data);
    }

    /**
     * Send notification for SMS processing
     */
    public function sendSmsProcessingNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Visa SMS Processing Completed Successfully"
            : "❌ Visa SMS Processing Failed";

        $data = [
            'process_type' => 'Visa SMS Processing',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmailWithThrottling($subject, 'visa-sms-processing-notification', $data);
    }

    /**
     * Send notification for Issues download process
     */
    public function sendIssuesDownloadNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Visa Issues Download Completed Successfully"
            : "❌ Visa Issues Download Failed";

        $data = [
            'process_type' => 'Visa Issues Download',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmailWithThrottling($subject, 'visa-issues-download-notification', $data);
    }

    /**
     * Send notification for Issues processing
     */
    public function sendIssuesProcessingNotification(array $results, bool $success = true): void
    {
        if (!$this->shouldSendNotifications()) {
            return;
        }

        $subject = $success
            ? "✅ Visa Issues Processing Completed Successfully"
            : "❌ Visa Issues Processing Failed";

        $data = [
            'process_type' => 'Visa Issues Processing',
            'success' => $success,
            'results' => $results,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            'server' => config('app.url', 'Unknown Server'),
            'environment' => app()->environment(),
        ];

        $this->sendEmailWithThrottling($subject, 'visa-issues-processing-notification', $data);
    }

    /**
     * Send email with throttling to avoid rate limits
     */
    private function sendEmailWithThrottling(string $subject, string $view, array $data): void
    {
        try {
            // Check if we should throttle emails
            $this->applyEmailThrottling();

            $this->sendEmail($subject, $view, $data);

        } catch (Exception $e) {
            // Check if it's a rate limiting error
            if ($this->isRateLimitError($e)) {
                Log::warning('Email rate limit detected, retrying with delay', [
                    'subject' => $subject,
                    'error' => $e->getMessage(),
                    'environment' => app()->environment()
                ]);

                // Wait longer and retry once
                sleep($this->throttleSettings['delay_between_emails'] * 2);

                try {
                    $this->sendEmail($subject, $view, $data);
                } catch (Exception $retryException) {
                    Log::error('Failed to send Visa notification email after retry', [
                        'error' => $retryException->getMessage(),
                        'subject' => $subject,
                        'data' => $data,
                        'environment' => app()->environment()
                    ]);
                }
            } else {
                throw $e; // Re-throw non-rate-limit errors
            }
        }
    }

    /**
     * Apply email throttling to avoid rate limits
     */
    private function applyEmailThrottling(): void
    {
        static $lastEmailTime = null;
        static $emailCount = 0;
        static $windowStart = null;

        $now = time();

        // Initialize window if first email or window expired
        if ($windowStart === null || ($now - $windowStart) >= 60) {
            $windowStart = $now;
            $emailCount = 0;
        }

        // Check if we've hit the rate limit
        if ($emailCount >= $this->throttleSettings['max_emails_per_minute']) {
            $waitTime = 60 - ($now - $windowStart) + 1;
            Log::info("Email rate limit reached, waiting {$waitTime} seconds", [
                'emails_sent' => $emailCount,
                'window_start' => $windowStart
            ]);
            sleep($waitTime);

            // Reset window
            $windowStart = time();
            $emailCount = 0;
        }

        // Ensure minimum delay between emails
        if ($lastEmailTime !== null) {
            $timeSinceLastEmail = $now - $lastEmailTime;
            if ($timeSinceLastEmail < $this->throttleSettings['delay_between_emails']) {
                $sleepTime = $this->throttleSettings['delay_between_emails'] - $timeSinceLastEmail;
                sleep($sleepTime);
            }
        }

        $lastEmailTime = time();
        $emailCount++;
    }

    /**
     * Check if exception is a rate limiting error
     */
    private function isRateLimitError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        $rateLimitIndicators = [
            'too many emails',
            'rate limit',
            'quota exceeded',
            '550 5.7.0',
            'per second',
            'per minute'
        ];

        foreach ($rateLimitIndicators as $indicator) {
            if (str_contains($message, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send generic email notification
     */
    private function sendEmail(string $subject, string $view, array $data): void
    {
        try {
            $recipients = $this->getNotificationRecipients();

            if (empty($recipients)) {
                Log::warning('No email recipients configured for Visa notifications');
                return;
            }

            // Send using Laravel's Mail facade with HTML content
            Mail::send([], [], function ($message) use ($subject, $recipients, $data) {
                $message->to($recipients)
                    ->subject($subject)
                    ->html($this->generateEmailHtml($data));
            });

            Log::info('Visa notification email sent', [
                'subject' => $subject,
                'recipients' => $recipients,
                'process_type' => $data['process_type'] ?? 'Unknown',
                'environment' => app()->environment()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send Visa notification email', [
                'error' => $e->getMessage(),
                'subject' => $subject,
                'data' => $data,
                'environment' => app()->environment()
            ]);
            throw $e; // Re-throw to allow calling code to handle
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
                .throttle-notice { background: #FFF3CD; border: 1px solid #FFEAA7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='status'>{$statusIcon} {$data['process_type']} - {$status}</div>
                    <div class='timestamp'>Server: {$data['server']}</div>
                    <div class='timestamp'>Time: {$data['timestamp']}</div>
                    <div class='timestamp'>Environment: <span class='environment'>{$environment}</span></div>
                </div>
                <div class='content'>
        ";

        // Add throttling notice for development environments
        if (in_array($environment, ['local', 'dev', 'testing'])) {
            $html .= "
                <div class='throttle-notice'>
                    <strong>📧 Development Notice:</strong> This email was sent with rate limiting to prevent spam.
                    In production, emails are throttled to {$this->throttleSettings['max_emails_per_minute']} per minute.
                </div>
            ";
        }

        // Add process-specific content
        if (str_contains($data['process_type'], 'Download')) {
            $html .= $this->generateDownloadContent($data);
        } elseif (str_contains($data['process_type'], 'Processing')) {
            $html .= $this->generateProcessingContent($data);
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
        $html .= "<tr><td>Files Found</td><td>" . ($results['files_found'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files Downloaded</td><td>" . ($results['files_downloaded'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files Skipped</td><td>" . ($results['files_skipped'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Errors</td><td>" . ($results['errors'] ?? 0) . "</td></tr>";

        if (!empty($results['files_processed'])) {
            $html .= "<tr><td>Files Processed</td><td>" . $results['files_processed'] . "</td></tr>";
        }

        $html .= "</table>";

        if (!empty($results['details'])) {
            $html .= "<h4>File Details:</h4>";
            foreach ($results['details'] as $detail) {
                $status = '❌ Failed';
                if ($detail['downloaded'] ?? false) {
                    $status = '✅ Downloaded';
                } elseif ($detail['skipped'] ?? false) {
                    $status = '⏭️ Skipped';
                }

                $html .= "<p><strong>{$detail['filename']}</strong> - {$status}</p>";
                if (!empty($detail['message'])) {
                    $html .= "<p style='margin-left: 20px; color: #666;'>{$detail['message']}</p>";
                }
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
     * Generate processing-specific content
     */
    private function generateProcessingContent(array $data): string
    {
        $results = $data['results'];

        $html = "<div class='details'>";
        $html .= "<h3>Processing Summary</h3>";
        $html .= "<table>";
        $html .= "<tr><th>Metric</th><th>Count</th></tr>";
        $html .= "<tr><td>Files Processed</td><td>" . ($results['files_processed'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Files Failed</td><td>" . ($results['files_failed'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Total Transactions Updated</td><td>" . ($results['total_transactions_updated'] ?? 0) . "</td></tr>";
        $html .= "</table>";

        if (!empty($results['details'])) {
            $html .= "<h4>File Processing Details:</h4>";
            foreach ($results['details'] as $detail) {
                $html .= "<p><strong>{$detail['filename']}</strong></p>";

                if ($detail['success'] ?? false) {
                    $updated = $detail['updated_count'] ?? 0;
                    $notFound = $detail['not_found_count'] ?? 0;
                    $errors = $detail['error_count'] ?? 0;
                    $html .= "<ul style='margin-left: 20px;'>";
                    $html .= "<li>✅ Updated: {$updated} transactions</li>";
                    if ($notFound > 0) {
                        $html .= "<li>ℹ️ Not found: {$notFound} transactions</li>";
                    }
                    if ($errors > 0) {
                        $html .= "<li>⚠️ Errors: {$errors} transactions</li>";
                    }
                    $html .= "</ul>";
                } else {
                    $error = $detail['error'] ?? 'Unknown error';
                    $html .= "<p style='margin-left: 20px; color: #EF4444;'>❌ {$error}</p>";
                }
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
     * Check if notifications should be sent
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
        $recipients = $this->getRecipients();

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
     * Send test notification with rate limiting protection
     */
    public function sendTestNotification(string $type = 'sms'): bool
    {
        if (!$this->isNotificationsEnabled()) {
            Log::info('Visa test notification skipped', [
                'reason' => !config('decta.notifications.enabled', true) ? 'notifications disabled' : 'environment not allowed',
                'environment' => app()->environment(),
                'allowed_environments' => $this->getAllowedEnvironments()
            ]);
            return false;
        }

        try {
            $data = [
                'process_type' => 'Visa ' . ucfirst($type) . ' Test',
                'success' => true,
                'results' => [
                    'message' => 'This is a test notification to verify Visa email configuration.',
                    'test_time' => Carbon::now()->format('Y-m-d H:i:s'),
                    'environment_note' => 'Emails are only sent in: ' . implode(', ', $this->getAllowedEnvironments()),
                    'throttle_info' => "Rate limited to {$this->throttleSettings['max_emails_per_minute']} emails per minute"
                ],
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                'server' => config('app.url', 'Unknown Server'),
                'environment' => app()->environment(),
            ];

            $this->sendEmailWithThrottling('🧪 Visa ' . ucfirst($type) . ' Notification Test', 'visa-test-notification', $data);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send Visa test notification', [
                'error' => $e->getMessage(),
                'type' => $type,
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
            'recipients_count' => count($this->getRecipients()),
            'visa_sms_enabled' => config('decta.visa_sms.notifications.enabled', true),
            'visa_issues_enabled' => config('decta.visa_issues.notifications.enabled', false),
            'throttle_settings' => $this->throttleSettings,
            'mail_driver' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
        ];
    }

    /**
     * Update throttle settings for specific environments
     */
    public function configureThrottling(array $settings = []): void
    {
        $defaults = [
            'max_emails_per_minute' => 10,
            'delay_between_emails' => 6,
        ];

        // Adjust for development environments
        if (app()->environment(['local', 'dev', 'testing'])) {
            $defaults['max_emails_per_minute'] = 5;
            $defaults['delay_between_emails'] = 10;
        }

        $this->throttleSettings = array_merge($defaults, $settings);

        Log::info('Email throttling configured', [
            'settings' => $this->throttleSettings,
            'environment' => app()->environment()
        ]);
    }
}
