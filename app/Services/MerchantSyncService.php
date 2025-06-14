<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MerchantSyncFailed;
use App\Mail\NewMerchantCreated;

/**
 * MerchantSyncService handles synchronization of merchant data
 * between different database connections.
 *
 * This service is responsible for:
 * - Retrieving merchant data from a payment gateway database
 * - Updating or inserting merchant records in the primary database
 * - Synchronizing shop data for each merchant
 * - Logging synchronization statistics and errors
 * - Sending email notifications for sync failures and new merchants
 */
class MerchantSyncService
{
    /**
     * Create a new MerchantSyncService instance.
     *
     * @param DynamicLogger $logger Logging service for sync-related events
     * @param ShopSyncService $shopSyncService Service for syncing shops
     */
    public function __construct(
        private readonly DynamicLogger   $logger,
        private readonly ShopSyncService $shopSyncService
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
     * 5. Sync shops for each merchant
     * 6. Commit the transaction
     * 7. Log synchronization statistics
     *
     * @return array Statistics of sync operation (new and updated merchant counts, shop counts)
     *
     * @throws \Exception If synchronization fails, rolls back the transaction
     */
    public function sync(): array
    {
        try {
            // Initialize statistics tracking
            $stats = [
                'new' => 0,
                'updated' => 0,
                'shops' => [
                    'new' => 0,
                    'updated' => 0,
                    'settings_created' => 0,
                    'fees_created' => 0,
                ]
            ];

            // Retrieve existing merchants for comparison
            $existingMerchants = $this->getExistingMerchants();
            // Fetch source merchant data from payment gateway
            $sourceData = $this->getSourceData();

            $this->logger->log('info', 'Found merchants', [
                'count' => count($sourceData),
            ]);

            // Start database transaction
            DB::connection('pgsql')->beginTransaction();

            // Process each merchant
            foreach ($sourceData as $merchant) {
                $isNew = !isset($existingMerchants[$merchant->id]);
                $this->upsertMerchant($merchant);
                $stats[$isNew ? 'new' : 'updated']++;
                // Only send notification for new merchant
                if ($isNew) {
                    $merchantId = DB::connection('pgsql')
                        ->table('merchants')
                        ->where('account_id', $merchant->id)
                        ->value('id');

                    if ($merchantId) {
                        $this->sendNewMerchantNotification($merchant, $merchantId);
                    }
                }
                // Sync shops for this merchant (both new and existing merchants)
                try {
                    $shopStats = $this->shopSyncService->syncShopsForMerchant($merchant->id);
                    $stats['shops']['new'] += $shopStats['new'];
                    $stats['shops']['updated'] += $shopStats['updated'];
                    $stats['shops']['settings_created'] += $shopStats['settings_created'];
                    $stats['shops']['fees_created'] += $shopStats['fees_created'];
                } catch (\Exception $e) {
                    $this->logger->log('error', 'Failed to sync shops for merchant', [
                        'merchant_id' => $merchant->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other merchants instead of failing the entire sync
                }
            }

            // Commit database transaction
            DB::connection('pgsql')->commit();

            $this->logger->log('info', 'Merchant sync completed successfully', [
                'new_merchants' => $stats['new'],
                'updated_merchants' => $stats['updated'],
                'new_shops' => $stats['shops']['new'],
                'updated_shops' => $stats['shops']['updated'],
                'shop_settings_created' => $stats['shops']['settings_created'],
                'shop_fees_created' => $stats['shops']['fees_created'],
            ]);

            return $stats;
        } catch (\Exception $e) {
            // Rollback transaction in case of failure
            DB::connection('pgsql')->rollBack();

            // Log the error
            $this->logger->log('error', 'Merchant sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send email notification for sync failure
            $this->sendSyncFailureNotification($e);

            throw $e;
        }
    }

    /**
     * Sends an email notification when merchant sync fails.
     *
     * @param \Exception $exception The exception that caused the failure
     * @return void
     */
    private function sendSyncFailureNotification(\Exception $exception): void
    {
        try {
            $adminEmail = config('app.admin_email');

            // Validate that we have a valid email address
            if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->log('warning', 'Cannot send sync failure notification - no valid admin email configured', [
                    'config_value' => $adminEmail
                ]);
                return;
            }

            Mail::to($adminEmail)
                ->send(new MerchantSyncFailed($exception->getMessage(), $exception->getTraceAsString()));

            $this->logger->log('info', 'Merchant sync failure notification email sent');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to send merchant sync failure notification email', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sends an email notification when a new merchant is created.
     *
     * @param object $merchant The merchant data
     * @param int $merchantId The internal merchant ID
     * @return void
     */
    private function sendNewMerchantNotification($merchant, int $merchantId): void
    {
        try {
            $adminEmail = config('app.admin_email');

            // Validate that we have a valid email address
            if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->log('warning', 'Cannot send new merchant notification - no valid admin email configured', [
                    'merchant_id' => $merchantId,
                    'config_value' => $adminEmail
                ]);
                return;
            }

            Mail::to($adminEmail)
                ->send(new NewMerchantCreated($merchant, $merchantId));

            $this->logger->log('info', 'New merchant notification email sent', [
                'merchant_id' => $merchantId,
                'account_id' => $merchant->id,
                'name' => $merchant->corp_name,
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to send new merchant notification email', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);
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
        return DB::connection('pgsql')
            ->table('merchants')
            ->pluck('id', 'account_id')
            ->toArray();
    }

    /**
     * Fetches merchant source data from the payment gateway database,
     * filtered to only include merchants under Intraclear Bank.
     *
     * @return \Illuminate\Support\Collection Collection of merchant data
     */
    private function getSourceData()
    {
        // Find the Intraclear bank ID first
        $intraclearBank = DB::connection('payment_gateway_mysql')
            ->table('bank')
            ->where('bank', 'Intraclear')
            ->first();

        if (!$intraclearBank) {
            $this->logger->log('warning', 'Intraclear bank not found in the bank table');
            return collect(); // Return empty collection if Intraclear bank not found
        }

        $intraclearBankId = $intraclearBank->id;

        $this->logger->log('info', 'Found Intraclear bank', [
            'bank_id' => $intraclearBankId,
        ]);

        // Get all bank_keys linked to Intraclear
        $bankKeysQuery = DB::connection('payment_gateway_mysql')
            ->table('bank_keys')
            ->where('bank_id', $intraclearBankId)
            ->select('id');

        // Get all shops linked to these bank keys
        $shopIds = DB::connection('payment_gateway_mysql')
            ->table('shop_bank_keys')
            ->whereIn('key_id', function ($query) use ($intraclearBankId) {
                $query->select('id')
                    ->from('bank_keys')
                    ->where('bank_id', $intraclearBankId);
            })
            ->pluck('shop_id')
            ->unique();

        $this->logger->log('info', 'Found shop IDs linked to Intraclear', [
            'shop_count' => count($shopIds),
        ]);

        // Finally, get all merchants (accounts) linked to these shops
        return DB::connection('payment_gateway_mysql')
            ->table('account')
            ->whereIn('id', function ($query) use ($shopIds) {
                $query->select('account_id')
                    ->from('shop')
                    ->whereIn('id', $shopIds);
            })
            ->select([
                'id',
                'corp_name',
                'email',
                'phone',
                'active',
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
        DB::connection('pgsql')->table('merchants')->updateOrInsert(
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
