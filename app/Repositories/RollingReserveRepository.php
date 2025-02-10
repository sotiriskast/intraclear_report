<?php

namespace App\Repositories;

use App\Models\MerchantRollingReserve;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Services\DynamicLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class RollingReserveRepository implements RollingReserveRepositoryInterface
{
    public function __construct(
        private DynamicLogger $logger,
        private MerchantRepository $merchantRepository,
    ) {}

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
