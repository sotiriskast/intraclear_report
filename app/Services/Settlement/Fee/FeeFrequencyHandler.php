<?php

namespace App\Services\Settlement\Fee;

use Carbon\Carbon;
use App\Repositories\Interfaces\FeeRepositoryInterface;

class FeeFrequencyHandler
{
    public function __construct(
        private readonly FeeRepositoryInterface $feeRepository
    ) {}

    public function shouldApplyFee(
        string $frequencyType,
        int $merchantId,
        int $feeTypeId,
        array $dateRange
    ): bool {
        $startDate = Carbon::parse($dateRange['start']);
        $endDate = Carbon::parse($dateRange['end']);

        // Always apply transaction-based fees
        if ($frequencyType === 'transaction') {
            return true;
        }

        // Get the last fee application date
        $lastFeeApplication = $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId);

        if (!$lastFeeApplication) {
            return true; // First time application
        }

        $lastApplicationDate = Carbon::parse($lastFeeApplication->applied_date);

        return match ($frequencyType) {
            'monthly' => $this->isNewMonth($lastApplicationDate, $startDate),
            'yearly' => $this->isNewYear($lastApplicationDate, $startDate),
            'weekly' => $this->isNewWeek($lastApplicationDate, $startDate),
            'one_time' => !$lastFeeApplication,
            'daily' => true,
            default => false,
        };
    }

    private function isNewMonth(Carbon $lastApplication, Carbon $currentDate): bool
    {
        // Check if we're in a different month
        return $lastApplication->format('Y-m') !== $currentDate->format('Y-m');
    }

    private function isNewYear(Carbon $lastApplication, Carbon $currentDate): bool
    {
        // Check if we're in a different year
        return $lastApplication->year !== $currentDate->year;
    }

    private function isNewWeek(Carbon $lastApplication, Carbon $currentDate): bool
    {
        // Check if we're in a different week
        return $lastApplication->weekOfYear !== $currentDate->weekOfYear ||
            $lastApplication->year !== $currentDate->year;
    }
}
