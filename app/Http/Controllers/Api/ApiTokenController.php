<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{

    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    /**
     * Create a new API token for the merchant
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'account_id' => 'required',
            'api_key' => 'required',
        ]);

        $merchant = $this->apiKeyService->findActiveMerchant($request->account_id);

        if (!$merchant || !$this->apiKeyService->validateApiKey($merchant, $request->api_key)) {
            throw ValidationException::withMessages([
                'api_key' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $merchant->createToken('merchant-api')->plainTextToken,
            'merchant' => [
                'account_id' => $merchant->account_id,
                'name' => $merchant->name,
            ]
        ]);
    }

    /**
     * Revoke the current API token
     */
    public function revokeToken(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked successfully']);
    }
}
