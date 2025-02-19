<?php

namespace App\Services\Api;

use App\Models\Merchant;
use App\Services\DynamicLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKeyService
{
    private const API_KEY_LENGTH = 32;

    public function __construct(
        private readonly DynamicLogger $logger
    )
    {
    }

    /**
     * Find active merchant by account ID
     */
    public function findActiveMerchant(string|int $accountId): ?Merchant
    {
        return Merchant::where('account_id', $accountId)
            ->where('active', true)
            ->first();
    }

    /**
     * Handle the API key generation process
     */
    public function handleApiKeyGeneration(string|int $accountId, bool $force = false): array
    {
        $merchant = $this->findActiveMerchant($accountId);

        if (!$merchant) {
            return [
                'success' => false,
                'message' => "No active merchant found with account ID: {$accountId}"
            ];
        }

        if ($merchant->api_key && !$force) {
            return [
                'success' => false,
                'message' => 'Merchant already has an API key. Use --force to regenerate.',
                'requires_confirmation' => true
            ];
        }

        try {
            $apiKey = $this->generateApiKey($merchant);

            $this->logger->log('info', 'API key generated for merchant', [
                'merchant_id' => $merchant->id,
                'account_id' => $merchant->account_id
            ]);

            return [
                'success' => true,
                'message' => 'API Key generated successfully!',
                'api_key' => $apiKey
            ];
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to generate API key', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => "Failed to generate API key: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Generate a new API key for a merchant
     */
    public function generateApiKey(Merchant $merchant): string
    {
        // Generate a random API key
        $apiKey = Str::random(self::API_KEY_LENGTH);

        // Hash the API key before storing
        $merchant->update([
            'api_key' => Hash::make($apiKey)
        ]);

        return $apiKey;
    }

    /**
     * Validate an API key
     */
    public function validateApiKey(Merchant $merchant, string $apiKey): bool
    {
        return Hash::check($apiKey, $merchant->api_key);
    }

    /**
     * Revoke API key for a merchant
     */
    public function revokeApiKey(Merchant $merchant): void
    {
        $merchant->tokens()->delete();
        $merchant->update(['api_key' => null]);

        $this->logger->log('info', 'API key revoked for merchant', [
            'merchant_id' => $merchant->id,
            'account_id' => $merchant->account_id
        ]);
    }
}
