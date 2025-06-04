<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Services\DectaNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DectaCheckDeclinedTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:check-declined-transactions
                            {--date= : Specific date to check (Y-m-d format, defaults to yesterday)}
                            {--days-back=1 : Number of days back to check (default: 1)}
                            {--force : Send notification even if no new declined transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for declined transactions and send email notifications if found';

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
            $this->info('Starting declined transactions check...');

            // Determine the date range to check
            $dateRange = $this->getDateRange();
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            $this->info("Checking for declined transactions from {$startDate} to {$endDate}");

            // Get declined transactions for the specified period
            $declinedTransactions = $this->getDeclinedTransactions($startDate, $endDate);

            if ($declinedTransactions->isEmpty() && !$this->option('force')) {
                $this->info('No declined transactions found for the specified period.');
                return self::SUCCESS;
            }

            $declinedCount = $declinedTransactions->count();
            $this->info("Found {$declinedCount} declined transactions.");

            // Prepare summary data
            $summaryData = $this->prepareSummaryData($declinedTransactions, $startDate, $endDate);

            // Send notification
            $this->sendNotification($summaryData);

            $this->info('Declined transactions notification sent successfully.');

            // Log the event
            Log::info('Declined transactions check completed', [
                'date_range' => $dateRange,
                'declined_count' => $declinedCount,
                'notification_sent' => true
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to check declined transactions: ' . $e->getMessage());

            Log::error('Declined transactions check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Try to send error notification
            try {
                $this->notificationService->sendErrorNotification(
                    'Declined Transactions Check Failed',
                    'The daily declined transactions check failed with error: ' . $e->getMessage(),
                    ['error_details' => $e->getMessage()]
                );
            } catch (\Exception $notificationError) {
                $this->error('Failed to send error notification: ' . $notificationError->getMessage());
            }

            return self::FAILURE;
        }
    }

    /**
     * Get the date range to check based on command options
     */
    protected function getDateRange(): array
    {
        if ($this->option('date')) {
            $specificDate = Carbon::createFromFormat('Y-m-d', $this->option('date'));
            return [
                'start' => $specificDate->startOfDay(),
                'end' => $specificDate->endOfDay()
            ];
        }

        $daysBack = (int) $this->option('days-back');
        $endDate = Carbon::yesterday()->endOfDay();
        $startDate = Carbon::yesterday()->subDays($daysBack - 1)->startOfDay();

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    /**
     * Get declined transactions for the specified date range
     */
    protected function getDeclinedTransactions(Carbon $startDate, Carbon $endDate)
    {
        return DectaTransaction::where(function ($query) {
            $query->where('gateway_transaction_status', 'declined')
                ->orWhere('gateway_transaction_status', 'DECLINED');
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('dectaFile')
            ->orderBy('tr_date_time', 'desc')
            ->get();
    }

    /**
     * Prepare summary data for the notification
     */
    protected function prepareSummaryData($declinedTransactions, Carbon $startDate, Carbon $endDate): array
    {
        $totalAmount = $declinedTransactions->sum('tr_amount') / 100; // Convert from cents

        // Group by currency
        $currencyBreakdown = $declinedTransactions->groupBy('tr_ccy')->map(function ($transactions, $currency) {
            return [
                'currency' => $currency ?: 'Unknown',
                'count' => $transactions->count(),
                'total_amount' => $transactions->sum('tr_amount') / 100
            ];
        })->values()->toArray();

        // Group by merchant
        $merchantBreakdown = $declinedTransactions->groupBy('merchant_name')->map(function ($transactions, $merchantName) {
            return [
                'merchant_name' => $merchantName ?: 'Unknown',
                'merchant_id' => $transactions->first()->merchant_id,
                'count' => $transactions->count(),
                'total_amount' => $transactions->sum('tr_amount') / 100,
                'currencies' => $transactions->pluck('tr_ccy')->unique()->filter()->values()->toArray()
            ];
        })->sortByDesc('count')->values()->toArray();

        // Get decline reasons (error messages)
        $declineReasons = $declinedTransactions->whereNotNull('error_message')
            ->groupBy('error_message')
            ->map(function ($transactions, $reason) {
                return [
                    'reason' => $reason,
                    'count' => $transactions->count()
                ];
            })
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();

        // Recent declined transactions for details
        $recentTransactions = $declinedTransactions->take(20)->map(function ($transaction) {
            return [
                'payment_id' => $transaction->payment_id,
                'merchant_name' => $transaction->merchant_name,
                'amount' => $transaction->tr_amount / 100,
                'currency' => $transaction->tr_ccy,
                'transaction_date' => $transaction->tr_date_time ? $transaction->tr_date_time->format('Y-m-d H:i:s') : null,
                'error_message' => $transaction->error_message,
                'gateway_transaction_id' => $transaction->gateway_transaction_id
            ];
        })->toArray();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days_checked' => $startDate->diffInDays($endDate) + 1
            ],
            'summary' => [
                'total_declined' => $declinedTransactions->count(),
                'total_amount' => $totalAmount,
                'unique_merchants' => $declinedTransactions->unique('merchant_id')->count(),
                'unique_currencies' => $declinedTransactions->unique('tr_ccy')->count()
            ],
            'currency_breakdown' => $currencyBreakdown,
            'merchant_breakdown' => array_slice($merchantBreakdown, 0, 10), // Top 10 merchants
            'decline_reasons' => $declineReasons,
            'recent_transactions' => $recentTransactions,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Send the notification email
     */
    protected function sendNotification(array $summaryData): void
    {
        $subject = sprintf(
            'Declined Transactions Alert - %d transactions on %s',
            $summaryData['summary']['total_declined'],
            $summaryData['period']['start_date']
        );

        $this->notificationService->sendDeclinedTransactionsNotification($subject, $summaryData);
    }
}
