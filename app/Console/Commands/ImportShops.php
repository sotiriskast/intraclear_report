<?php

namespace App\Console\Commands;

use App\Services\DynamicLogger;
use App\Services\ShopSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:shops-import
                            {--merchant-id= : Specific merchant ID to sync shops for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync shops for merchants and create default settings and fees';

    public function __construct(
        private DynamicLogger $logger,
        private ShopSyncService $shopSyncService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        try {
            $merchantId = $this->option('merchant-id');

            if ($merchantId) {
                // Sync shops for specific merchant
                $this->info("Syncing shops for merchant ID: {$merchantId}");
                $stats = $this->shopSyncService->syncShopsForMerchant($merchantId);

                $message = "Merchant {$merchantId}: {$stats['new']} new shops, {$stats['updated']} updated shops, " .
                    "{$stats['settings_created']} settings created, {$stats['fees_created']} fees created";
                $this->info($message);
                $this->logger->log('info', $message);
            } else {
                // Sync shops for all merchants
                $this->info("Syncing shops for all merchants...");
                $merchants = DB::connection('pgsql')
                    ->table('merchants')
                    ->select('account_id', 'name')
                    ->where('active', true)
                    ->get();

                $totalStats = [
                    'merchants_processed' => 0,
                    'new' => 0,
                    'updated' => 0,
                    'settings_created' => 0,
                    'fees_created' => 0,
                    'failed' => 0
                ];

                foreach ($merchants as $merchant) {
                    try {
                        $this->line("Processing merchant: {$merchant->name} (ID: {$merchant->account_id})");
                        $stats = $this->shopSyncService->syncShopsForMerchant($merchant->account_id);

                        $totalStats['merchants_processed']++;
                        $totalStats['new'] += $stats['new'];
                        $totalStats['updated'] += $stats['updated'];
                        $totalStats['settings_created'] += $stats['settings_created'];
                        $totalStats['fees_created'] += $stats['fees_created'];

                        $this->info("  ✓ {$stats['new']} new, {$stats['updated']} updated, " .
                            "{$stats['settings_created']} settings, {$stats['fees_created']} fees");
                    } catch (\Exception $e) {
                        $totalStats['failed']++;
                        $this->error("  ✗ Failed: {$e->getMessage()}");
                        $this->logger->log('error', 'Shop sync failed for merchant', [
                            'merchant_id' => $merchant->account_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $message = "Shop sync completed: {$totalStats['merchants_processed']} merchants processed, " .
                    "{$totalStats['new']} new shops, {$totalStats['updated']} updated shops, " .
                    "{$totalStats['settings_created']} settings created, {$totalStats['fees_created']} fees created, " .
                    "{$totalStats['failed']} failed";
                $this->info($message);
                $this->logger->log('info', $message);
            }
        } catch (\Exception $e) {
            $message = "Shop sync failed: {$e->getMessage()}";
            $this->error($message);
            $this->logger->log('error', 'Shop sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
