<?php

namespace App\Services\Settlement\Fee\interfaces;

interface StandardFeeHandlerInterface
{
    public function getStandardFees(int $merchantId, array $rawTransactionData): array;

}
