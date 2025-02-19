<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Services\Api\ApiKeyService;
use App\Services\DynamicLogger;
use Illuminate\Console\Command;

class GenerateMerchantApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:api-key:generate
                          {account_id : The merchant\'s account ID}
                          {--force : Force regenerate if API key already exists}';

    protected $description = 'Generate an API key for a merchant';

    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $accountId = $this->argument('account_id');
        $force = $this->option('force');

        $result = $this->apiKeyService->handleApiKeyGeneration($accountId, $force);

        if (!$result['success']) {
            if (isset($result['requires_confirmation'])) {
                if ($this->confirm('Merchant already has an API key. Do you want to regenerate it?')) {
                    $result = $this->apiKeyService->handleApiKeyGeneration($accountId, true);
                } else {
                    $this->info('Operation cancelled.');
                    return 0;
                }
            } else {
                $this->error($result['message']);
                return 1;
            }
        }

        if ($result['success']) {
            $this->info($result['message']);
            $this->newLine();
            $this->info('Please securely share this API key with the merchant:');
            $this->newLine();
            $this->line($result['api_key']);
            $this->newLine();
            $this->warn('⚠️  This key will only be shown once and cannot be retrieved later.');
        }

        return 0;
    }}

