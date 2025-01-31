<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\DynamicLogger;

readonly class FeeService
{
    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger          $logger,
        private FeeFrequencyHandler    $frequencyHandler,
        private CustomFeeHandler       $customFeeHandler,
        private StandardFeeHandler     $standardFeeHandler

    )
    {
    }

    public function calculateFees(int $merchantId, array $transactionData, array $dateRange): array
    {
        try {
            $calculatedFees = [];

            // Process standard fees
            $standardFees = $this->standardFeeHandler->getStandardFees($merchantId, $transactionData);
            foreach ($standardFees as $fee) {
                if ($this->frequencyHandler->shouldApplyFee(
                    $fee['frequency'],
                    $merchantId,
                    $fee['fee_type_id'],
                    $dateRange
                )) {
                    $calculatedFees[] = $fee;
                    $this->logFeeApplication($merchantId, $fee, $transactionData, $fee['fee_amount'], $dateRange);
                }
            }

            // Process custom fees
            $customFees = $this->customFeeHandler->getCustomFees($merchantId, $transactionData, $dateRange['start']);
            foreach ($customFees as $fee) {
                if ($this->frequencyHandler->shouldApplyFee(
                    $fee['frequency'],
                    $merchantId,
                    $fee['fee_type_id'],
                    $dateRange
                )) {
                    $calculatedFees[] = $fee;
                    $this->logFeeApplication($merchantId, $fee, $transactionData, $fee['fee_amount'], $dateRange);
                }
            }

            $this->logger->log('info', 'Fee calculation completed', [
                'merchant_id' => $merchantId,
                'standard_fees_count' => count($standardFees),
                'custom_fees_count' => count($customFees),
                'total_fees' => count($calculatedFees)
            ]);

            return $calculatedFees;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to calculate fees', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    private function logFeeApplication($merchantId, $fee, $transactionData, $feeAmount, $dateRange): void
    {
        $this->feeRepository->logFeeApplication([
            'merchant_id' => $merchantId,
            'fee_type_id' => $fee['fee_type_id'],
            'base_amount' => $transactionData['total_sales_amount'] ?? 0,
            'base_currency' => $transactionData['currency'] ?? 'EUR',
            'fee_amount_eur' => $feeAmount,
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'applied_date' => $dateRange['start'],
        ]);
    }
}
