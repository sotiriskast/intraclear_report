<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\DectaNotificationService;
use Carbon\Carbon;

class DectaTestDeclinedNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:test-declined-notification
                            {--empty : Test with empty/no data}
                            {--email= : Send to specific email instead of configured recipients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test declined transactions email notification with sample data';

    /**
     * The notification service instance.
     *
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
    public function handle(): int
    {
        try {
            $this->info('Testing declined transactions notification...');

            if ($this->option('empty')) {
                $summaryData = $this->createEmptyTestData();
                $subject = 'TEST: No Declined Transactions Found';
            } else {
                $summaryData = $this->createSampleTestData();
                $subject = 'TEST: Declined Transactions Alert - ' . $summaryData['summary']['total_declined'] . ' transactions';
            }

            // Override recipients if specific email provided
            $originalRecipients = config('decta.notifications.recipients');
            if ($this->option('email')) {
                config(['decta.notifications.recipients' => [$this->option('email')]]);
                $this->info('Sending test notification to: ' . $this->option('email'));
            } else {
                $this->info('Sending test notification to configured recipients: ' . implode(', ', $originalRecipients));
            }

            $this->notificationService->sendDeclinedTransactionsNotification($subject, $summaryData);

            // Restore original recipients
            if ($this->option('email')) {
                config(['decta.notifications.recipients' => $originalRecipients]);
            }

            $this->info('Test declined transactions notification sent successfully!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to send test notification: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Create sample test data for the notification
     */
    protected function createSampleTestData(): array
    {
        return [
            'period' => [
                'start_date' => Carbon::yesterday()->format('Y-m-d'),
                'end_date' => Carbon::yesterday()->format('Y-m-d'),
                'days_checked' => 1
            ],
            'summary' => [
                'total_declined' => 15,
                'total_amount' => 2847.50,
                'unique_merchants' => 5,
                'unique_currencies' => 3
            ],
            'currency_breakdown' => [
                [
                    'currency' => 'EUR',
                    'count' => 8,
                    'total_amount' => 1650.00
                ],
                [
                    'currency' => 'USD',
                    'count' => 5,
                    'total_amount' => 897.50
                ],
                [
                    'currency' => 'GBP',
                    'count' => 2,
                    'total_amount' => 300.00
                ]
            ],
            'merchant_breakdown' => [
                [
                    'merchant_name' => 'Test Merchant Alpha',
                    'merchant_id' => 'MERCH001',
                    'count' => 6,
                    'total_amount' => 1200.00,
                    'currencies' => ['EUR', 'USD']
                ],
                [
                    'merchant_name' => 'Beta Commerce Ltd',
                    'merchant_id' => 'MERCH002',
                    'count' => 4,
                    'total_amount' => 850.00,
                    'currencies' => ['EUR']
                ],
                [
                    'merchant_name' => 'Gamma Retail',
                    'merchant_id' => 'MERCH003',
                    'count' => 3,
                    'total_amount' => 547.50,
                    'currencies' => ['USD', 'GBP']
                ],
                [
                    'merchant_name' => 'Delta Services',
                    'merchant_id' => 'MERCH004',
                    'count' => 2,
                    'total_amount' => 250.00,
                    'currencies' => ['GBP']
                ]
            ],
            'decline_reasons' => [
                [
                    'reason' => 'Insufficient funds',
                    'count' => 6
                ],
                [
                    'reason' => 'Invalid card number',
                    'count' => 4
                ],
                [
                    'reason' => 'Card expired',
                    'count' => 3
                ],
                [
                    'reason' => 'Transaction declined by issuer',
                    'count' => 2
                ]
            ],
            'recent_transactions' => [
                [
                    'payment_id' => 'PAY_TEST_001',
                    'merchant_name' => 'Test Merchant Alpha',
                    'amount' => 125.50,
                    'currency' => 'EUR',
                    'transaction_date' => Carbon::yesterday()->format('Y-m-d H:i:s'),
                    'error_message' => 'Insufficient funds',
                    'gateway_transaction_id' => 'GTW_123456'
                ],
                [
                    'payment_id' => 'PAY_TEST_002',
                    'merchant_name' => 'Beta Commerce Ltd',
                    'amount' => 89.99,
                    'currency' => 'USD',
                    'transaction_date' => Carbon::yesterday()->subHours(2)->format('Y-m-d H:i:s'),
                    'error_message' => 'Invalid card number',
                    'gateway_transaction_id' => 'GTW_123457'
                ],
                [
                    'payment_id' => 'PAY_TEST_003',
                    'merchant_name' => 'Gamma Retail',
                    'amount' => 300.00,
                    'currency' => 'GBP',
                    'transaction_date' => Carbon::yesterday()->subHours(4)->format('Y-m-d H:i:s'),
                    'error_message' => 'Card expired',
                    'gateway_transaction_id' => 'GTW_123458'
                ],
                [
                    'payment_id' => 'PAY_TEST_004',
                    'merchant_name' => 'Test Merchant Alpha',
                    'amount' => 75.25,
                    'currency' => 'EUR',
                    'transaction_date' => Carbon::yesterday()->subHours(6)->format('Y-m-d H:i:s'),
                    'error_message' => 'Transaction declined by issuer',
                    'gateway_transaction_id' => 'GTW_123459'
                ],
                [
                    'payment_id' => 'PAY_TEST_005',
                    'merchant_name' => 'Delta Services',
                    'amount' => 150.00,
                    'currency' => 'USD',
                    'transaction_date' => Carbon::yesterday()->subHours(8)->format('Y-m-d H:i:s'),
                    'error_message' => 'Insufficient funds',
                    'gateway_transaction_id' => 'GTW_123460'
                ]
            ],
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Create empty test data (no declined transactions found)
     */
    protected function createEmptyTestData(): array
    {
        return [
            'period' => [
                'start_date' => Carbon::yesterday()->format('Y-m-d'),
                'end_date' => Carbon::yesterday()->format('Y-m-d'),
                'days_checked' => 1
            ],
            'summary' => [
                'total_declined' => 0,
                'total_amount' => 0,
                'unique_merchants' => 0,
                'unique_currencies' => 0
            ],
            'currency_breakdown' => [],
            'merchant_breakdown' => [],
            'decline_reasons' => [],
            'recent_transactions' => [],
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s')
        ];
    }
}
