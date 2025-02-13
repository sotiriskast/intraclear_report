<?php

namespace App\Repositories;

use App\Models\MerchantSetting;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository for managing merchant settings
 *
 * This repository handles:
 * - CRUD operations for merchant settings
 * - Validation of unique settings per merchant
 * - Retrieval of merchant-specific configurations
 */
class MerchantSettingRepository
{
    /**
     * Get all merchant settings with optional relations
     *
     * @param array $with Relations to eager load
     * @param int $perPage Number of items per page
     * @return LengthAwarePaginator
     */
    public function getAll(array $with = [], int $perPage = 10)
    {
        return MerchantSetting::with($with)
            ->paginate($perPage);
    }
    /**
     * Find merchant settings by ID
     *
     * @param int $id Setting ID
     * @return MerchantSetting
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id)
    {
        return MerchantSetting::findOrFail($id);
    }

    /**
     * Create new merchant settings
     *
     * @param array $data Setting data
     * @return MerchantSetting
     * @throws \Exception If settings already exist for merchant
     */
    public function create(array $data)
    {
        $existingSetting = MerchantSetting::where('merchant_id', $data['merchant_id'])
            ->exists();

        if ($existingSetting) {
            throw new \Exception('A merchant setting already exists and cannot be created again.');
        }

        return MerchantSetting::create($data);
    }
    /**
     * Update existing merchant settings
     *
     * @param int $id Setting ID
     * @param array $data Updated setting data
     * @return MerchantSetting
     */
    public function update(int $id, array $data)
    {
        $setting = $this->findById($id);
        $setting->update($data);

        return $setting;
    }
    /**
     * Delete merchant settings
     *
     * @param int $id Setting ID
     * @return bool True if deleted successfully
     */
    public function delete(int $id)
    {
        $setting = $this->findById($id);

        return $setting->delete();
    }
    /**
     * Find settings for specific merchant
     *
     * @param int $merchantId Merchant ID
     * @return MerchantSetting|null
     */
    public function findByMerchant(int $merchantId)
    {
        return MerchantSetting::where('merchant_id', $merchantId)
            ->first();
    }
    /**
     * Check if settings exist for merchant
     *
     * @param int $merchantId Merchant ID
     * @return bool True if settings exist
     */
    public function isExistingForMerchant(int $merchantId): bool
    {
        return MerchantSetting::where('merchant_id', $merchantId)
            ->exists();
    }
}
