<?php

namespace App\Console\Commands;

use App\Services\DynamicLogger;
use App\Services\MerchantSyncService;
use Illuminate\Console\Command;

class ImportMerchants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:merchants-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import merchants from processing database';

    public function __construct(
        private DynamicLogger $logger,
        private MerchantSyncService $merchantSync
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        try {
            $stats = $this->merchantSync->sync();
            $message = "Imported {$stats['new']} new merchants, created Merchant Settings {$stats['settings_created']}, updated {$stats['updated']} existing merchants";
            $this->info($message);
            $this->logger->log('debug', $message);
        } catch (\Exception $e) {
            $message = "Import failed: {$e->getMessage()}";
            $this->error($message);
            $this->logger->log('error', 'Merchant import failed', ['error' => $e->getMessage()]);
        }
    }
}
