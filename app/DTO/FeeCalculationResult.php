<?php

namespace App\DTO;

readonly class FeeCalculationResult
{
    public function __construct(
        public string $feeType,
        public string $feeRate,
        public float  $feeAmount,
        public string $frequency,
        public bool   $isPercentage,
        public array  $transactionData
    ) {}

    public function toArray(): array
    {
        return [
            'fee_type' => $this->feeType,
            'fee_rate' => $this->feeRate,
            'fee_amount' => $this->feeAmount,
            'frequency' => $this->frequency,
            'is_percentage' => $this->isPercentage,
            'transactionData' => $this->transactionData
        ];
    }
}
