<?php

namespace App\Services;

use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Repositories\ShopSettingRepository;
use Illuminate\Support\Facades\DB;

/**
 * ShopSyncService handles synchronization of shop data
 * between different database connections and creates default settings.
 */
readonly class ShopSyncService
{
    /**
     * Create a new ShopSyncService instance.
     */
    public function __construct(
        private DynamicLogger         $logger,
        private ShopRepository        $shopRepository,
        private ShopSettingRepository $shopSettingRepository,
        private MerchantRepository    $merchantRepository,
    ) {}

    /**
     * Synchronizes shops for a specific merchant
     *
     * @param int $merchantAccountId Merchant account ID
     * @return array Statistics of sync operation
     * @throws \Exception
     */
    public function syncShopsForMerchant(int $merchantAccountId): array
    {
        try {
            $stats = ['new' => 0, 'updated' => 0, 'settings_created' => 0, 'fees_created' => 0];

            // Get internal merchant ID
            $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantAccountId);

            // Get existing shops for this merchant
            $existingShops = $this->getExistingShops($internalMerchantId);

            // Fetch shops from payment gateway
            $sourceShops = $this->getSourceShopsForMerchant($merchantAccountId);

            $this->logger->log('info', 'Found shops for merchant', [
                'merchant_id' => $merchantAccountId,
                'shop_count' => count($sourceShops),
            ]);

            foreach ($sourceShops as $shopData) {
                $isNew = !isset($existingShops[$shopData->id]);

                // Create or update shop
                $shop = $this->shopRepository->createOrUpdate([
                    'shop_id' => $shopData->id,
                    'email' => $shopData->email ?? null,
                    'website' => $shopData->website ?? null,
                    'owner_name' => $shopData->owner_name ?? null,
                    'active' => $shopData->active ?? true,
                ], $internalMerchantId);

                $stats[$isNew ? 'new' : 'updated']++;

                if ($isNew) {
                    // Create default settings for new shop
                    if (!$this->shopSettingRepository->existsForShop($shop->id)) {
                        $this->createDefaultShopSettings($shop->id);
                        $stats['settings_created']++;
                    }
                }
            }

            $this->logger->log('info', 'Shop sync completed successfully', [
                'merchant_id' => $merchantAccountId,
                'new_shops' => $stats['new'],
                'updated_shops' => $stats['updated'],
                'settings_created' => $stats['settings_created'],
                'fees_created' => $stats['fees_created'],
            ]);

            return $stats;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Shop sync failed', [
                'merchant_id' => $merchantAccountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Create default settings for a new shop
     */
    private function createDefaultShopSettings(int $shopId): void
    {
        try {
            $this->shopSettingRepository->create($shopId);

            $this->logger->log('info', 'Created default settings for shop', [
                'shop_id' => $shopId,
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create default settings for shop', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get existing shops for a merchant
     */
    private function getExistingShops(int $internalMerchantId): array
    {
        return $this->shopRepository->getByMerchant($internalMerchantId)
            ->get()
            ->keyBy('shop_id')
            ->toArray();
    }

    /**
     * Get shops from payment gateway for a specific merchant
     */
    private function getSourceShopsForMerchant(int $merchantAccountId): array
    {
        return DB::connection('payment_gateway_mysql')
            ->table('shop')
            ->select([
                'id',
                'account_id',
                'email',
                'website',
                'owner_name',
                'active',
            ])
            ->where('account_id', $merchantAccountId)
            ->get()
            ->toArray();
    }
}
