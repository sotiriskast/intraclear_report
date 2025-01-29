<?php

namespace App\Services\Settlement\Fee;

use Carbon\Carbon;
use App\Repositories\Interfaces\FeeRepositoryInterface;

class FeeFrequencyHandler
{
    public function __construct(
        private readonly FeeRepositoryInterface $feeRepository
    )
    {
    }

    public function calculateFeeAmount(float $amount, bool $isPercentage, array $transactionData, string $frequencyType): float
    {
        $amount = $amount / 100;
        if ($isPercentage) {
            return ($transactionData['total_sales_eur']) * ($amount / 100);
        }
        return match ($frequencyType) {
            'transaction' => $this->calculateTransactionFee($amount, $transactionData),
            // For weekly, monthly, yearly, one_time - just return the fixed amount
            default => $amount
        };
    }

    private function calculateTransactionFee(float $amount, array $transactionData): float
    {
        return ($amount * ($transactionData['transaction_sales_count'] ?? 0));
    }

    public function shouldApplyFee(
        string $frequencyType,
        int    $merchantId,
        int    $feeTypeId,
        array  $dateRange
    ): bool
    {
        $startDate = Carbon::parse($dateRange['start']);

        return match ($frequencyType) {
            'transaction' => true, // Always apply
            'daily' => true,      // Apply every report
            'weekly' => true,     // Apply every report (since reports are weekly)
            'one_time' => $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId) === null,
            'monthly' => $this->shouldApplyMonthlyFee($merchantId, $feeTypeId, $startDate),
            'yearly' => $this->shouldApplyYearlyFee($merchantId, $feeTypeId, $startDate),
            default => false
        };
    }

    private function shouldApplyMonthlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        // Only apply in first week of month
        $firstDayOfMonth = $date->copy()->startOfMonth();
        $endOfFirstWeek = $firstDayOfMonth->copy()->endOfWeek();

        return $date->between($firstDayOfMonth, $endOfFirstWeek) &&
            !$this->wasAlreadyAppliedThisMonth($merchantId, $feeTypeId, $date);
    }

    private function shouldApplyYearlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        // Only apply in first week of year
        $firstDayOfYear = $date->copy()->startOfYear();
        $endOfFirstWeek = $firstDayOfYear->copy()->endOfWeek();

        return $date->between($firstDayOfYear, $endOfFirstWeek) &&
            !$this->wasAlreadyAppliedThisYear($merchantId, $feeTypeId, $date);
    }

    private function wasAlreadyAppliedThisMonth(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        $startOfMonth = $date->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $date->copy()->endOfMonth()->format('Y-m-d');

        $applications = $this->feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            $feeTypeId,
            $startOfMonth,
            $endOfMonth
        );

        return !empty($applications);
    }

    private function wasAlreadyAppliedThisYear(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        $startOfYear = $date->copy()->startOfYear()->format('Y-m-d');
        $endOfYear = $date->copy()->endOfYear()->format('Y-m-d');

        $applications = $this->feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            $feeTypeId,
            $startOfYear,
            $endOfYear
        );

        return !empty($applications);
    }
}
