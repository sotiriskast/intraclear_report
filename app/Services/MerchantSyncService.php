<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MerchantSyncService
{
    public function sync(): array
    {
        $stats = ['new' => 0, 'updated' => 0];
        $existingMerchants = $this->getExistingMerchants();
        $sourceData = $this->getSourceData();
        foreach ($sourceData as $merchant) {
            $isNew = !isset($existingMerchants[$merchant->id]);
            $this->upsertMerchant($merchant);
            $stats[$isNew ? 'new' : 'updated']++;
        }
        return $stats;
    }

    private function getExistingMerchants(): array
    {
        return DB::connection('mariadb')->table('merchants')
            ->pluck('merchant_id')
            ->flip()
            ->toArray();
    }

    private function getSourceData(): \Illuminate\Support\Collection
    {
        return DB::connection('payment_gateway_mysql')
            ->table('account')
            ->select([
                'account.id as merchant_id', // Change this line
                'corp_name',
                'email',
                'phone',
                'active'
            ])
            ->get();
    }

    private function upsertMerchant($merchant): void
    {
        DB::connection('mariadb')->table('merchants')->updateOrInsert(
            ['merchant_id' => $merchant->merchant_id], // Change this line
            [
                'name' => $merchant->corp_name,
                'email' => $merchant->email,
                'phone' => $merchant->phone,
                'active' => $merchant->active,
                'updated_at' => now()
            ]
        );
    }
}
