<?php

namespace App\Services\Settlement\Fee;

use Carbon\Carbon;
use App\Repositories\Interfaces\FeeRepositoryInterface;

readonly class FeeFrequencyHandler
{
    public function __construct(
        private FeeRepositoryInterface $feeRepository
    ) {
    }

    public function shouldApplyFee(
        string $frequencyType,
        int $merchantId,
        int $feeTypeId,
        array $dateRange
    ): bool {
        $startDate = Carbon::parse($dateRange['start']);

        return match ($frequencyType) {
            'transaction', 'daily', 'weekly' => true,
            'one_time' => $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId) === null,
            'monthly' => $this->shouldApplyMonthlyFee($merchantId, $feeTypeId, $startDate),
            'yearly' => $this->shouldApplyYearlyFee($merchantId, $feeTypeId, $startDate),
            default => false
        };
    }

    private function shouldApplyMonthlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        $firstDayOfMonth = $date->copy()->startOfMonth();
        $endOfFirstWeek = $firstDayOfMonth->copy()->endOfWeek();

        return $date->between($firstDayOfMonth, $endOfFirstWeek) &&
            !$this->wasAlreadyAppliedInDateRange(
                $merchantId,
                $feeTypeId,
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth()
            );
    }

    private function shouldApplyYearlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        $firstDayOfYear = $date->copy()->startOfYear();
        $endOfFirstWeek = $firstDayOfYear->copy()->endOfWeek();

        return $date->between($firstDayOfYear, $endOfFirstWeek) &&
            !$this->wasAlreadyAppliedInDateRange(
                $merchantId,
                $feeTypeId,
                $date->copy()->startOfYear(),
                $date->copy()->endOfYear()
            );
    }

    private function wasAlreadyAppliedInDateRange(
        int $merchantId,
        int $feeTypeId,
        Carbon $startDate,
        Carbon $endDate
    ): bool {
        return !empty($this->feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            $feeTypeId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ));
    }
}
