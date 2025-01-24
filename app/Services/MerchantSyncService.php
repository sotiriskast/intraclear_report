<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MerchantSyncService
{
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }

    public function sync(): array
    {
        try {
            $stats = ['new' => 0, 'updated' => 0];
            $existingMerchants = $this->getExistingMerchants();
            $sourceData = $this->getSourceData();
            DB::connection('mariadb')->beginTransaction();
            foreach ($sourceData as $merchant) {
                $isNew = !isset($existingMerchants[$merchant->id]);
                $this->upsertMerchant($merchant);
                $stats[$isNew ? 'new' : 'updated']++;
            }
            DB::connection('mariadb')->commit();
            $this->logger->log('info', 'Merchant sync completed successfully', [
                'new_merchants' => $stats['new'],
                'updated_merchants' => $stats['updated']
            ]);
            return $stats;
        } catch (\Exception $e) {
            DB::connection('mariadb')->rollBack();
            //@todo Send email for not adding merchant
            $this->logger->log('error', 'Merchant sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function getExistingMerchants(): array
    {
        return DB::connection('mariadb')
            ->table('merchants')
            ->pluck('id', 'account_id')
            ->toArray();
    }

    private function getSourceData()
    {
        return DB::connection('payment_gateway_mysql')
            ->table('account')
            ->select([
                'id',
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
            ['account_id' => $merchant->id],
            [
                'name' => $merchant->corp_name,
                'email' => $merchant->email,
                'phone' => $merchant->phone,
                'active' => $merchant->active,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
