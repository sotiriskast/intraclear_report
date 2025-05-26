<?php

namespace App\Repositories\Interfaces;

use App\Models\FeeHistory;
use App\Models\ShopFee;
use Illuminate\Database\Eloquent\Collection;

interface FeeRepositoryInterface
{
    /**
     * Get active shop fees for a given date
     *
     * @param int $shopId Shop ID (internal)
     * @param string|null $date Specific date to check fees (null for current)
     * @return Collection<ShopFee> Collection of active shop fees
     */
    public function getShopFees(int $shopId, ?string $date = null): Collection;

    /**
     * Get all active fee types
     *
     * @return Collection Collection of active fee types
     */
    public function getActiveFeeTypes(): Collection;


    /**
     * Create a new shop fee
     *
     * @param array $data Fee data including shop_id, fee_type_id, amount
     * @return ShopFee Newly created shop fee
     */
    public function createShopFee(array $data): ShopFee;


    /**
     * Update an existing shop fee
     *
     * @param int $feeId Fee ID to update
     * @param array $data Updated fee data
     * @return ShopFee Updated shop fee
     */
    public function updateShopFee(int $feeId, array $data): ShopFee;

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
     * Get the most recent fee application for a shop and fee type
     *
     * @param int $shopId Shop ID (internal)
     * @param int $feeTypeId Fee type ID
     * @return FeeHistory|null Latest fee history record or null if none exists
     */
    public function getLastShopFeeApplication(int $shopId, int $feeTypeId): ?FeeHistory;

    /**
     * Check if merchant has any fee applications
     *
     * @param int $merchantId Merchant ID
     * @return bool Whether the merchant has any fee applications
     */
    public function hasAnyFeeApplications(int $merchantId): bool;

    /**
     * Check if shop has any fee applications
     *
     * @param int $shopId Shop ID (internal)
     * @return bool Whether the shop has any fee applications
     */
    public function hasAnyShopFeeApplications(int $shopId): bool;

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

    /**
     * Get shop fee applications within a specified date range
     *
     * @param int $shopId Shop ID (internal)
     * @param int|null $feeTypeId Fee type ID (optional)
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Array of fee history records
     */
    public function getShopFeeApplicationsInDateRange(
        int $shopId,
        ?int $feeTypeId,
        string $startDate,
        string $endDate
    ): array;
}
