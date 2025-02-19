<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\MerchantAuthService;
use App\Services\DynamicLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MerchantApiAuthController extends Controller
{
    public function __construct(
        private readonly MerchantAuthService $authService,
        private readonly DynamicLogger $logger
    ) {}

    /**
     * Login merchant and generate access token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|integer',
            'api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $merchant = $this->authService->authenticateMerchant(
            $request->account_id,
            $request->api_key
        );

        if (!$merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $tokenResult = $this->authService->generateToken($merchant, ['merchant:read']);

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful',
            'data' => [
                'merchant_id' => $merchant->account_id,
                'name' => $merchant->name,
                'token' => $tokenResult->plainTextToken,
            ]
        ]);
    }

    /**
     * Logout merchant by revoking current token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $merchant = $request->user();

            if ($merchant && $merchant->currentAccessToken()) {
                $merchant->currentAccessToken()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error during merchant logout', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }
}
