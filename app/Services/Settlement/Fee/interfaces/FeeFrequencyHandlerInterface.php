<?php

namespace App\Services\Settlement\Fee\interfaces;

interface FeeFrequencyHandlerInterface
{
    public function shouldApplyFee(string $frequencyType, int $merchantId, int $feeTypeId, array $dateRange): bool;

}
