<?php

namespace App\Repositories;

use App\Models\FeeHistory;
use App\Models\FeeType;
use App\Models\ShopFee;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for managing merchant and shop fees and fee histories
 *
 * This repository handles:
 * - Retrieval and management of merchant fees
 * - Retrieval and management of shop fees
 * - Fee type management
 * - Fee application logging
 * - Fee history tracking
 * - Period-based fee calculations
 *
 * @implements FeeRepositoryInterface
 * @property MerchantRepository $merchantRepository Repository for merchant operations
 */
readonly class FeeRepository implements FeeRepositoryInterface
{
    public function __construct(private MerchantRepository $merchantRepository) {}

    /**
     * Get active shop fees for a given date
     *
     * @param int $shopId Shop ID (internal)
     * @param string|null $date Specific date to check fees (null for current)
     * @return Collection<ShopFee> Collection of active shop fees
     */
    public function getShopFees(int $shopId, ?string $date = null): Collection
    {
        $query = ShopFee::with(['feeType'])
            ->where('shop_id', $shopId)
            ->where('active', true);

        if ($date) {
            $query->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->where('effective_to', '>=', $date)
                        ->orWhereNull('effective_to');
                });
        }

        return $query->get();
    }

    /**
     * Get all active fee types
     *
     * @return Collection<FeeType> Collection of active fee types
     */
    public function getActiveFeeTypes(): Collection
    {
        return FeeType::where('active', true)->get();
    }

    /**
     * Create a new shop fee
     *
     * @param array $data Fee data including shop_id, fee_type_id, amount
     * @return ShopFee Newly created shop fee
     */
    public function createShopFee(array $data): ShopFee
    {
        return ShopFee::create($data);
    }

    /**
     * Update an existing shop fee
     *
     * @param int $feeId Fee ID to update
     * @param array $data Updated fee data
     * @return ShopFee Updated shop fee
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateShopFee(int $feeId, array $data): ShopFee
    {
        $fee = ShopFee::findOrFail($feeId);
        $fee->update($data);

        return $fee;
    }

    /**
     * Log a fee application instance
     *
     * @param array $feeData Fee application data
     * @return FeeHistory Created fee history record
     */
    public function logFeeApplication(array $feeData): FeeHistory
    {
        return FeeHistory::create($feeData);
    }

    /**
     * Get the most recent fee application for a merchant and fee type
     *
     * @param int $merchantId Merchant ID
     * @param int $feeTypeId Fee type ID
     * @return FeeHistory|null Latest fee history record or null if none exists
     */
    public function getLastFeeApplication(int $merchantId, int $feeTypeId): ?FeeHistory
    {
        return FeeHistory::where('merchant_id', $merchantId)
            ->where('fee_type_id', $feeTypeId)
            ->whereNull('shop_id') // Ensure it's a merchant-level fee
            ->orderBy('applied_date', 'desc')
            ->first();
    }

    /**
     * Get the most recent fee application for a shop and fee type
     *
     * @param int $shopId Shop ID (internal)
     * @param int $feeTypeId Fee type ID
     * @return FeeHistory|null Latest fee history record or null if none exists
     */
    public function getLastShopFeeApplication(int $shopId, int $feeTypeId): ?FeeHistory
    {
        return FeeHistory::where('shop_id', $shopId)
            ->where('fee_type_id', $feeTypeId)
            ->orderBy('applied_date', 'desc')
            ->first();
    }

    /**
     * Check if merchant has any fee applications
     *
     * @param int $merchantId Merchant ID
     * @return bool Whether the merchant has any fee applications
     */
    public function hasAnyFeeApplications(int $merchantId): bool
    {
        return FeeHistory::where('merchant_id', $merchantId)
            ->whereNull('shop_id') // Ensure it's a merchant-level fee
            ->exists();
    }

    /**
     * Check if shop has any fee applications
     *
     * @param int $shopId Shop ID (internal)
     * @return bool Whether the shop has any fee applications
     */
    public function hasAnyShopFeeApplications(int $shopId): bool
    {
        return FeeHistory::where('shop_id', $shopId)->exists();
    }

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
    ): array {
        $query = FeeHistory::query()
            ->where('merchant_id', $merchantId)
            ->whereNull('shop_id') // Ensure it's a merchant-level fee
            ->whereBetween('applied_date', [$startDate, $endDate]);

        if ($feeTypeId !== null) {
            $query->where('fee_type_id', $feeTypeId);
        }

        return $query->orderBy('applied_date', 'asc')
            ->get()
            ->toArray();
    }

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
    ): array {
        $query = FeeHistory::query()
            ->where('shop_id', $shopId)
            ->whereBetween('applied_date', [$startDate, $endDate]);

        if ($feeTypeId !== null) {
            $query->where('fee_type_id', $feeTypeId);
        }

        return $query->orderBy('applied_date', 'asc')
            ->get()
            ->toArray();
    }
}
