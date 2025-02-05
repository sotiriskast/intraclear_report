<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\Factories\FeeCalculatorFactory;
use App\Services\Settlement\Fee\interfaces\CustomFeeHandlerInterface;

/**
 * Handles the calculation and processing of custom merchant fees
 * Custom fees are merchant-specific fees that may differ from standard fee structures
 */
readonly class CustomFeeHandler implements CustomFeeHandlerInterface
{
    private FeeCalculatorFactory $calculatorFactory;

    /**
     * Initialize the custom fee handler with required dependencies
     *
     * @param FeeRepositoryInterface $feeRepository Repository for accessing merchant-specific fees
     * @param DynamicLogger $logger Service for logging operations and errors
     */
    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger          $logger
    )
    {
        $this->calculatorFactory = new FeeCalculatorFactory();
    }

    /**
     * Calculate all applicable custom fees for a merchant based on transaction data
     *
     * Processes each custom fee configured for the merchant:
     * 1. Retrieves all custom fees for the merchant
     * 2. Creates appropriate calculator for each fee type
     * 3. Calculates fee amounts considering currency and exchange rates
     * 4. Formats and returns the calculated fees
     *
     * @param int $merchantId ID of the merchant
     * @param array $rawTransactionData Raw transaction data for fee calculation
     * @param string $startDate Start date for fee application period
     * @return array Array of calculated custom fees
     */
    public function getCustomFees(int $merchantId, array $rawTransactionData, string $startDate): array
    {
        try {
            // Get merchant-specific fees
            $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $startDate);
            $transactionData = TransactionData::fromArray($rawTransactionData);
            $customFees = [];

            // Extract currency information for fee calculation
            $currency = $transactionData->currency;
            $exchangeRate = $transactionData->exchangeRate;

            // Process each custom fee
            foreach ($merchantFees as $fee) {
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
                            'transactionData' => $rawTransactionData
                        ];
                    }
                } catch (\Exception $e) {
                    // Log error but continue processing other fees
                    $this->logger->log('error', 'Error processing custom fee', [
                        'merchant_id' => $merchantId,
                        'fee_type_id' => $fee->fee_type_id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            return $customFees;

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate custom fees', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Format a fee rate for display
     * Converts internal rate representation to human-readable format
     *
     * @param int $amount Fee amount in smallest currency unit (e.g., cents)
     * @param bool $isPercentage Whether the fee is a percentage
     * @return string Formatted rate with appropriate suffix (% for percentages)
     */
    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2) . '%' :
            number_format($amount / 100, 2);
    }
}
