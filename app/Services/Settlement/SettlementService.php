<?php

namespace App\Services\Settlement;

use App\Services\DynamicLogger;
use App\Classes\Fees\FeeCalculatorFactory;
use App\Services\Settlement\Fee\FeeService;
use App\Repositories\Interfaces\{FeeRepositoryInterface, TransactionRepositoryInterface};
use App\Services\Settlement\Reserve\RollingReserveHandler;
use Carbon\Carbon;

class SettlementService
{
    private $feeCalculatorFactory;

    public function __construct(
        private readonly FeeRepositoryInterface         $feeRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly RollingReserveHandler          $rollingReserveHandler,
        private readonly DynamicLogger                  $logger,
        private readonly FeeService                     $feeService
    )
    {
        $this->feeCalculatorFactory = new FeeCalculatorFactory();
    }

    public function generateSettlement(int $merchantId, array $dateRange, string $currency): array
    {
        try {
            $this->logger->log('info', 'Starting settlement generation', ['merchant_id' => $merchantId, 'currency' => $currency, 'date_range' => $dateRange]);
            // Get transactions
            $transactions = $this->transactionRepository->getMerchantTransactions($merchantId, $dateRange, $currency);
            $this->logger->log('info', 'Got Merchants transactions', ['merchant_id' => $merchantId, 'currency' => $currency, 'date_range' => $dateRange]);
            // Calculate exchange rates and totals
            $exchangeRates = $this->transactionRepository->getExchangeRates($dateRange, [$currency]);

            $this->logger->log('info', 'Get Exchange Rate', ['merchant_id' => $merchantId, 'currency' => $currency, 'date_range' => $dateRange]);
            $totals = $this->transactionRepository->calculateTransactionTotals($transactions, $exchangeRates);
            $this->logger->log('info', 'Calculate Transaction Totals', ['merchant_id' => $merchantId, 'currency' => $currency, 'date_range' => $dateRange]);
            // Calculate fees
            $fees = $this->feeService->calculateFees($merchantId, $totals[$currency] ?? [], $dateRange);

            // Process rolling reserve (both new reserves and releases)
            $reserveProcessing = $this->rollingReserveHandler->processSettlementReserve(
                $merchantId,
                $totals[$currency] ?? [],
                $currency,
                $dateRange
            );

            return [
                'total_sales_amount' => $totals[$currency]['total_sales'] ?? 0,
                'total_sales_amount_eur' => $totals[$currency]['total_sales_eur'] ?? 0,
                'total_declined_amount' => $totals[$currency]['total_declined_sales'] ?? 0,
                'total_declined_amount_eur' => $totals[$currency]['total_declined_sales_eur'] ?? 0,
                'total_refunds_amount' => $totals[$currency]['total_refunds'] ?? 0,
                'total_refunds_amount_eur' => $totals[$currency]['total_refunds_eur'] ?? 0,
                'total_sales_transaction_count' => $totals[$currency]['transaction_sales_count'] ?? 0,
                'total_decline_transaction_count' => $totals[$currency]['transaction_declined_count'] ?? 0,
                'total_refunds_transaction_count' => $totals[$currency]['refund_count'] ?? 0,
                'fees' => $fees,
                'rolling_reserve' => $reserveProcessing['new_reserve'],
                'releaseable_reserve' => $reserveProcessing['released_reserves'],
                'exchange_rate' => $totals[$currency]['exchange_rate'] ?? 1.0
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Settlement generation failed', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
