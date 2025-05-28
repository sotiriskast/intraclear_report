<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Decta\Services\DectaTransactionService;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class DectaMatchTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'decta:match-transactions
                            {--file-id= : Match transactions for a specific file}
                            {--limit=100 : Limit number of transactions to process}
                            {--retry-failed : Retry previously failed matches}
                            {--max-attempts=3 : Maximum matching attempts per transaction}
                            {--force : Force re-matching of already matched transactions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Match Decta transactions with payment gateway database';

    /**
     * @var DectaTransactionService
     */
    protected $transactionService;

    /**
     * @var DectaTransactionRepository
     */
    protected $transactionRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DectaTransactionService $transactionService,
        DectaTransactionRepository $transactionRepository
    ) {
        parent::__construct();
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Decta transaction matching process...');

        try {
            // Check system health
            if (!$this->checkSystemHealth()) {
                $this->error('System health check failed. Aborting matching process.');
                return 1;
            }

            $fileId = $this->option('file-id') ? (int) $this->option('file-id') : null;
            $limit = (int) $this->option('limit');
            $retryFailed = $this->option('retry-failed');
            $maxAttempts = (int) $this->option('max-attempts');
            $force = $this->option('force');

            // Get transactions to process
            $transactions = $this->getTransactionsToProcess($fileId, $limit, $retryFailed, $maxAttempts, $force);

            if ($transactions->isEmpty()) {
                $this->info('No transactions found to match.');
                return 0;
            }

            $this->info(sprintf('Found %d transaction(s) to match.', $transactions->count()));

            // Display initial statistics
            $this->displayInitialStats($fileId);

            $matchedCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            $progressBar = $this->output->createProgressBar($transactions->count());
            $progressBar->start();

            foreach ($transactions as $transaction) {
                try {
                    $result = $this->processTransaction($transaction, $maxAttempts);

                    switch ($result['status']) {
                        case 'matched':
                            $matchedCount++;
                            break;
                        case 'failed':
                            $failedCount++;
                            break;
                        case 'skipped':
                            $skippedCount++;
                            break;
                    }

                } catch (Exception $e) {
                    $failedCount++;
                    Log::error('Error processing transaction for matching', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->payment_id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display results
            $this->displayResults($matchedCount, $failedCount, $skippedCount, $fileId);

            return $failedCount > 0 ? 1 : 0;

        } catch (Exception $e) {
            $this->error("Matching process failed: {$e->getMessage()}");
            Log::error('Decta transaction matching failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Check system health before processing
     */
    private function checkSystemHealth(): bool
    {
        try {
            // Test payment gateway database connection
            DB::connection('payment_gateway_mysql')->getPdo();
            $this->line('✓ Payment gateway database connection OK');

            // Test main database connection
            DB::connection()->getPdo();
            $this->line('✓ Main database connection OK');

            return true;
        } catch (Exception $e) {
            $this->error("Database connection failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get transactions to process based on options
     */
    private function getTransactionsToProcess(
        ?int $fileId,
        int $limit,
        bool $retryFailed,
        int $maxAttempts,
        bool $force
    ) {
        if ($force) {
            // Get all transactions regardless of status
            $query = DectaTransaction::query();

            if ($fileId) {
                $query->where('decta_file_id', $fileId);
            }

            return $query->limit($limit)->get();
        }

        if ($retryFailed) {
            // Get transactions that need re-matching
            return $this->transactionRepository->getForReMatching($maxAttempts);
        }

        // Get unmatched transactions
        return $this->transactionRepository->getUnmatched($fileId, $limit);
    }

    /**
     * Process individual transaction for matching
     */
    private function processTransaction(DectaTransaction $transaction, int $maxAttempts): array
    {
        // Check if transaction has exceeded max attempts
        $attempts = $transaction->matching_attempts ?? [];

        if (count($attempts) >= $maxAttempts && !$this->option('force')) {
            return ['status' => 'skipped', 'reason' => 'max_attempts_reached'];
        }

        // Mark as processing
        $transaction->markAsProcessing();

        // Attempt to find matching gateway transaction
        $gatewayData = $this->findMatchingGatewayTransaction($transaction);

        if ($gatewayData) {
            // Match found - store all gateway information
            $transaction->markAsMatched($gatewayData);

            // Display detailed match information
            $this->line(" ✓ Matched: {$transaction->payment_id}");
            $this->line("   → Gateway TID: {$gatewayData['trx_id']}");
            $this->line("   → Account ID: {$gatewayData['account_id']}");
            $this->line("   → Shop ID: {$gatewayData['shop_id']}");

            if (isset($gatewayData['transaction_date'])) {
                $this->line("   → Gateway Date: {$gatewayData['transaction_date']}");
            }

            if (isset($gatewayData['bank_amount']) && isset($gatewayData['bank_currency'])) {
                $amount = $gatewayData['bank_amount'] / 100; // Convert from cents
                $this->line("   → Gateway Amount: {$amount} {$gatewayData['bank_currency']}");
            }

            // Log comprehensive match details
            Log::info('Transaction matched successfully with full gateway data', [
                'decta_payment_id' => $transaction->payment_id,
                'decta_transaction_id' => $transaction->id,
                'gateway_data_stored' => [
                    'gateway_transaction_id' => $gatewayData['transaction_id'],
                    'gateway_account_id' => $gatewayData['account_id'],
                    'gateway_shop_id' => $gatewayData['shop_id'],
                    'gateway_trx_id' => $gatewayData['trx_id'],
                    'gateway_transaction_date' => $gatewayData['transaction_date'] ?? null,
                    'gateway_bank_response_date' => $gatewayData['bank_response_date'] ?? null,
                ],
                'matching_criteria_used' => $this->getSearchCriteria($transaction),
            ]);

            return ['status' => 'matched', 'gateway_data' => $gatewayData];

        } else {
            // No match found - add detailed attempt information
            $searchCriteria = $this->getSearchCriteria($transaction);

            $transaction->addMatchingAttempt([
                'strategy' => 'comprehensive_search',
                'search_criteria' => $searchCriteria,
                'searched_fields' => [
                    'approval_id' => !empty($searchCriteria['approval_id']),
                    'ret_ref_nr' => !empty($searchCriteria['ret_ref_nr']),
                    'amount' => !empty($searchCriteria['amount']),
                    'currency' => !empty($searchCriteria['currency']),
                    'date' => !empty($searchCriteria['transaction_date']),
                ],
                'result' => 'no_match_found',
                'attempted_at' => Carbon::now()->toISOString(),
            ]);

            $currentAttempts = count($transaction->matching_attempts);

            if ($currentAttempts >= $maxAttempts) {
                $transaction->markAsFailed('No matching gateway transaction found after ' . $maxAttempts . ' attempts');
                $this->line(" ✗ Failed: {$transaction->payment_id} (max attempts reached)");

                // Show what we searched for
                $this->line("   → Search criteria used:");
                if (!empty($searchCriteria['approval_id'])) {
                    $this->line("     - Approval ID: {$searchCriteria['approval_id']}");
                }
                if (!empty($searchCriteria['ret_ref_nr'])) {
                    $this->line("     - Return Ref: {$searchCriteria['ret_ref_nr']}");
                }
                if (!empty($searchCriteria['amount'])) {
                    $amount = $searchCriteria['amount'] / 100;
                    $currency = $searchCriteria['currency'] ?? 'N/A';
                    $this->line("     - Amount: {$amount} {$currency}");
                }
                if (!empty($searchCriteria['transaction_date'])) {
                    $date = Carbon::parse($searchCriteria['transaction_date'])->format('Y-m-d H:i:s');
                    $this->line("     - Date: {$date}");
                }

            } else {
                $this->line(" ! No match: {$transaction->payment_id} (attempt {$currentAttempts}/{$maxAttempts})");
            }

            return ['status' => 'failed', 'attempts' => $currentAttempts];
        }
    }
    /**
     * Find matching transaction in payment gateway
     */
    private function findMatchingGatewayTransaction(DectaTransaction $transaction): ?array
    {
        try {
            $searchCriteria = $this->getSearchCriteria($transaction);

            // Tertiary search: amount + date + currency
            if (!empty($searchCriteria['amount']) && !empty($searchCriteria['transaction_date'])) {
                $match = $this->searchByAmountAndDate($searchCriteria);
                if ($match) {
                    return $match;
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error('Error in gateway transaction search', [
                'payment_id' => $transaction->payment_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }


    /**
     * Search by amount and date
     */
    private function searchByAmountAndDate(array $criteria): ?array
    {
        $date = $criteria['transaction_date']->toDateString();

        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->join('bank_response', 'transactions.tid', '=', 'bank_response.tid')
            ->where('transactions.bank_amount', $criteria['amount'])
            ->whereDate('bank_response.added', $date);

        if (!empty($criteria['currency'])) {
            $query->where('transactions.bank_currency', $criteria['currency']);
        }

        $results = $query->select([
            'transactions.id as transaction_id',
            'transactions.account_id',
            'transactions.shop_id',
            'transactions.tid as trx_id',
            'transactions.bank_amount',
            'transactions.bank_currency',
        ])
            ->get();

        // If multiple results, return the first one (could be improved with better scoring)
        return $results->count() === 1 ? (array) $results->first() : null;
    }

    /**
     * Get search criteria from transaction
     */
    private function getSearchCriteria(DectaTransaction $transaction): array
    {
        return [
            'payment_id' => $transaction->payment_id,
            'amount' => $transaction->tr_amount,
            'currency' => $transaction->tr_ccy,
            'transaction_date' => $transaction->tr_date_time,
        ];
    }

    /**
     * Display initial statistics
     */
    private function displayInitialStats(?int $fileId): void
    {
        $stats = $this->transactionRepository->getStatistics($fileId);

        $this->info("Current Statistics:");
        $this->info(" - Total transactions: {$stats['total']}");
        $this->info(" - Matched: {$stats['matched']} ({$stats['match_rate']}%)");
        $this->info(" - Unmatched: {$stats['unmatched']}");
        $this->info(" - Failed: {$stats['failed']}");
        $this->info(" - Pending: {$stats['pending']}");

        if ($stats['total'] > 0) {
            $this->newLine();
        }
    }

    /**
     * Display final results
     */
    private function displayResults(int $matched, int $failed, int $skipped, ?int $fileId): void
    {
        $this->info("Matching completed:");
        $this->info(" - Newly matched: {$matched}");
        $this->info(" - Failed to match: {$failed}");
        $this->info(" - Skipped: {$skipped}");

        if ($matched > 0 || $failed > 0) {
            $this->newLine();
            $this->displayInitialStats($fileId);
        }

        if ($failed > 0) {
            $this->warn("\nSome transactions could not be matched. Consider:");
            $this->info(" - Running with --retry-failed to retry failed matches");
            $this->info(" - Checking transaction data quality");
            $this->info(" - Reviewing search criteria logic");
        }

        if ($skipped > 0) {
            $this->info(" - Use --force to retry skipped transactions");
        }
    }
}
