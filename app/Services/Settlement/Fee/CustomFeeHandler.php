<?php

namespace App\Services\Settlement\Fee;

use App\DTO\TransactionData;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\Factories\FeeCalculatorFactory;
use App\Services\Settlement\Fee\interfaces\CustomFeeHandlerInterface;

readonly class CustomFeeHandler implements CustomFeeHandlerInterface
{
    private FeeCalculatorFactory $calculatorFactory;

    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger $logger
    ) {
        $this->calculatorFactory = new FeeCalculatorFactory();
    }

    public function getCustomFees(int $merchantId, array $rawTransactionData, string $startDate): array
    {
        try {
            $merchantFees = $this->feeRepository->getMerchantFees($merchantId, $startDate);
            $transactionData = TransactionData::fromArray($rawTransactionData);
            $customFees = [];
            $currency = $transactionData->currency;
            $exchangeRate = $transactionData->exchangeRate;
            foreach ($merchantFees as $fee) {
                try {
                    if ($fee->amount <= 0) {
                        continue;
                    }

                    $calculator = $this->calculatorFactory->createCalculator(
                        $fee->feeType->frequency_type,
                        $fee->feeType->is_percentage,
                        $fee->feeType->key
                    );

                    $feeAmount = $calculator->calculate($transactionData, $fee->amount);

                    if ($feeAmount > 0) {
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
    private function formatRate(int $amount, bool $isPercentage): string
    {
        return $isPercentage ?
            number_format($amount / 100, 2) . '%' :
            number_format($amount / 100, 2);
    }
}
