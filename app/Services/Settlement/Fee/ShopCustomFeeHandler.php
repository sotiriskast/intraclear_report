<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\ShopRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\Factories\FeeCalculatorFactory;
use App\Services\Settlement\Fee\interfaces\ShopCustomFeeHandlerInterface;

/**
 * Handles the calculation and processing of custom shop fees
 */
readonly class ShopCustomFeeHandler implements ShopCustomFeeHandlerInterface
{
    private FeeCalculatorFactory $calculatorFactory;

    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private ShopRepository $shopRepository,
        private DynamicLogger $logger
    ) {
        $this->calculatorFactory = new FeeCalculatorFactory;
    }

    /**
     * Calculate all applicable custom fees for a shop based on transaction data
     */
    public function getCustomFees(int $merchantId, int $shopId, array $rawTransactionData, string $startDate): array
    {
        try {
            // Get internal shop ID
            $internalShopId = $this->shopRepository->getInternalIdByExternalId($shopId, $merchantId);

            // Get shop-specific fees
            $shopFees = $this->feeRepository->getShopFees($internalShopId, $startDate);
            $transactionData = TransactionData::fromArray($rawTransactionData);
            $customFees = [];

            // Extract currency information for fee calculation
            $currency = $transactionData->currency;
            $exchangeRate = $transactionData->exchangeRate;

            // Process each custom fee
            foreach ($shopFees as $fee) {
                try {
                    // Skip fees with zero or negative amounts
                    if ($fee->amount <= 0) {
                        continue;
                    }

                    // Create appropriate calculator based on fee configuration
                    $calculator = $this->calculatorFactory->createCalculator(
                        $fee->feeType->frequency_type,
                        $fee->feeType->is_percentage,
                        $fee->feeType->key
                    );

                    // Calculate the actual fee amount
                    $feeAmount = $calculator->calculate($transactionData, $fee->amount);

                    if ($feeAmount > 0) {
                        // Prepare fee details for return
                        $customFees[] = [
                            'fee_type' => $fee->feeType->name,
                            'fee_type_id' => $fee->fee_type_id,
                            'fee_rate' => $this->formatRate($fee->amount, $fee->feeType->is_percentage),
                            'fee_amount' => $feeAmount,
                            'currency' => $currency,
                            'exchange_rate' => $exchangeRate,
                            'frequency' => $fee->feeType->frequency_type,
                            'is_percentage' => $fee->feeType->is_percentage,
                            'transactionData' => $rawTransactionData,
                            'shop_id' => $internalShopId,
                        ];
                    }
                } catch (\Exception $e) {
                    // Log error but continue processing other fees
                    $this->logger->log('error', 'Error processing custom shop fee', [
                        'merchant_id' => $merchantId,
                        'shop_id' => $shopId,
                        'fee_type_id' => $fee->fee_type_id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            return $customFees;

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate custom shop fees', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Format a fee rate for display
     */
    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2).'%' :
            number_format($amount / 100, 2);
    }
}
