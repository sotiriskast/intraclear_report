<?php

namespace App\Repositories;

use App\Models\Shop;
use App\Models\ShopSetting;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for managing shop settings
 */
class ShopSettingRepository
{
    /**
     * Find settings for a specific shop
     *
     * @param int $shopId Shop ID
     * @return ShopSetting|null
     */
    public function findByShop(int $shopId): ?ShopSetting
    {
        return ShopSetting::where('shop_id', $shopId)->first();
    }

    /**
     * Create default settings for a new shop
     *
     * @param int $shopId Shop ID
     * @param array $data Optional data to override defaults
     * @return ShopSetting
     */
    public function create(int $shopId, array $data = []): ShopSetting
    {
        $defaultSettings = [
            'shop_id' => $shopId,
            'rolling_reserve_percentage' => 1000,
            'holding_period_days' => 180,
            'mdr_percentage' => 500,
            'transaction_fee' => 35,
            'declined_fee' => 25,
            'payout_fee' => 100,
            'refund_fee' => 100,
            'chargeback_fee' => 4000,
            'monthly_fee' => 15000,
            'mastercard_high_risk_fee_applied' => 15000,
            'visa_high_risk_fee_applied' => 15000,
            'setup_fee' => 50000,
            'setup_fee_charged' => false,
            'exchange_rate_markup' => 1.01,
            'fx_rate_markup' => 0,
        ];

        $mergedData = array_merge($defaultSettings, $data);
        return ShopSetting::create($mergedData);
    }

    /**
     * Update existing shop settings
     *
     * @param int $id Setting ID
     * @param array $data Updated setting data
     * @return ShopSetting
     */
    public function update(int $id, array $data): ShopSetting
    {
        $setting = ShopSetting::findOrFail($id);
        $setting->update($data);
        return $setting;
    }

    /**
     * Check if settings exist for shop
     *
     * @param int $shopId Shop ID
     * @return bool
     */
    public function existsForShop(int $shopId): bool
    {
        return ShopSetting::where('shop_id', $shopId)->exists();
    }
}
