<?php

namespace App\Services\Transaction;

use App\DTO\ChargebackData;
use App\DTO\TransactionTotal;
use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use App\Services\DynamicLogger;
use App\Services\ExchangeRateService;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackProcessorInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * Service for calculating transaction totals
 */
//@todo REMOVE THIS FILE (REFACTORS)
readonly class TransactionTotalsCalculator
{
    /**
     * Create a new TransactionTotalsCalculator
     */
    public function __construct(
        private ExchangeRateService          $exchangeRateService,
        private ChargebackProcessorInterface $chargebackProcessor,
        private DynamicLogger                $logger
    ) {
    }

    /**
     * Calculate transaction totals for all currencies
     *
     * @param Collection $transactions Transaction collection
     * @param array $exchangeRates Exchange rates lookup
     * @param int $merchantId Merchant ID
     * @return array Transaction totals by currency
     * @throws Exception
     */
    public function calculateTotals(Collection $transactions, array $exchangeRates, int $merchantId): array
    {
        try {
            // Initialize a map of TransactionTotal objects by currency
            $totalsByArray = $this->initializeTotalsMap($transactions);

            // Process each transaction and update the appropriate totals
            foreach ($transactions as $transaction) {
                $this->processTransaction($totalsByArray, $transaction, $exchangeRates);
            }

            // Calculate final exchange rates with merchant markup
            $totalsByArray = $this->calculateFinalExchangeRates($totalsByArray, $exchangeRates, $merchantId);

            // Convert TransactionTotal objects to arrays for compatibility
            return array_map(function (TransactionTotal $total) {
                return $total->toArray();
            }, $totalsByArray);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to calculate transaction totals', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Initialize a map of TransactionTotal objects by currency
     *
     * @param Collection $transactions Transaction collection
     * @return array Map of TransactionTotal objects by currency
     */
    private function initializeTotalsMap(Collection $transactions): array
    {
        $totalsByCurrency = [];
        $uniqueCurrencies = $transactions->pluck('currency')->unique();

        foreach ($uniqueCurrencies as $currency) {
            $totalsByCurrency[$currency] = TransactionTotal::initialize($currency);
        }

        return $totalsByCurrency;
    }

    /**
     * Process a single transaction and update the totals
     *
     * @param array $totalsMap Reference to totals map
     * @param object $transaction Transaction object
     * @param array $exchangeRates Exchange rates lookup
     */
    private function processTransaction(array &$totalsMap, object $transaction, array $exchangeRates): void
    {
        $currency = $transaction->currency;
        $rate = $this->exchangeRateService->getDailyExchangeRate($transaction, $exchangeRates);

        // Normalize amount
        $amount = is_string($transaction->amount) ? (float)$transaction->amount : $transaction->amount;

        // Convert to standard units (divide by 100 if needed)
        $amountInStandardUnits = $amount * 0.01;

        // Calculate EUR amount
        $amountInEur = $amountInStandardUnits * $rate;

        // Process transaction based on type and status
        $totalsMap[$currency] = $this->processTransactionByType(
            $totalsMap[$currency],
            $transaction,
            $amountInStandardUnits,
            $amountInEur,
            $rate
        );
    }

    /**
     * Process transaction based on its type and status
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param object $transaction Transaction object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param float $rate Exchange rate
     * @return TransactionTotal Updated TransactionTotal
     */
    private function processTransactionByType(
        TransactionTotal $total,
        object           $transaction,
        float            $amount,
        float            $amountInEur,
        float            $rate
    ): TransactionTotal {
        $transactionType = mb_strtoupper($transaction->transaction_type);
        $transactionStatus = mb_strtoupper($transaction->transaction_status);

        return match ($transactionType) {
            TransactionTypeEnum::CHARGEBACK->value => $this->processChargebackTransaction(
                $total,
                $transaction,
                $amount,
                $amountInEur,
                $transactionStatus,
                $rate
            ),
            TransactionTypeEnum::PAYOUT->value => $this->processPayoutTransaction(
                $total,
                $amount,
                $amountInEur,
                $transactionStatus
            ),
            TransactionTypeEnum::SALE->value => $this->processSaleTransaction(
                $total,
                $amount,
                $amountInEur,
                $transactionStatus
            ),
            TransactionTypeEnum::REFUND->value,
            TransactionTypeEnum::PARTIAL_REFUND->value => $this->processRefundTransaction(
                $total,
                $amount,
                $amountInEur,
                $transactionStatus
            ),
            default => $total
        };
    }

    /**
     * Process chargeback transaction
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param object $transaction Transaction object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $status Transaction status
     * @param float $rate Exchange rate
     * @return TransactionTotal Updated TransactionTotal
     */
    private function processChargebackTransaction(
        TransactionTotal $total,
        object           $transaction,
        float            $amount,
        float            $amountInEur,
        string           $status,
        float            $rate
    ): TransactionTotal {
        // Create and process chargeback data
        $chargebackData = ChargebackData::fromTransaction($transaction, $rate);
        $this->chargebackProcessor->processChargeback($transaction->account_id, $chargebackData);

        // Create a new instance with updated values
        $newTotal = new TransactionTotal(
            $total->currency,
            $total->totalSales,
            $total->totalSalesEur,
            $total->transactionSalesCount,
            $total->totalDeclinedSales,
            $total->totalDeclinedSalesEur,
            $total->transactionDeclinedCount,
            $total->totalRefunds,
            $total->totalRefundsEur,
            $total->transactionRefundsCount,
            $total->totalChargebackCount + 1, // Increment total chargeback count
            $total->processingChargebackCount,
            $total->processingChargebackAmount,
            $total->processingChargebackAmountEur,
            $total->approvedChargebackCount,
            $total->approvedChargebackAmount,
            $total->approvedChargebackAmountEur,
            $total->declinedChargebackCount,
            $total->declinedChargebackAmount,
            $total->declinedChargebackAmountEur,
            $total->totalPayoutCount,
            $total->totalPayoutAmount,
            $total->totalPayoutAmountEur,
            $total->processingPayoutCount,
            $total->processingPayoutAmount,
            $total->processingPayoutAmountEur,
            $total->approvedPayoutCount,
            $total->approvedPayoutAmount,
            $total->approvedPayoutAmountEur,
            $total->declinedPayoutCount,
            $total->declinedPayoutAmount,
            $total->declinedPayoutAmountEur,
            $total->exchangeRate,
            $total->fxRate
        );

        // Update chargeback counts and amounts based on status
        if ($status === TransactionStatusEnum::PROCESSING->value) {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount + 1,
                $newTotal->processingChargebackAmount + $amount,
                $newTotal->processingChargebackAmountEur + $amountInEur,
                $newTotal->approvedChargebackCount,
                $newTotal->approvedChargebackAmount,
                $newTotal->approvedChargebackAmountEur,
                $newTotal->declinedChargebackCount,
                $newTotal->declinedChargebackAmount,
                $newTotal->declinedChargebackAmountEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount,
                $newTotal->processingPayoutAmount,
                $newTotal->processingPayoutAmountEur,
                $newTotal->approvedPayoutCount,
                $newTotal->approvedPayoutAmount,
                $newTotal->approvedPayoutAmountEur,
                $newTotal->declinedPayoutCount,
                $newTotal->declinedPayoutAmount,
                $newTotal->declinedPayoutAmountEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        } elseif ($status === TransactionStatusEnum::APPROVED->value) {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount,
                $newTotal->processingChargebackAmount,
                $newTotal->processingChargebackAmountEur,
                $newTotal->approvedChargebackCount + 1,
                $newTotal->approvedChargebackAmount + $amount,
                $newTotal->approvedChargebackAmountEur + $amountInEur,
                $newTotal->declinedChargebackCount,
                $newTotal->declinedChargebackAmount,
                $newTotal->declinedChargebackAmountEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount,
                $newTotal->processingPayoutAmount,
                $newTotal->processingPayoutAmountEur,
                $newTotal->approvedPayoutCount,
                $newTotal->approvedPayoutAmount,
                $newTotal->approvedPayoutAmountEur,
                $newTotal->declinedPayoutCount,
                $newTotal->declinedPayoutAmount,
                $newTotal->declinedPayoutAmountEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        } else {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount,
                $newTotal->processingChargebackAmount,
                $newTotal->processingChargebackAmountEur,
                $newTotal->approvedChargebackCount,
                $newTotal->approvedChargebackAmount,
                $newTotal->approvedChargebackAmountEur,
                $newTotal->declinedChargebackCount + 1,
                $newTotal->declinedChargebackAmount + $amount,
                $newTotal->declinedChargebackAmountEur + $amountInEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount,
                $newTotal->processingPayoutAmount,
                $newTotal->processingPayoutAmountEur,
                $newTotal->approvedPayoutCount,
                $newTotal->approvedPayoutAmount,
                $newTotal->approvedPayoutAmountEur,
                $newTotal->declinedPayoutCount,
                $newTotal->declinedPayoutAmount,
                $newTotal->declinedPayoutAmountEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        }
    }

    /**
     * Process payout transaction
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $status Transaction status
     * @return TransactionTotal Updated TransactionTotal
     */
    private function processPayoutTransaction(
        TransactionTotal $total,
        float            $amount,
        float            $amountInEur,
        string           $status
    ): TransactionTotal {
        // Always update total payout counters
        $newTotal = new TransactionTotal(
            $total->currency,
            $total->totalSales,
            $total->totalSalesEur,
            $total->transactionSalesCount,
            $total->totalDeclinedSales,
            $total->totalDeclinedSalesEur,
            $total->transactionDeclinedCount,
            $total->totalRefunds,
            $total->totalRefundsEur,
            $total->transactionRefundsCount,
            $total->totalChargebackCount,
            $total->processingChargebackCount,
            $total->processingChargebackAmount,
            $total->processingChargebackAmountEur,
            $total->approvedChargebackCount,
            $total->approvedChargebackAmount,
            $total->approvedChargebackAmountEur,
            $total->declinedChargebackCount,
            $total->declinedChargebackAmount,
            $total->declinedChargebackAmountEur,
            $total->totalPayoutCount + 1,
            $total->totalPayoutAmount + $amount,
            $total->totalPayoutAmountEur + $amountInEur,
            $total->processingPayoutCount,
            $total->processingPayoutAmount,
            $total->processingPayoutAmountEur,
            $total->approvedPayoutCount,
            $total->approvedPayoutAmount,
            $total->approvedPayoutAmountEur,
            $total->declinedPayoutCount,
            $total->declinedPayoutAmount,
            $total->declinedPayoutAmountEur,
            $total->exchangeRate,
            $total->fxRate
        );

        // Update status-specific totals
        if ($status === TransactionStatusEnum::PROCESSING->value) {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount,
                $newTotal->processingChargebackAmount,
                $newTotal->processingChargebackAmountEur,
                $newTotal->approvedChargebackCount,
                $newTotal->approvedChargebackAmount,
                $newTotal->approvedChargebackAmountEur,
                $newTotal->declinedChargebackCount,
                $newTotal->declinedChargebackAmount,
                $newTotal->declinedChargebackAmountEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount + 1,
                $newTotal->processingPayoutAmount + $amount,
                $newTotal->processingPayoutAmountEur + $amountInEur,
                $newTotal->approvedPayoutCount,
                $newTotal->approvedPayoutAmount,
                $newTotal->approvedPayoutAmountEur,
                $newTotal->declinedPayoutCount,
                $newTotal->declinedPayoutAmount,
                $newTotal->declinedPayoutAmountEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        } elseif ($status === TransactionStatusEnum::APPROVED->value) {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount,
                $newTotal->processingChargebackAmount,
                $newTotal->processingChargebackAmountEur,
                $newTotal->approvedChargebackCount,
                $newTotal->approvedChargebackAmount,
                $newTotal->approvedChargebackAmountEur,
                $newTotal->declinedChargebackCount,
                $newTotal->declinedChargebackAmount,
                $newTotal->declinedChargebackAmountEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount,
                $newTotal->processingPayoutAmount,
                $newTotal->processingPayoutAmountEur,
                $newTotal->approvedPayoutCount + 1,
                $newTotal->approvedPayoutAmount + $amount,
                $newTotal->approvedPayoutAmountEur + $amountInEur,
                $newTotal->declinedPayoutCount,
                $newTotal->declinedPayoutAmount,
                $newTotal->declinedPayoutAmountEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        } elseif ($status === TransactionStatusEnum::DECLINED->value) {
            return new TransactionTotal(
                $newTotal->currency,
                $newTotal->totalSales,
                $newTotal->totalSalesEur,
                $newTotal->transactionSalesCount,
                $newTotal->totalDeclinedSales,
                $newTotal->totalDeclinedSalesEur,
                $newTotal->transactionDeclinedCount,
                $newTotal->totalRefunds,
                $newTotal->totalRefundsEur,
                $newTotal->transactionRefundsCount,
                $newTotal->totalChargebackCount,
                $newTotal->processingChargebackCount,
                $newTotal->processingChargebackAmount,
                $newTotal->processingChargebackAmountEur,
                $newTotal->approvedChargebackCount,
                $newTotal->approvedChargebackAmount,
                $newTotal->approvedChargebackAmountEur,
                $newTotal->declinedChargebackCount,
                $newTotal->declinedChargebackAmount,
                $newTotal->declinedChargebackAmountEur,
                $newTotal->totalPayoutCount,
                $newTotal->totalPayoutAmount,
                $newTotal->totalPayoutAmountEur,
                $newTotal->processingPayoutCount,
                $newTotal->processingPayoutAmount,
                $newTotal->processingPayoutAmountEur,
                $newTotal->approvedPayoutCount,
                $newTotal->approvedPayoutAmount,
                $newTotal->approvedPayoutAmountEur,
                $newTotal->declinedPayoutCount + 1,
                $newTotal->declinedPayoutAmount + $amount,
                $newTotal->declinedPayoutAmountEur + $amountInEur,
                $newTotal->exchangeRate,
                $newTotal->fxRate
            );
        }

        // Return original if no status match
        return $newTotal;
    }

    /**
     * Process sale transaction
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $status Transaction status
     * @return TransactionTotal Updated TransactionTotal
     */
    private function processSaleTransaction(
        TransactionTotal $total,
        float            $amount,
        float            $amountInEur,
        string           $status
    ): TransactionTotal {
        if ($status === TransactionStatusEnum::APPROVED->value) {
            return $total->withAddedSale($amount, $amountInEur);
        } elseif ($status === TransactionStatusEnum::DECLINED->value) {
            return $total->withAddedDeclinedSale($amount, $amountInEur);
        }

        return $total;
    }

    /**
     * Process refund transaction
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param float $amount Amount in standard units
     * @param float $amountInEur Amount in EUR
     * @param string $status Transaction status
     * @return TransactionTotal Updated TransactionTotal
     */
    private function processRefundTransaction(
        TransactionTotal $total,
        float            $amount,
        float            $amountInEur,
        string           $status
    ): TransactionTotal {
        // Only process approved refunds
        if ($status === TransactionStatusEnum::APPROVED->value) {
            return $total->withAddedRefund($amount, $amountInEur);
        }

        return $total;
    }

    /**
     * Calculate final exchange rates with merchant markup
     *
     * @param array $totalsMap Map of TransactionTotal objects by currency
     * @param array $exchangeRates Exchange rates lookup
     * @param int $merchantId Merchant ID
     * @return array Updated map of TransactionTotal objects
     */
    private function calculateFinalExchangeRates(array $totalsMap, array $exchangeRates, int $merchantId): array
    {
        // Get merchant-specific exchange rate markup
        $rateMarkup = $this->exchangeRateService->getMerchantRateMarkup($merchantId, 'exchange_rate');
        $fxRateMarkup = $this->exchangeRateService->getMerchantRateMarkup($merchantId, 'fx_rate');

        foreach ($totalsMap as $currency => $total) {
            // Set precision based on currency
            $precision = $this->exchangeRateService->getPrecisionForCurrency($currency);

            if ($total->totalSales > 0) {
                // Calculate exchange rate from existing data
                $netSales = $total->totalSales - $total->totalRefunds;
                $netSalesEur = $total->totalSalesEur - $total->totalRefundsEur;

                // Set the FX rate from merchant settings
                $totalsMap[$currency] = $total->withFxRate($fxRateMarkup);

                // Calculate and apply exchange rate if we have valid EUR sales
                if ($netSalesEur > 0) {
                    $rawExchangeRate = $netSales / $netSalesEur;

                    // Apply markup for non-EUR currencies
                    if ($currency !== 'EUR') {
                        $finalRate = round($rawExchangeRate * $rateMarkup, $precision);
                        $totalsMap[$currency] = $totalsMap[$currency]->withExchangeRate($finalRate);
                    } else {
                        $totalsMap[$currency] = $totalsMap[$currency]->withExchangeRate(1.0);
                    }
                } else {
                    $totalsMap[$currency] = $this->handleZeroDivisionCase(
                        $totalsMap[$currency],
                        $currency,
                        $exchangeRates,
                        $rateMarkup,
                        $precision
                    );
                }
            } else {
                $totalsMap[$currency] = $this->handleZeroDivisionCase(
                    $totalsMap[$currency],
                    $currency,
                    $exchangeRates,
                    $rateMarkup,
                    $precision
                );
            }
        }

        return $totalsMap;
    }

    /**
     * Handle exchange rate calculation for cases with zero sales
     *
     * @param TransactionTotal $total TransactionTotal object
     * @param string $currency Currency code
     * @param array $exchangeRates Exchange rates lookup
     * @param float $rateMarkup Exchange rate markup
     * @param int $precision Decimal precision for rounding
     * @return TransactionTotal Updated TransactionTotal
     */
    private function handleZeroDivisionCase(
        TransactionTotal $total,
        string           $currency,
        array            $exchangeRates,
        float            $rateMarkup,
        int              $precision
    ): TransactionTotal {
        // Use 1.0 for EUR, or find last known rate
        $baseRate = ($currency === 'EUR') ? 1.0 : $this->getLastKnownExchangeRate($currency, $exchangeRates);

        // Apply markup for non-EUR currencies
        if ($currency !== 'EUR') {
            return $total->withExchangeRate(round($baseRate * $rateMarkup, $precision));
        } else {
            return $total->withExchangeRate(1.0);
        }
    }

    /**
     * Get last known exchange rate for a currency
     *
     * @param string $currency Currency code
     * @param array $exchangeRates Exchange rates lookup
     * @return float Exchange rate or 1.0 if not found
     */
    private function getLastKnownExchangeRate(string $currency, array $exchangeRates): float
    {
        // Try to find any rate for this currency
        foreach ($exchangeRates as $key => $rate) {
            if (str_starts_with($key, $currency . '_')) {
                return $rate;
            }
        }

        // Default to 1.0 if no rate found
        $this->logger->log('warning', 'No exchange rate found for currency', [
            'currency' => $currency,
        ]);

        return 1.0;
    }
}
