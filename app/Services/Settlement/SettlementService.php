<?php

namespace App\Services\Settlement;

use App\Exceptions\MissingSchemeRatesException;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackSettlementInterface;
use App\Services\Settlement\Fee\FeeService;
use App\Services\Settlement\Reserve\RollingReserveHandler;
use Exception;

/**
 * SettlementService manages the generation of merchant settlements.
 *
 * This service is responsible for:
 * - Retrieving merchant transactions
 * - Calculating transaction totals and exchange rates
 * - Processing fees
 * - Handling rolling reserves
 *
 * Key features:
 * - Uses readonly class to ensure immutability of dependencies
 * - Comprehensive error logging
 * - Detailed settlement calculations
 */
readonly class SettlementService
{
    /**
     * Construct the SettlementService with required dependencies.
     *
     * Uses PHP 8.4 readonly class feature to enforce immutability of
     * service dependencies.
     *
     * @param  TransactionRepositoryInterface  $transactionRepository  Repository for fetching transaction data
     * @param  ChargebackSettlementInterface  $chargebackSettlement  Service for fetching transaction data
     * @param  RollingReserveHandler  $rollingReserveHandler  Service to manage rolling reserves
     * @param  DynamicLogger  $logger  Logging service for tracking settlement processes
     * @param  FeeService  $feeService  Service for calculating merchant fees
     */
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private ChargebackSettlementInterface $chargebackSettlement,
        private RollingReserveHandler $rollingReserveHandler,
        private DynamicLogger $logger,
        private FeeService $feeService,
        private SchemeRateValidationService $schemeRateValidator,


    ) {}

    /**
     * Generates a comprehensive settlement report for a merchant.
     *
     * This method performs a series of steps to create a detailed settlement:
     * 1. Log the start of settlement generation
     * 2. Retrieve merchant transactions for the specified date range
     * 3. Fetch exchange rates
     * 4. Calculate transaction totals
     * 5. Calculate merchant fees
     * 6. Process rolling reserves
     * 7. Compile and return settlement details
     *
     * @param  int  $merchantId  Unique identifier of the merchant
     * @param  array  $dateRange  Associative array containing 'start' and 'end' dates
     * @param  string  $currency  Currency code for the settlement
     * @return array Comprehensive settlement report with various financial metrics
     *
     * @throws \Exception If any part of the settlement generation fails
     */
    public function generateSettlement(int $merchantId, array $dateRange, string $currency): array
    {
        try {
            // Log the start of settlement generation
            $this->logger->log('info', 'Starting settlement generation', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'date_range' => $dateRange,
            ]);
            // Retrieve merchant transactions for the specified period
            $transactions = $this->transactionRepository->getMerchantTransactions(
                $merchantId,
                $dateRange,
                $currency
            );

            $this->logger->log('info', 'Retrieved merchant transactions', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'transaction_count' => $transactions->count(),
            ]);
            // Validate scheme rates for the date range and currency
            try {
                $this->schemeRateValidator->validateSchemeRates($dateRange, [$currency]);
            } catch (MissingSchemeRatesException $e) {
                $this->logger->log('error', $e->getMessage(), [
                    'merchant_id' => $merchantId,
                    'missing_rates' => $e->getMissingRates(),
                    'date_range' => $e->getDateRange(),
                ]);

                // Re-throw to stop processing
                throw $e;
            }
            // Fetch and calculate exchange rates
            $exchangeRates = $this->transactionRepository->getExchangeRates(
                $dateRange,
                [$currency]
            );
            $this->logger->log('info', 'Calculated exchange rates', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
            ]);

            // Calculate transaction totals
            $totals = $this->transactionRepository->calculateTransactionTotals(
                $transactions,
                $exchangeRates,
                $merchantId
            );
            $this->logger->log('info', 'Calculated transaction totals', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
            ]);

            // Calculate merchant fees
            $currencyTotals = $totals[$currency] ?? [];
            $fees = $this->feeService->calculateFees(
                $merchantId,
                $currencyTotals,
                $dateRange
            );

            $chargebackSettlements = $this->chargebackSettlement->processSettlementsChargeback(
                $merchantId,
                $dateRange
            );
            // Process rolling reserves
            $reserveProcessing = $this->rollingReserveHandler->processSettlementReserve(
                $merchantId,
                $currencyTotals,
                $currency,
                $dateRange
            );

            // Compile and return comprehensive settlement report
            return [
                // Sales metrics
                'total_sales_amount' => $currencyTotals['total_sales'] ?? 0,
                'total_sales_amount_eur' => $currencyTotals['total_sales_eur'] ?? 0,

                // Declined transaction metrics
                'total_declined_amount' => $currencyTotals['total_declined_sales'] ?? 0,
                'total_declined_amount_eur' => $currencyTotals['total_declined_sales_eur'] ?? 0,

                // Refund metrics
                'total_refunds_amount' => $currencyTotals['total_refunds'] ?? 0,
                'total_refunds_amount_eur' => $currencyTotals['total_refunds_eur'] ?? 0,

                // Chargeback processing
                'total_processing_chargeback_amount' => $currencyTotals['processing_chargeback_amount'] ?? 0,
                'total_processing_chargeback_amount_eur' => $currencyTotals['processing_chargeback_amount_eur'] ?? 0,

                // Chargeback approved
                'total_approved_chargeback_amount' => (float) ($currencyTotals['approved_chargeback_amount'] ?? 0 + $chargebackSettlements['approved_refunds'] ?? 0),
                'total_approved_chargeback_amount_eur' => (float) ($currencyTotals['approved_chargeback_amount_eur'] ?? 0 + $chargebackSettlements['approved_refunds_eur'] ?? 0),

                // Chargeback declined
                'total_declined_chargeback_amount' => $currencyTotals['declined_chargeback_amount'] ?? 0,
                'total_declined_chargeback_amount_eur' => $currencyTotals['declined_chargeback_amount_eur'] ?? 0,

                // Transaction count metrics
                'total_sales_transaction_count' => $currencyTotals['transaction_sales_count'] ?? 0,
                'total_decline_transaction_count' => $currencyTotals['transaction_declined_count'] ?? 0,
                'total_refunds_transaction_count' => $currencyTotals['refund_count'] ?? 0,
                'total_processing_chargeback_count' => $currencyTotals['processing_chargeback_count'] ?? 0,
                'total_chargeback_count' => $currencyTotals['total_chargeback_count'] ?? 0,

                // Additional financial details
                'fees' => $fees,
                'rolling_reserve' => $reserveProcessing['new_reserve'],
                'releaseable_reserve' => $reserveProcessing['released_reserves'],
                'rolling_reserved_percentage' => $reserveProcessing['reserved_percentage'],
                'chargebackSettlement' => $chargebackSettlements,
                'exchange_rate' => $currencyTotals['exchange_rate'] ?? 1.0,
                'fx_rate' => $currencyTotals['fx_rate'] ?? 0,
            ];

        } catch (\Exception $e) {
            // Log detailed error information
            $this->logger->log('error', 'Settlement generation failed', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow the exception for higher-level error handling
            throw $e;
        }
    }
}
