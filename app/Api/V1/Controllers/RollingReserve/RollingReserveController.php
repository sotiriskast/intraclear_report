<?php

namespace App\Api\V1\Controllers\RollingReserve;

use App\Api\V1\Controllers\RollingReserve\Requests\ListRollingReserveRequest;
use App\Api\V1\Controllers\RollingReserve\Requests\ShowRollingReserveRequest;
use App\Api\V1\Resources\RollingReserve\RollingReserveResource;
use App\Api\V1\Resources\RollingReserve\RollingReserveSummaryResource;
use App\Api\V1\Services\RollingReserve\RollingReserveApiService;
use App\Http\Controllers\Controller;
use App\Services\DynamicLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class RollingReserveController extends Controller
{
    public function __construct(
        private readonly RollingReserveApiService $apiService,
        private readonly DynamicLogger            $logger
    )
    {
    }

    public function index(ListRollingReserveRequest $request): JsonResponse
    {
        $result = $this->apiService->listReserves(
            merchantId: $request->user()->id,
            filters: $request->validated(),
            perPage: $request->get('per_page', 15)
        );
        $headers = [
            'X-RateLimit-Limit' => 60,
            'X-RateLimit-Remaining' => RateLimiter::remaining('api-standard', 10),
        ];

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
                'errors' => $result->errors
            ], 400)->withHeaders($headers);
        }

        return response()->json([
            'success' => true,
            'data' => RollingReserveResource::collection($result->data),
            'meta' => $result->meta
        ])->withHeaders($headers);
    }

    public function show(ShowRollingReserveRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->apiService->getReserve(
                merchantId: $request->user()->id,
                reserveId: $id
            );

            $headers = [
                'X-RateLimit-Limit' => 30,
                'X-RateLimit-Remaining' => RateLimiter::remaining('api-standard', 10),
            ];

            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'message' => $result->message ?? 'Reserve not found',
                    'error' => 'reserve_not_found'
                ], 404)->withHeaders($headers);
            }

            if (!$result->data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reserve not found',
                    'error' => 'reserve_not_found'
                ], 404)->withHeaders($headers);
            }

            return response()->json([
                'success' => true,
                'data' => new RollingReserveResource($result->data)
            ])->withHeaders($headers);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving rolling reserve', [
                'merchant_id' => $request->user()->id,
                'reserve_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reserve details',
                'error' => app()->environment('local') ? $e->getMessage() : 'internal_server_error'
            ], 500);
        }
    }

    public function summary(ListRollingReserveRequest $request): JsonResponse
    {
        try {
            $result = $this->apiService->getReserveSummary(
                merchantId: $request->user()->id,
                currency: $request->get('currency')
            );

            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'message' => $result->message
                ], 400);
            }

            if (!is_array($result->data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data structure received from service'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => new RollingReserveSummaryResource($result->data)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reserve summary',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
