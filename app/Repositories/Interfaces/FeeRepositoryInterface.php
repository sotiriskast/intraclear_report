<?php

namespace App\Repositories\Interfaces;

use App\Models\FeeHistory;

interface FeeRepositoryInterface
{
    public function getMerchantFees(int $merchantId, string $date = null);
    public function getActiveFeeTypes();
    public function createMerchantFee(array $data);
    public function updateMerchantFee(int $feeId, array $data);
    public function logFeeApplication(array $feeData);
    public function getLastFeeApplication(int $merchantId, int $feeTypeId): ?FeeHistory;
    public function getFeeApplicationsInDateRange(
        int $merchantId,
        int $feeTypeId,
        string $startDate,
        string $endDate
    ): array;

}
