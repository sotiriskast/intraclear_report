<?php

namespace App\Repositories\Interfaces;

use App\Models\FeeHistory;

/**
 * Interface for Fee repository operations
 *
 * Defines contract for:
 * - Managing merchant fees
 * - Tracking fee histories
 * - Processing fee applications
 * - Fee type management
 */
interface FeeRepositoryInterface
{
    /**
     * Get active merchant fees for a given date
     */
    public function getMerchantFees(int $merchantId, ?string $date = null);

    /**
     * Get all active fee types
     */
    public function getActiveFeeTypes();

    /**
     * Create a new merchant fee
     */
    public function createMerchantFee(array $data);

    /**
     * Update an existing merchant fee
     */
    public function updateMerchantFee(int $feeId, array $data);

    /**
     * Log a fee application instance
     */
    public function logFeeApplication(array $feeData);

    /**
     * Get the last fee application for a merchant and fee type
     */
    public function getLastFeeApplication(int $merchantId, int $feeTypeId): ?FeeHistory;

    /**
     * Get fee applications within a date range
     */
    public function getFeeApplicationsInDateRange(
        int $merchantId,
        ?int $feeTypeId,
        string $startDate,
        string $endDate
    ): array;
}
