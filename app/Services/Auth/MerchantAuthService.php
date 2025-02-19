<?php

namespace App\Services\Auth;

use App\Models\Merchant;
use App\Repositories\MerchantRepository;
use App\Services\DynamicLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

readonly class MerchantAuthService
{
    public function __construct(
        private DynamicLogger $logger,
        private MerchantRepository $merchantRepository
    ) {}

    public function authenticateMerchant(int $accountId, string $apiKey): ?Merchant
    {
        $merchant = $this->merchantRepository->findByAccountId($accountId);

        if (!$merchant || !Hash::check($apiKey, $merchant->api_key) || !$merchant->active) {
            $this->logger->log('warning', 'Failed merchant authentication attempt', [
                'account_id' => $accountId,
            ]);

            return null;
        }

        $this->logger->log('info', 'Merchant authenticated successfully', [
            'merchant_id' => $merchant->id,
            'account_id' => $merchant->account_id,
        ]);

        return $merchant;
    }

    public function generateToken(Merchant $merchant, array $abilities = ['merchant:read']): NewAccessToken
    {
        $tokenName = 'merchant-api-' . $merchant->account_id;
        $merchant->tokens()->where('name', $tokenName)->delete();

        $token = $merchant->createToken($tokenName, $abilities, now()->addDays(7));

        $this->logger->log('info', 'Generated new API token for merchant', [
            'merchant_id' => $merchant->id,
            'account_id' => $merchant->account_id,
            'token_id' => $token->accessToken->id,
        ]);

        return $token;
    }

    public function regenerateApiKey(Merchant $merchant): string
    {
        $newApiKey = Str::random(32);

        $merchant->update([
            'api_key' => Hash::make($newApiKey),
        ]);

        $merchant->tokens()->delete();

        $this->logger->log('info', 'Merchant API key regenerated', [
            'merchant_id' => $merchant->id,
            'account_id' => $merchant->account_id,
        ]);

        return $newApiKey;
    }

    public function revokeAllTokens(Merchant $merchant): bool
    {
        $count = $merchant->tokens()->count();
        $merchant->tokens()->delete();

        $this->logger->log('info', 'Revoked all merchant tokens', [
            'merchant_id' => $merchant->id,
            'token_count' => $count,
        ]);

        return true;
    }
}
