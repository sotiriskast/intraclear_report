<?php

namespace App\Repositories;

use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Models\FeeHistory;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FeeRepository implements FeeRepositoryInterface
{
    public function __construct(private readonly MerchantRepository $merchantRepository)
    {
    }
    /**
     * Get active merchant fees for a given date
     *
     * @param int $merchantId
     * @param string|null $date
     * @return Collection
     */
    public function getMerchantFees(int $merchantId, string $date = null): Collection
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
     * @return Collection
     */
    public function getActiveFeeTypes(): Collection
    {
        return FeeType::where('active', true)->get();
    }
    /**
     * Create a new merchant fee
     *
     * @param array $data
     * @return MerchantFee
     */
    public function createMerchantFee(array $data): MerchantFee
    {
        return MerchantFee::create($data);
    }
    /**
     * Update an existing merchant fee
     *
     * @param int $feeId
     * @param array $data
     * @return MerchantFee
     */
    public function updateMerchantFee(int $feeId, array $data): MerchantFee
    {
        $fee = MerchantFee::findOrFail($feeId);
        $fee->update($data);
        return $fee;
    }
    /**
     * Log a fee application
     *
     * @param array $feeData
     * @return FeeHistory
     */
    public function logFeeApplication(array $feeData): FeeHistory
    {
        return FeeHistory::create($feeData);
    }
    /**
     * Get the last fee application for a merchant and fee type
     *
     * @param int $merchantId
     * @param int $feeTypeId
     * @return FeeHistory|null
     */
    public function getLastFeeApplication(int $merchantId, int $feeTypeId): ?FeeHistory
    {
        return FeeHistory::where('merchant_id', $merchantId)
            ->where('fee_type_id', $feeTypeId)
            ->orderBy('applied_date', 'desc')
            ->first();
    }
    /**
     * Get fee applications within a date range
     *
     * @param int $merchantId
     * @param int $feeTypeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getFeeApplicationsInDateRange(
        int $merchantId,
        int $feeTypeId,
        string $startDate,
        string $endDate
    ): array {
        return FeeHistory::where('merchant_id', $merchantId)
            ->where('fee_type_id', $feeTypeId)
            ->whereBetween('applied_date', [$startDate, $endDate])
            ->orderBy('applied_date', 'asc')
            ->get()
            ->toArray();
    }

}
