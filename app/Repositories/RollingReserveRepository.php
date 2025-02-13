<?php

namespace App\Repositories;

use App\Models\MerchantRollingReserve;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Services\DynamicLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
/**
 * Repository for managing rolling reserves and their entries
 *
 * This repository handles:
 * - Rolling reserve settings management
 * - Reserve fund releases
 * - Reserve entry creation and tracking
 * - Release date calculations
 *
 * @implements RollingReserveRepositoryInterface
 * @property DynamicLogger $logger Logger service
 * @property MerchantRepository $merchantRepository Repository for merchant operations
 */
class RollingReserveRepository implements RollingReserveRepositoryInterface
{
    public function __construct(
        private DynamicLogger $logger,
        private MerchantRepository $merchantRepository,
    ) {}
    /**
     * Get merchant's reserve settings for a currency
     *
     * @param int $merchantId Merchant's account ID
     * @param string $currency Currency code
     * @param string|null $date Specific date to check settings
     * @return MerchantRollingReserve|null
     */
    public function getMerchantReserveSettings(int $merchantId, string $currency, ?string $date = null)
    {
        $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
        $query = MerchantRollingReserve::where('merchant_id', $merchantId)
            ->where('currency', $currency)
            ->where('active', true);

        if ($date) {
            $query->where('effective_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->where('effective_to', '>=', $date)
                        ->orWhereNull('effective_to');
                });
        }

        return $query->first();
    }
    /**
     * Get funds eligible for release
     *
     * @param int $merchantId Merchant ID
     * @param string|CarbonInterface $date Date to check for releaseable funds
     * @return Collection<RollingReserveEntry> Collection of releaseable entries
     * @throws \Exception If retrieval fails
     */
    public function getReleaseableFunds(
        int $merchantId,
        string|CarbonInterface $date
    ): Collection {
        try {
            $query = RollingReserveEntry::query()
                ->where('merchant_id', $merchantId)
                ->where('status', 'pending')
                ->where('release_due_date', '<=', $date)
                ->whereNull('released_at');

            $releases = $query->get();

            $this->logger->log('info', 'Retrieved releaseable funds', [
                'merchant_id' => $merchantId,
                'date' => $date,
                'count' => $releases->count(),
            ]);

            return $releases;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving releaseable funds', [
                'merchant_id' => $merchantId,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    /**
     * Mark reserve entries as released
     *
     * @param array $entryIds Array of entry IDs to mark as released
     * @return int Number of entries updated
     * @throws \Exception If update fails
     */
    public function markReserveAsReleased(array $entryIds): int
    {
        try {
            $now = now();
            $affected = RollingReserveEntry::whereIn('id', $entryIds)
                ->update([
                    'status' => 'released',
                    'released_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->logger->log('info', 'Marked reserves as released', [
                'entry_ids' => $entryIds,
                'affected_count' => $affected,
            ]);

            return $affected;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error marking reserves as released', [
                'entry_ids' => $entryIds,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    /**
     * Create a new reserve entry
     *
     * @param array $data Reserve entry data
     * @return RollingReserveEntry Created entry
     * @throws \Exception If creation fails
     */
    public function createReserveEntry(array $data): RollingReserveEntry
    {
        try {
            $existing = RollingReserveEntry::where('merchant_id', $data['merchant_id'])
                ->where('period_start', $data['period_start'])
                ->where('period_end', $data['period_end'])
                ->where('original_currency', $data['original_currency'])
                ->first();

            if ($existing) {
                $this->logger->log('info', 'Reserve entry already exists for this period', [
                    'merchant_id' => $data['merchant_id'],
                    'period' => $data['period_start'].' to '.$data['period_end'],
                ]);

                return $existing;
            }

            return RollingReserveEntry::create($data);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create reserve entry', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
