<?php

namespace App\Repositories;

use App\Models\FeeType;
use App\Models\MerchantFee;
use App\Models\FeeHistory;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use Illuminate\Support\Facades\DB;

class FeeRepository implements FeeRepositoryInterface
{
    public function __construct(private MerchantRepository $merchantRepository)
    {
    }

    public function getMerchantFees(int $merchantId, string $date = null)
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

    public function getActiveFeeTypes()
    {
        return FeeType::where('active', true)->get();
    }

    public function createMerchantFee(array $data)
    {
        return MerchantFee::create($data);
    }

    public function updateMerchantFee(int $feeId, array $data)
    {
        $fee = MerchantFee::findOrFail($feeId);
        $fee->update($data);
        return $fee;
    }

    public function logFeeApplication(array $feeData)
    {
        return FeeHistory::create($feeData);
    }
}
