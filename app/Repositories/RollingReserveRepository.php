<?php

namespace App\Repositories;

use App\Models\MerchantRollingReserve;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RollingReserveRepository implements RollingReserveRepositoryInterface
{
    public function getMerchantReserveSettings(int $merchantId, string $currency, string $date = null)
    {
        $query = MerchantRollingReserve::where('merchant_id', $merchantId)
            ->where('currency', $currency)
            ->where('active', true);

        if ($date) {
            $query->where('effective_from', '<=', $date)
                ->where(function($q) use ($date) {
                    $q->where('effective_to', '>=', $date)
                        ->orWhereNull('effective_to');
                });
        }

        return $query->first();
    }

    public function createReserveEntry(array $data)
    {
        // Check for existing entry first
        $existing = RollingReserveEntry::where('merchant_id', $data['merchant_id'])
            ->where('settlement_period_start', $data['settlement_period_start'])
            ->where('settlement_period_end', $data['settlement_period_end'])
            ->where('original_currency', $data['original_currency'])
            ->first();

        if ($existing) {
            \Log::info('Reserve entry already exists for this period', [
                'merchant_id' => $data['merchant_id'],
                'period' => $data['settlement_period_start'] . ' to ' . $data['settlement_period_end']
            ]);
            return $existing;
        }

        return RollingReserveEntry::create($data);
    }

    public function getReleaseableFunds(int $merchantId, string $date)
    {
        return RollingReserveEntry::where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->where('release_date', '<=', $date)
            ->whereNull('released_at')
            ->get();
    }

    public function markReserveAsReleased(array $entryIds)
    {
        return RollingReserveEntry::whereIn('id', $entryIds)
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'updated_at' => now()
            ]);
    }
}
