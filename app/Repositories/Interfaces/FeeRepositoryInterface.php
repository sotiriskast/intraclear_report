<?php

namespace App\Repositories\Interfaces;

use App\Models\FeeHistory;
use App\Models\MerchantFee;
use Illuminate\Database\Eloquent\Collection;

interface FeeRepositoryInterface
{
    /**
     * Get active merchant fees for a given date
     *
     * @param int $merchantId Merchant's account ID
     * @param string|null $date Specific date to check fees (null for current)
     * @return Collection<MerchantFee> Collection of active merchant fees
     */
    public function getMerchantFees(int $merchantId, ?string $date = null): Collection;

    /**
     * Get all active fee types
     *
     * @return Collection Collection of active fee types
     */
    public function getActiveFeeTypes(): Collection;

    /**
     * Create a new merchant fee
     *
     * @param array $data Fee data including merchant_id, fee_type_id, amount
     * @return MerchantFee Newly created merchant fee
     */
    public function createMerchantFee(array $data): MerchantFee;

    /**
     * Update an existing merchant fee
     *
     * @param int $feeId Fee ID to update
     * @param array $data Updated fee data
     * @return MerchantFee Updated merchant fee
     */
    public function updateMerchantFee(int $feeId, array $data): MerchantFee;

    /**
     * Log a fee application instance
     *
     * @param array $feeData Fee application data
     * @return FeeHistory Created fee history record
     */
    public function logFeeApplication(array $feeData): FeeHistory;

    /**
     * Get the most recent fee application for a merchant and fee type
     *
     * @param int $merchantId Merchant ID
     * @param int $feeTypeId Fee type ID
     * @return FeeHistory|null Latest fee history record or null if none exists
     */
    public function getLastFeeApplication(int $merchantId, int $feeTypeId): ?FeeHistory;

    /**
     * Check if merchant has any fee applications
     *
     * @param int $merchantId Merchant ID
     * @return bool Whether the merchant has any fee applications
     */
    public function hasAnyFeeApplications(int $merchantId): bool;

    /**
     * Get fee applications within a specified date range
     *
     * @param int $merchantId Merchant ID
     * @param int|null $feeTypeId Fee type ID (optional)
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Array of fee history records
     */
    public function getFeeApplicationsInDateRange(
        int $merchantId,
        ?int $feeTypeId,
        string $startDate,
        string $endDate
    ): array;
}
