<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Api\ApiKeyService;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;

class MerchantApiKeyController extends Controller
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService,
        private readonly DynamicLogger $logger
    ) {}

    /**
     * Generate a new API key for a merchant
     */
    public function generate(Request $request, Merchant $merchant)
    {
        try {
            if (!$merchant->active) {
                return back()->with('error', 'Cannot generate API key for inactive merchant.');
            }

            $apiKey = $this->apiKeyService->generateApiKey($merchant);

            $this->logger->log('info', 'API key generated via admin interface', [
                'merchant_id' => $merchant->id,
                'admin_id' => $request->user()->id
            ]);

            // Store the plain API key in flash session to display once
            return back()->with([
                'success' => 'API key generated successfully.',
                'api_key' => $apiKey
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to generate API key via admin interface', [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to generate API key: ' . $e->getMessage());
        }
    }

    /**
     * Revoke the merchant's API key
     */
    public function revoke(Request $request, Merchant $merchant)
    {
        try {
            $this->apiKeyService->revokeApiKey($merchant);

            $this->logger->log('info', 'API key revoked via admin interface', [
                'merchant_id' => $merchant->id,
                'admin_id' => $request->user()->id
            ]);

            return back()->with('success', 'API key revoked successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to revoke API key via admin interface', [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to revoke API key: ' . $e->getMessage());
        }
    }
}
