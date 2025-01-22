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
                'released_at' => now()
            ]);
    }
}
