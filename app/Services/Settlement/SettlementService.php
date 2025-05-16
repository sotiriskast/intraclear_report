<?php

namespace App\Services\Settlement;

use App\Exceptions\MissingSchemeRatesException;
use App\Repositories\Interfaces\TransactionRepositoryInterface;
use App\Repositories\ShopRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackSettlementInterface;
use App\Services\Settlement\Fee\ShopFeeService;
use App\Services\Settlement\Reserve\ShopRollingReserveHandler;
use Exception;

/**
 * SettlementService manages the generation of shop-based merchant settlements.
 */
readonly class SettlementService
{
    public function __construct(
        private TransactionRepositoryInterface $transactionRepository,
        private ChargebackSettlementInterface $chargebackSettlement,
        private ShopRollingReserveHandler $rollingReserveHandler,
        private DynamicLogger $logger,
        private ShopFeeService $feeService,
        private SchemeRateValidationService $schemeRateValidator,
        private ShopRepository $shopRepository,
    ) {}

    /**
     * Generates a comprehensive settlement report for a merchant shop.
     */
    public function generateSettlement(int $merchantId, array $dateRange, string $currency, int $shopId): array
    {
        try {
            $this->logger->log('info', 'Starting shop settlement generation', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'currency' => $currency,
                'date_range' => $dateRange,
            ]);

            // Get internal shop ID
            $internalShopId = $this->shopRepository->getInternalIdByExternalId($shopId, $merchantId);

            // Retrieve merchant transactions for the specified period and shop
            $transactions = $this->transactionRepository->getMerchantShopTransactions(
                $merchantId,
                $shopId,
                $dateRange,
                $currency
            );

            $this->logger->log('info', 'Retrieved shop transactions', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'currency' => $currency,
                'transaction_count' => $transactions->count(),
            ]);

            // Validate scheme rates for the date range and currency
            try {
                $this->schemeRateValidator->validateSchemeRates($dateRange, [$currency]);
            } catch (MissingSchemeRatesException $e) {
                $this->logger->log('error', $e->getMessage(), [
                    'merchant_id' => $merchantId,
                    'shop_id' => $shopId,
                    'missing_rates' => $e->getMissingRates(),
                    'date_range' => $e->getDateRange(),
                ]);
                throw $e;
            }

            // Fetch and calculate exchange rates
            $exchangeRates = $this->transactionRepository->getExchangeRates(
                $dateRange,
                [$currency]
            );

            // Calculate transaction totals for this shop
            $totals = $this->transactionRepository->calculateShopTransactionTotals(
                $transactions,
                $exchangeRates,
                $merchantId,
                $shopId
            );

            // Calculate shop fees
            $currencyTotals = $totals[$currency] ?? [];
            $fees = $this->feeService->calculateFees(
                $merchantId,
                $shopId,
                $currencyTotals,
                $dateRange
            );

            // Process chargeback settlements
            $chargebackSettlements = $this->chargebackSettlement->processSettlementsChargeback(
                $merchantId,
                $dateRange
            );

            // Process rolling reserves for this shop
            $reserveProcessing = $this->rollingReserveHandler->processShopSettlementReserve(
                $internalShopId,
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
            $this->logger->log('error', 'Shop settlement generation failed', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
