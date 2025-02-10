<?php

namespace App\Repositories;

use App\Models\FeeHistory;
use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

readonly class FeeRepository implements FeeRepositoryInterface
{
    public function __construct(private MerchantRepository $merchantRepository) {}

    /**
     * Get active merchant fees for a given date
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
     */
    public function getActiveFeeTypes(): Collection
    {
        return FeeType::where('active', true)->get();
    }

    /**
     * Create a new merchant fee
     */
    public function createMerchantFee(array $data): MerchantFee
    {
        return MerchantFee::create($data);
    }

    /**
     * Update an existing merchant fee
     */
    public function updateMerchantFee(int $feeId, array $data): MerchantFee
    {
        $fee = MerchantFee::findOrFail($feeId);
        $fee->update($data);

        return $fee;
    }

    /**
     * Log a fee application
     */
    public function logFeeApplication(array $feeData): FeeHistory
    {
        return FeeHistory::create($feeData);
    }

    /**
     * Get the last fee application for a merchant and fee type
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
