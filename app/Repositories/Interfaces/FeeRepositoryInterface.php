<?php
// app/Repositories/Interfaces/FeeRepositoryInterface.php

namespace App\Repositories\Interfaces;

interface FeeRepositoryInterface
{
    public function getMerchantFees(int $merchantId, string $date = null);
    public function getActiveFeeTypes();
    public function createMerchantFee(array $data);
    public function updateMerchantFee(int $feeId, array $data);
    public function logFeeApplication(array $feeData);
}
