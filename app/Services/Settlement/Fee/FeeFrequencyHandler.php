<?php

namespace App\Services\Settlement\Fee;

use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Fee\interfaces\FeeFrequencyHandlerInterface;
use Carbon\Carbon;

/**
 * Handles the frequency-based logic for fee applications (both merchant and shop level)
 */
readonly class FeeFrequencyHandler implements FeeFrequencyHandlerInterface
{
    public function __construct(
        private FeeRepositoryInterface $feeRepository,
        private DynamicLogger $logger,
        private MerchantRepository $merchantRepository,
    ) {}

    /**
     * Determines if a merchant-level fee should be applied
     */
    public function shouldApplyFee(
        string $frequencyType,
        int $merchantId,
        int $feeTypeId,
        array $dateRange
    ): bool {
        $startDate = Carbon::parse($dateRange['end']);
        $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

        $this->logger->log('info', 'Checking if merchant fee should be applied', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
        ]);

        $shouldApply = match ($frequencyType) {
            'transaction', 'daily', 'weekly' => true,
            'one_time' => $this->feeRepository->getLastFeeApplication($merchantId, $feeTypeId) === null,
            'monthly' => $this->shouldApplyMonthlyFee($merchantId, $feeTypeId, $startDate, false),
            'yearly' => $this->shouldApplyYearlyFee($merchantId, $feeTypeId, $startDate, false),
            default => false
        };

        $this->logger->log('info', 'Merchant fee application decision', [
            'merchant_id' => $merchantId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'should_apply' => $shouldApply,
        ]);

        return $shouldApply;
    }

    /**
     * Determines if a shop-level fee should be applied
     */
    public function shouldApplyShopFee(
        string $frequencyType,
        int $shopId,
        int $feeTypeId,
        array $dateRange
    ): bool {
        $startDate = Carbon::parse($dateRange['end']);

        $this->logger->log('info', 'Checking if shop fee should be applied', [
            'shop_id' => $shopId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'start_date' => $dateRange['start'],
            'end_date' => $dateRange['end'],
        ]);

        $shouldApply = match ($frequencyType) {
            'transaction', 'daily', 'weekly' => true,
            'one_time' => $this->feeRepository->getLastShopFeeApplication($shopId, $feeTypeId) === null,
            'monthly' => $this->shouldApplyMonthlyFee($shopId, $feeTypeId, $startDate, true),
            'yearly' => $this->shouldApplyYearlyFee($shopId, $feeTypeId, $startDate, true),
            default => false
        };

        $this->logger->log('info', 'Shop fee application decision', [
            'shop_id' => $shopId,
            'fee_type_id' => $feeTypeId,
            'frequency_type' => $frequencyType,
            'should_apply' => $shouldApply,
        ]);

        return $shouldApply;
    }

    /**
     * Determines if a monthly fee should be applied
     */
    private function shouldApplyMonthlyFee(int $entityId, int $feeTypeId, Carbon $date, bool $isShop): bool
    {
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $this->logger->log('info', 'Checking monthly fee application', [
            'entity_id' => $entityId,
            'entity_type' => $isShop ? 'shop' : 'merchant',
            'fee_type_id' => $feeTypeId,
            'check_date' => $date->format('Y-m-d'),
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
        ]);

        // Check if this is the first report of the month
        $isFirstReportOfMonth = false;
        if ($date->day == 1 || ($date->day <= 7 && $date->format('Y-m') != $date->copy()->subDays(7)->format('Y-m'))) {
            $isFirstReportOfMonth = true;
        }

        // Check if fee was already applied this month
        $alreadyApplied = $isShop
            ? $this->wasAlreadyAppliedInDateRange($entityId, $feeTypeId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), true)
            : $this->wasAlreadyAppliedInDateRange($entityId, $feeTypeId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'), false);

        return !$alreadyApplied && $isFirstReportOfMonth;
    }

    /**
     * Determines if a yearly fee should be applied
     */
    private function shouldApplyYearlyFee(int $entityId, int $feeTypeId, Carbon $date, bool $isShop): bool
    {
        $yearStart = $date->copy()->startOfYear();
        $yearEnd = $date->copy()->endOfYear();

        $this->logger->log('info', 'Checking yearly fee application', [
            'entity_id' => $entityId,
            'entity_type' => $isShop ? 'shop' : 'merchant',
            'fee_type_id' => $feeTypeId,
            'check_date' => $date->format('Y-m-d'),
            'year_start' => $yearStart->format('Y-m-d'),
            'year_end' => $yearEnd->format('Y-m-d'),
        ]);

        // Check if this is the first report of the year
        $isFirstReportOfYear = false;
        if (($date->month == 1 && $date->day == 1) ||
            ($date->month == 1 && $date->day <= 7 && $date->year != $date->copy()->subDays(7)->year)) {
            $isFirstReportOfYear = true;
        }

        // Check if fee was already applied this year
        $alreadyApplied = $isShop
            ? $this->wasAlreadyAppliedInDateRange($entityId, $feeTypeId, $yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d'), true)
            : $this->wasAlreadyAppliedInDateRange($entityId, $feeTypeId, $yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d'), false);

        return !$alreadyApplied && $isFirstReportOfYear;
    }

    /**
     * Checks if a fee has already been applied within a given date range
     */
    private function wasAlreadyAppliedInDateRange(
        int $entityId,
        int $feeTypeId,
        string $startDate,
        string $endDate,
        bool $isShop
    ): bool {
        $this->logger->log('info', 'Checking previous fee applications', [
            'entity_id' => $entityId,
            'entity_type' => $isShop ? 'shop' : 'merchant',
            'fee_type_id' => $feeTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $feeApplications = $isShop
            ? $this->feeRepository->getShopFeeApplicationsInDateRange($entityId, $feeTypeId, $startDate, $endDate)
            : $this->feeRepository->getFeeApplicationsInDateRange($entityId, $feeTypeId, $startDate, $endDate);

        $result = !empty($feeApplications);

        $this->logger->log('info', 'Fee applications check result', [
            'entity_id' => $entityId,
            'entity_type' => $isShop ? 'shop' : 'merchant',
            'fee_type_id' => $feeTypeId,
            'application_count' => count($feeApplications),
            'was_already_applied' => $result,
        ]);

        return $result;
    }
}
