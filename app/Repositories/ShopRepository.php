<?php

namespace App\Repositories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository for managing shops
 */
class ShopRepository
{
    /**
     * Find shop by external shop ID and merchant account ID
     *
     * @param int $shopId External shop ID from payment gateway
     * @param int $merchantAccountId Merchant account ID
     * @return Shop|null
     */
    public function findByExternalIdAndMerchant(int $shopId, int $merchantAccountId): ?Shop
    {
        return Shop::whereHas('merchant', function ($query) use ($merchantAccountId) {
            $query->where('account_id', $merchantAccountId);
        })->where('shop_id', $shopId)->first();
    }

    /**
     * Get shop by internal ID
     *
     * @param int $id Internal shop ID
     * @return Shop|null
     */
    public function find(int $id): ?Shop
    {
        return Shop::find($id);
    }

    /**
     * Get all shops for a merchant
     *
     * @param int $merchantId Internal merchant ID
     *
     */
    public function getByMerchant(int $merchantId)
    {
        return Shop::where('merchant_id', $merchantId);
    }

    /**
     * Create or update shop from external data
     *
     * @param array $shopData External shop data
     * @param int $merchantId Internal merchant ID
     * @return Shop
     */
    public function createOrUpdate(array $shopData, int $merchantId): Shop
    {
        return Shop::updateOrCreate(
            [
                'shop_id' => $shopData['shop_id'],
                'merchant_id' => $merchantId,
                'email' => $shopData['email'] ?? null,
                'website' => $shopData['website'] ?? null,
                'owner_name' => $shopData['owner_name'] ?? null,
                'active' => $shopData['active'] ?? true,
            ]
        );
    }

    /**
     * Get internal shop ID by external shop ID and merchant account ID
     *
     * @param int $externalShopId External shop ID
     * @param int $merchantAccountId Merchant account ID
     * @return int Internal shop ID
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getInternalIdByExternalId(int $externalShopId, int $merchantAccountId): int
    {
        $shop = $this->findByExternalIdAndMerchant($externalShopId, $merchantAccountId);

        if (!$shop) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Shop not found: external_id={$externalShopId}, merchant_account_id={$merchantAccountId}"
            );
        }

        return $shop->id;
    }
}
