<?php

namespace App\Services\Settlement\Fee\interfaces;

interface CustomFeeHandlerInterface
{
    public function getCustomFees(int $merchantId, array $rawTransactionData, string $startDate): array;

}
