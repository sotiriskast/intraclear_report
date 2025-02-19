<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RollingReserveResource;
use App\Models\Merchant;
use App\Models\RollingReserveEntry;
use App\Repositories\MerchantRepository;
use App\Services\DynamicLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiRollingReserveController extends Controller
{
    public function __construct(
        private readonly DynamicLogger      $logger,
        private readonly MerchantRepository $merchantRepository

    )
    {
    }

    /**
     * Get all rolling reserves for the authenticated merchant
     */
    public function index(Request $request)
    {
        try {
            // Get merchant from the token
            $merchant = Merchant::where('account_id', $request->user()->account_id)->first();
            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 401);
            }

            $this->logger->log('info', 'API request received', [
                'merchant_id' => $merchant->id,
                'account_id' => $merchant->account_id
            ]);

            $reserves = RollingReserveEntry::query()
                ->where('merchant_id', $merchant->id)
                ->select([
                    'original_amount',
                    'original_currency',
                    'reserve_amount_eur',
                    'status',
                    'period_start',
                    'period_end',
                    'release_due_date'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            $this->logger->log('info', 'Reserves found', [
                'count' => $reserves->count()
            ]);

            $formattedReserves = $reserves->map(function ($reserve) {
                return [
                    'amount' => $reserve->original_amount / 100,
                    'currency' => $reserve->original_currency,
                    'amount_eur' => $reserve->reserve_amount_eur / 100,
                    'status' => $reserve->status,
                    'period_start' => $reserve->period_start?->format('Y-m-d'),
                    'period_end' => $reserve->period_end?->format('Y-m-d'),
                    'release_due_date' => $reserve->release_due_date?->format('Y-m-d')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedReserves
            ]);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving rolling reserves', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rolling reserves',
                'debug_message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get summary of rolling reserves
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $merchantId = $this->merchantRepository->getMerchantIdByAccountId($request->user()->account_id);

            $summary = RollingReserveEntry::query()
                ->where('merchant_id', $merchantId)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN status = "pending" THEN reserve_amount_eur ELSE 0 END), 0) as total_pending_eur,
                    COALESCE(SUM(CASE WHEN status = "released" THEN reserve_amount_eur ELSE 0 END), 0) as total_released_eur,
                    COUNT(CASE WHEN status = "pending" THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = "released" THEN 1 END) as released_count
                ')
                ->first();

            return response()->json([
                'data' => [
                    'total_pending_eur' => ($summary->total_pending_eur ?? 0) / 100,
                    'total_released_eur' => ($summary->total_released_eur ?? 0) / 100,
                    'pending_count' => $summary->pending_count ?? 0,
                    'released_count' => $summary->released_count ?? 0,
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to retrieve rolling reserves summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to retrieve rolling reserves summary');
        }
    }
}
