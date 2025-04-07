<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use Carbon\Carbon;

/**
 * Handles the frequency-based logic for fee applications
 * Determines whether fees should be applied based on their frequency type
 * and previous application history
 */
readonly class FeeFrequencyHandler implements FeeFrequencyHandlerInterface
{
    /**
     * @param  FeeRepositoryInterface  $feeRepository  Repository for checking fee application history
     */
    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger $logger,
        private MerchantRepository $merchantRepository,
    ) {

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
     * @param  string  $frequencyType  Type of frequency (transaction, daily, weekly, monthly, yearly, one_time)
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  array  $dateRange  Start and end dates for the period being processed
     * @return bool Whether the fee should be applied
     */
    public function shouldApplyFee(
        string $frequencyType,
        int $merchantId,
        int $feeTypeId,
        array $dateRange
    ): bool {
//        $startDate = Carbon::parse($dateRange['start']);
        $startDate = Carbon::parse($dateRange['end']);
        $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

        $this->logger->log('info', 'Checking if fee should be applied', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
        ]);


        $shouldApply = match ($frequencyType) {
            // Always apply transaction, daily, and weekly fees
            'transaction', 'daily', 'weekly' => true,

            // One-time fees are applied only if they've never been applied before
            'one_time' => $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId) === null,

            // Monthly fees
            'monthly' => $this->shouldApplyMonthlyFee($merchantId, $feeTypeId, $startDate),

            // Yearly fees
            'yearly' => $this->shouldApplyYearlyFee($merchantId, $feeTypeId, $startDate),

            // Default to not applying the fee for unknown frequency types
            default => false
        };

        $this->logger->log('info', 'Fee application decision', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'should_apply' => $shouldApply,
        ]);

        return $shouldApply;
    }

    /**
     * Determines if a monthly fee should be applied
     * Monthly fees are applied only in the first week of the month
     * and only if they haven't already been applied for the current month
     *
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  Carbon  $date  Date being checked
     * @return bool Whether the monthly fee should be applied
     */
    private function shouldApplyMonthlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        // Get first day of the month for the given date
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $this->logger->log('info', 'Checking monthly fee application', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'check_date' => $date->format('Y-m-d'),
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
        ]);

        // Special case: Check if this is the first report of the month
        // The report date range crosses from one month to another (like Dec 31 - Jan 7)
        $isFirstReportOfMonth = false;

        // For special handling of reports that start in the last week of previous month
        // and end in the first week of current month (like Dec 31 - Jan 7)
        // We consider the report that starts in the last week of month and contains the 1st of next month
        // OR the report that contains the 1st day of the month
        if ($date->day == 1 || ($date->day <= 7 && $date->format('Y-m') != $date->copy()->subDays(7)->format('Y-m'))) {
            $isFirstReportOfMonth = true;
            $this->logger->log('info', 'This is the first report of the month', [
                'date' => $date->format('Y-m-d'),
            ]);
        }

        // If a fee has already been applied for this month, don't apply again
        $alreadyApplied = $this->wasAlreadyAppliedInDateRange(
            $merchantId,
            $feeTypeId,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );

        $this->logger->log('info', 'Monthly fee application check', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'already_applied' => $alreadyApplied,
            'is_first_report_of_month' => $isFirstReportOfMonth
        ]);

        if ($alreadyApplied) {
            return false;
        }

        // Only apply the fee if this is the first report of the month
        return $isFirstReportOfMonth;
    }

    /**
     * Determines if a yearly fee should be applied
     * Yearly fees are applied only in the first week of the year
     * and only if they haven't already been applied for the current year
     *
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  Carbon  $date  Date being checked
     * @return bool Whether the yearly fee should be applied
     */
    private function shouldApplyYearlyFee(int $merchantId, int $feeTypeId, Carbon $date): bool
    {
        // Define the year period
        $yearStart = $date->copy()->startOfYear();
        $yearEnd = $date->copy()->endOfYear();

        $this->logger->log('info', 'Checking yearly fee application', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'check_date' => $date->format('Y-m-d'),
            'year_start' => $yearStart->format('Y-m-d'),
            'year_end' => $yearEnd->format('Y-m-d'),
        ]);

        // Special case: Check if this is the first report of the year
        // The report date range crosses from one year to another (like Dec 31 - Jan 7)
        $isFirstReportOfYear = false;

        // For a yearly fee, the report that contains Jan 1 or starts in the last week of December
        // and ends in the first week of January (Dec 31 - Jan 7)
        if (($date->month == 1 && $date->day == 1) ||
            ($date->month == 1 && $date->day <= 7 && $date->year != $date->copy()->subDays(7)->year)) {
            $isFirstReportOfYear = true;
            $this->logger->log('info', 'This is the first report of the year', [
                'date' => $date->format('Y-m-d'),
            ]);
        }

        // If a fee has already been applied for this year, don't apply again
        $alreadyApplied = $this->wasAlreadyAppliedInDateRange(
            $merchantId,
            $feeTypeId,
            $yearStart->format('Y-m-d'),
            $yearEnd->format('Y-m-d')
        );

        $this->logger->log('info', 'Yearly fee application check', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'already_applied' => $alreadyApplied,
            'is_first_report_of_year' => $isFirstReportOfYear
        ]);

        if ($alreadyApplied) {
            return false;
        }

        // Only apply the fee if this is the first report of the year
        return $isFirstReportOfYear;
    }

    /**
     * Checks if a fee has already been applied within a given date range
     * Used to prevent duplicate fee applications in the same period
     *
     * @param  int  $merchantId  ID of the merchant
     * @param  int  $feeTypeId  Type of fee being checked
     * @param  string  $startDate  Start of the date range in Y-m-d format
     * @param  string  $endDate  End of the date range in Y-m-d format
     * @return bool Whether the fee was already applied in the date range
     */
    private function wasAlreadyAppliedInDateRange(
        int $merchantId,
        int $feeTypeId,
        string $startDate,
        string $endDate
    ): bool {
        $this->logger->log('info', 'Checking previous fee applications', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $feeApplications = $this->feeRepository->getFeeApplicationsInDateRange(
            $merchantId,
            $feeTypeId,
            $startDate,
            $endDate
        );

        $result = !empty($feeApplications);

        $this->logger->log('info', 'Fee applications check result', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'application_count' => count($feeApplications),
            'was_already_applied' => $result,
        ]);

        return $result;
    }
}
