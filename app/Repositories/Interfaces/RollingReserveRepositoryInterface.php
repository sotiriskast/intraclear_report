<?php

namespace App\Repositories\Interfaces;
/**
 * Interface for Rolling Reserve repository operations
 *
 * Defines contract for:
 * - Managing merchant reserve settings
 * - Processing reserve releases
 * - Creating and tracking reserve entries
 * - Handling reserve periods and dates
 */
interface RollingReserveRepositoryInterface
{
    /**
     * Get merchant's reserve settings for a specific currency and date
     */
    public function getMerchantReserveSettings(int $merchantId, string $currency, ?string $date = null);

    /**
     * Get funds eligible for release on a specific date
     */
    public function createReserveEntry(array $data);

    /**
     * Mark specified reserve entries as released
     */
    public function getReleaseableFunds(int $merchantId, string $date);

    /**
     * Create a new reserve entry
     */
    public function markReserveAsReleased(array $entryIds);
}
