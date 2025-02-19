<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Auth\MerchantAuthService;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly MerchantAuthService $authService,
        private readonly DynamicLogger $logger
    ) {}

    public function show(Merchant $merchant)
    {

        return view('admin.merchants.api', [
            'merchant' => $merchant,
            'hasApiKey' => !empty($merchant->api_key),
            'tokenCount' => $merchant->tokens()->count(),
        ]);
    }

    public function generate(Merchant $merchant)
    {

        $apiKey = $this->authService->regenerateApiKey($merchant);

        $this->logger->log('info', 'Admin generated new API key for merchant', [
            'admin_id' => auth()->id(),
            'merchant_id' => $merchant->id,
            'account_id' => $merchant->account_id,
        ]);

        return redirect()
            ->route('merchant.api', $merchant)
            ->with('newApiKey', $apiKey)
            ->with('success', 'New API key has been generated. Make sure to copy it now.');
    }

    public function revokeAllTokens(Merchant $merchant)
    {

        $tokenCount = $merchant->tokens()->count();
        $this->authService->revokeAllTokens($merchant);

        return redirect()
            ->route('merchant.api', $merchant)
            ->with('success', "All API tokens ($tokenCount) have been revoked.");
    }
}
