<?php

namespace App\Repositories;

use App\Models\FeeHistory;
use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
/**
 * Repository for managing merchant fees and fee histories
 *
 * This repository handles:
 * - Retrieval and management of merchant fees
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
     * Get active merchant fees for a given date
     *
     * @param int $merchantId Merchant's account ID
     * @param string|null $date Specific date to check fees (null for current)
     * @return Collection<MerchantFee> Collection of active merchant fees
     */
    public function getMerchantFees(int $merchantId, ?string $date = null): Collection
    {
        $query = MerchantFee::with(['feeType'])
            ->where('merchant_id', $this->merchantRepository->getMerchantIdByAccountId($merchantId))
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
     * Create a new merchant fee
     *
     * @param array $data Fee data including merchant_id, fee_type_id, amount
     * @return MerchantFee Newly created merchant fee
     */
    public function createMerchantFee(array $data): MerchantFee
    {
        return MerchantFee::create($data);
    }

    /**
     * Update an existing merchant fee
     *
     * @param int $feeId Fee ID to update
     * @param array $data Updated fee data
     * @return MerchantFee Updated merchant fee
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateMerchantFee(int $feeId, array $data): MerchantFee
    {
        $fee = MerchantFee::findOrFail($feeId);
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
        return FeeHistory::where('merchant_id', $merchantId)->exists();
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
        $query = FeeHistory::query()->where('merchant_id', $merchantId)
            ->whereBetween('applied_date', [$startDate, $endDate]);
        if ($feeTypeId !== null) {
            $query->where('fee_type_id', $feeTypeId);
        }
        return $query->orderBy('applied_date', 'asc')
            ->get()
            ->toArray();
    }
}
