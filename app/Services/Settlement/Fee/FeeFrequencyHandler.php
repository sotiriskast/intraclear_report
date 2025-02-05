<?php

namespace App\Services\Settlement\Fee;

use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use Carbon\Carbon;
use App\Repositories\Interfaces\FeeRepositoryInterface;

/**
 * Handles the frequency-based logic for fee applications
 * Determines whether fees should be applied based on their frequency type
 * and previous application history
 */
readonly class FeeFrequencyHandler implements FeeFrequencyHandlerInterface
{
    /**
     * @param FeeRepositoryInterface $feeRepository Repository for checking fee application history
     */
    public function __construct(
        private FeeRepositoryInterface $feeRepository
    )
    {
    }

    /**
     * Determines if a fee should be applied based on its frequency type and application history
     *
     * Frequency types:
     * - transaction: Applied to every transaction
     * - daily: Applied daily
     * - weekly: Applied weekly
     * - monthly: Applied in the first week of each month
     * - yearly: Applied in the first week of each year
     * - one_time: Applied only once ever
     *
     * @param string $frequencyType Type of frequency (transaction, daily, weekly, monthly, yearly, one_time)
     * @param int $merchantId ID of the merchant
     * @param int $feeTypeId Type of fee being checked
     * @param array $dateRange Start and end dates for the period being processed
     * @return bool Whether the fee should be applied
     */
    public function shouldApplyFee(
        string $frequencyType,
        int    $merchantId,
        int    $feeTypeId,
        array  $dateRange
    ): bool
    {
        $startDate = Carbon::parse($dateRange['start']);

        return match ($frequencyType) {
            // Always apply transaction, daily, and weekly fees
            'transaction', 'daily', 'weekly' => true,
            // One-time fees are applied only if they've never been applied before
            'one_time' => $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId) === null,
            // Monthly fees are applied in the first week of each month
            'monthly' => $this->shouldApplyMonthlyFee($merchantId, $feeTypeId, $startDate),
            // Yearly fees are applied in the first week of each year
            'yearly' => $this->shouldApplyYearlyFee($merchantId, $feeTypeId, $startDate),
            // Default to not applying the fee for unknown frequency types
            default => false
        };
    }

    /**
     * Determines if a monthly fee should be applied
     * Monthly fees are applied only in the first week of the month
     * and only if they haven't already been applied for the current month
     *
     * @param int $merchantId ID of the merchant
     * @param int $feeTypeId Type of fee being checked
     * @param Carbon $date Date being checked
     * @return bool Whether the monthly fee should be applied
     */
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

    /**
     * Determines if a yearly fee should be applied
     * Yearly fees are applied only in the first week of the year
     * and only if they haven't already been applied for the current year
     *
     * @param int $merchantId ID of the merchant
     * @param int $feeTypeId Type of fee being checked
     * @param Carbon $date Date being checked
     * @return bool Whether the yearly fee should be applied
     */
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

    /**
     * Checks if a fee has already been applied within a given date range
     * Used to prevent duplicate fee applications in the same period
     *
     * @param int $merchantId ID of the merchant
     * @param int $feeTypeId Type of fee being checked
     * @param Carbon $startDate Start of the date range
     * @param Carbon $endDate End of the date range
     * @return bool Whether the fee was already applied in the date range
     */
    private function wasAlreadyAppliedInDateRange(
        int    $merchantId,
        int    $feeTypeId,
        Carbon $startDate,
        Carbon $endDate
    ): bool
    {
        return !empty($this->feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            $feeTypeId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ));
    }
}
