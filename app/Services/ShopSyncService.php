<?php

namespace App\Services;

use App\Models\FeeType;
use App\Repositories\FeeRepository;
use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Repositories\ShopSettingRepository;
use Illuminate\Support\Facades\DB;

/**
 * ShopSyncService handles synchronization of shop data
 * between different database connections and creates default settings.
 */
class ShopSyncService
{
    /**
     * Create a new ShopSyncService instance.
     */
    public function __construct(
        private readonly DynamicLogger $logger,
        private readonly ShopRepository $shopRepository,
        private readonly ShopSettingRepository $shopSettingRepository,
        private readonly MerchantRepository $merchantRepository,
        private readonly FeeRepository $feeRepository
    ) {}

    /**
     * Synchronizes shops for a specific merchant
     *
     * @param int $merchantAccountId Merchant account ID
     * @return array Statistics of sync operation
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

                    // Create default fees for new shop
                    $createdFees = $this->createDefaultShopFees($shop->id);
                    $stats['fees_created'] += $createdFees;
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
     * Create default fees for a new shop
     */
    private function createDefaultShopFees(int $shopId): int
    {
        try {
            $createdCount = 0;

            // Get default fee amounts from shop settings
            $settings = $this->shopSettingRepository->findByShop($shopId);
            if (!$settings) {
                return 0;
            }

            // Define default fees with their amounts from settings
            $defaultFees = [
                1 => $settings->mdr_percentage,              // MDR Fee
                2 => $settings->transaction_fee,             // Transaction Fee
                3 => $settings->monthly_fee,                 // Monthly Fee
                4 => $settings->setup_fee,                   // Setup Fee
                5 => $settings->payout_fee,                  // Payout Fee
                6 => $settings->refund_fee,                  // Refund Fee
                7 => $settings->declined_fee,                // Declined Fee
                8 => $settings->chargeback_fee,              // Chargeback Fee
                9 => $settings->mastercard_high_risk_fee_applied, // Mastercard High Risk Fee
                10 => $settings->visa_high_risk_fee_applied,  // Visa High Risk Fee
            ];

            foreach ($defaultFees as $feeTypeId => $amount) {
                // Only create fees with amounts > 0
                if ($amount > 0) {
                    $this->feeRepository->createShopFee([
                        'shop_id' => $shopId,
                        'fee_type_id' => $feeTypeId,
                        'amount' => $amount,
                        'effective_from' => now(),
                        'effective_to' => null,
                        'active' => true,
                    ]);
                    $createdCount++;
                }
            }

            $this->logger->log('info', 'Created default fees for shop', [
                'shop_id' => $shopId,
                'fees_created' => $createdCount,
            ]);

            return $createdCount;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create default fees for shop', [
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
