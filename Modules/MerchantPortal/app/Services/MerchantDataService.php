<?php

namespace Modules\MerchantPortal\Services;

use App\Models\Merchant;
use Illuminate\Support\Facades\Cache;

class MerchantDataService
{
    public function getMerchantData(int $merchantId): array
    {
        return Cache::remember(
            "merchant_data_{$merchantId}",
            now()->addMinutes(30),
            function () use ($merchantId) {
                $merchant = Merchant::with(['shops', 'users'])
                    ->findOrFail($merchantId);

                return [
                    'merchant' => $merchant,
                    'total_shops' => $merchant->shops->count(),
                    'active_users' => $merchant->users()->where('is_active', true)->count(),
                    'settings' => $merchant->settings ?? [],
                ];
            }
        );
    }

    public function clearMerchantCache(int $merchantId): void
    {
        Cache::forget("merchant_data_{$merchantId}");
    }
}

