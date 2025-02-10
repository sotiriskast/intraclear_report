<?php

namespace App\Repositories;

use App\Models\MerchantSetting;

class MerchantSettingRepository
{
    public function getAll(array $with = [], int $perPage = 10)
    {
        return MerchantSetting::with($with)
            ->paginate($perPage);
    }

    public function findById(int $id)
    {
        return MerchantSetting::findOrFail($id);
    }

    public function create(array $data)
    {
        $existingSetting = MerchantSetting::where('merchant_id', $data['merchant_id'])
            ->exists();

        if ($existingSetting) {
            throw new \Exception('A merchant setting already exists and cannot be created again.');
        }

        return MerchantSetting::create($data);
    }

    public function update(int $id, array $data)
    {
        $setting = $this->findById($id);
        $setting->update($data);

        return $setting;
    }

    public function delete(int $id)
    {
        $setting = $this->findById($id);

        return $setting->delete();
    }

    public function findByMerchant(int $merchantId)
    {
        return MerchantSetting::where('merchant_id', $merchantId)
            ->first();
    }

    public function isExistingForMerchant(int $merchantId): bool
    {
        return MerchantSetting::where('merchant_id', $merchantId)
            ->exists();
    }
}
