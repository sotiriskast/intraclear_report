<?php

namespace App\DTO;

use Carbon\CarbonInterface;

readonly class ReserveEntryData
{
    public function __construct(
        public int             $merchantId,
        public int             $originalAmount,
        public string          $originalCurrency,
        public int             $reserveAmountEur,
        public float           $exchangeRate,
        public CarbonInterface $periodStart,
        public CarbonInterface $periodEnd,
        public CarbonInterface $releaseDueDate,
        public string          $status = 'pending',
    )
    {
    }

    public function toArray(): array
    {
        return [
            'merchant_id' => $this->merchantId,
            'original_amount' => $this->originalAmount,
            'original_currency' => $this->originalCurrency,
            'reserve_amount_eur' => $this->reserveAmountEur,
            'exchange_rate' => $this->exchangeRate,
            'period_start' => $this->periodStart->toDateString(),
            'period_end' => $this->periodEnd->toDateString(),
            'release_due_date' => $this->releaseDueDate->toDateString(),
            'status' => $this->status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
