<?php

namespace App\Repositories\Interfaces;

use App\DTO\ChargebackData;
use App\Enums\ChargebackStatus;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * Interface for chargeback tracking repository
 * Defines methods for persisting and retrieving chargeback data
 */
interface ChargebackTrackingRepositoryInterface
{
    /**
     * Records a new chargeback in the tracking system
     */
    public function trackNewChargeback(int $merchantId, int $shopId, ChargebackData $data): void;

    /**
     * Updates the status of an existing chargeback
     */
    public function updateChargebackStatus(string $transactionId, ChargebackStatus $newStatus): void;

    /**
     * Retrieves all pending settlements for a merchant
     *
     * @return array<int, array> Array of pending settlements
     */
    public function getPendingSettlements(int $merchantId): array;

    /**
     * Retrieves all pending settlements for a specific shop
     *
     * @return array<int, array> Array of pending settlements
     */
    public function getShopPendingSettlements(int $shopId): array;

    public function getChargebackByTransactionId(int $transaction_id): \stdClass;

    /**
     * Marks multiple chargebacks as settled
     *
     * @param  array<int>  $chargebackIds  IDs of chargebacks to mark as settled
     */
    public function markAsSettled(array $chargebackIds, ?CarbonInterface $settledDate = null): void;

    /**
     * Gets all chargebacks within a specific date range
     */
    public function getChargebacksByDateRange(int $merchantId, CarbonPeriod $dateRange): array;

    /**
     * Gets all chargebacks for a shop within a specific date range
     */
    public function getShopChargebacksByDateRange(int $shopId, CarbonPeriod $dateRange): array;

    public function findExistingChargeback(string $transactionId): ?array;
}
