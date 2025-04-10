<?php

namespace App\Console\Commands;

use App\Mail\MerchantSyncFailed;
use App\Mail\NewMerchantCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMerchantEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-merchant
                            {type : The type of email to test (sync-failed or new-merchant)}
                            {--recipient= : The email address to send to (defaults to admin_email from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test merchant-related email notifications';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->argument('type');
        $recipient = $this->option('recipient') ?? config('app.admin_email');

        // Validate email address
        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid or missing recipient email address!');
            $this->line('Please specify a valid email with --recipient or set APP_ADMIN_EMAIL in your .env file.');
            return 1;
        }

        $this->info("Sending test email to: {$recipient}");

        switch ($type) {
            case 'sync-failed':
                $this->sendSyncFailedEmail($recipient);
                break;

            case 'new-merchant':
                $this->sendNewMerchantEmail($recipient);
                break;

            default:
                $this->error("Invalid email type: {$type}");
                $this->line("Available types: sync-failed, new-merchant");
                return 1;
        }

        return 0;
    }

    /**
     * Send a test merchant sync failed email.
     *
     * @param string $recipient
     * @return void
     */
    private function sendSyncFailedEmail(string $recipient): void
    {
        $errorMessage = 'This is a test error message for merchant sync failure';
        $stackTrace = "Exception: Test Exception\n at MerchantSyncService.php:123\n at SyncController.php:45";

        Mail::to($recipient)
            ->send(new MerchantSyncFailed($errorMessage, $stackTrace));

        $this->info('✓ Merchant sync failed test email sent!');
    }

    /**
     * Send a test new merchant created email.
     *
     * @param string $recipient
     * @return void
     */
    private function sendNewMerchantEmail(string $recipient): void
    {
        // Create a sample merchant object similar to what would come from the database
        $merchant = (object) [
            'id' => 'ACC_' . rand(10000, 99999),
            'corp_name' => 'Test Merchant Corp',
            'email' => 'test@testmerchant.com',
            'phone' => '555-123-4567',
            'active' => true
        ];

        $merchantId = rand(1, 1000); // Simulate internal merchant ID

        Mail::to($recipient)
            ->send(new NewMerchantCreated($merchant, $merchantId));

        $this->info('✓ New merchant created test email sent!');
        $this->line("Merchant ID: {$merchantId}");
        $this->line("Account ID: {$merchant->id}");
    }
}
