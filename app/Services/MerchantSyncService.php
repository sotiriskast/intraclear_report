<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MerchantSyncService handles synchronization of merchant data
 * between different database connections.
 *
 * This service is responsible for:
 * - Retrieving merchant data from a payment gateway database
 * - Updating or inserting merchant records in the primary database
 * - Logging synchronization statistics and errors
 *
 * @package App\Services
 */
class MerchantSyncService
{
    /**
     * Create a new MerchantSyncService instance.
     *
     * @param DynamicLogger $logger Logging service for sync-related events
     */
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }
    /**
     * Synchronizes merchant data from the payment gateway to the primary database.
     *
     * This method performs the following steps:
     * 1. Retrieve existing merchants from the primary database
     * 2. Fetch merchant data from the payment gateway
     * 3. Begin a database transaction
     * 4. Iterate through source data and upsert (update or insert) merchants
     * 5. Commit the transaction
     * 6. Log synchronization statistics
     *
     * @return array Statistics of sync operation (new and updated merchant counts)
     *
     * @throws \Exception If synchronization fails, rolls back the transaction
     */
    public function sync(): array
    {
        try {
            // Initialize statistics tracking
            $stats = ['new' => 0, 'updated' => 0];
            // Retrieve existing merchants for comparison
            $existingMerchants = $this->getExistingMerchants();
            // Fetch source merchant data from payment gateway
            $sourceData = $this->getSourceData();
            // Start database transaction
            DB::connection('mariadb')->beginTransaction();
            // Process each merchant
            foreach ($sourceData as $merchant) {
                $isNew = !isset($existingMerchants[$merchant->id]);
                $this->upsertMerchant($merchant);
                $stats[$isNew ? 'new' : 'updated']++;
            }
            // Commit database transaction
            DB::connection('mariadb')->commit();
            $this->logger->log('info', 'Merchant sync completed successfully', [
                'new_merchants' => $stats['new'],
                'updated_merchants' => $stats['updated']
            ]);
            return $stats;
        } catch (\Exception $e) {
            // Rollback transaction in case of failure
            DB::connection('mariadb')->rollBack();
            //@todo Send email for not adding merchant
            $this->logger->log('error', 'Merchant sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Retrieves existing merchants from the primary database.
     *
     * Creates a lookup array mapping account IDs to merchant IDs.
     *
     * @return array Associative array of existing merchants
     */
    private function getExistingMerchants(): array
    {
        return DB::connection('mariadb')
            ->table('merchants')
            ->pluck('id', 'account_id')
            ->toArray();
    }
    /**
     * Fetches merchant source data from the payment gateway database.
     *
     * Retrieves key merchant information from the account table.
     *
     * @return \Illuminate\Support\Collection Collection of merchant data
     */
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
    /**
     * Upserts (updates or inserts) a merchant record in the primary database.
     *
     * If a merchant with the given account ID exists, updates the record.
     * If no such merchant exists, creates a new record.
     *
     * @param object $merchant Merchant data object from source
     */
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
