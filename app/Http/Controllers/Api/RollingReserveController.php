<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RollingReserveResource;
use App\Models\RollingReserveEntry;
use App\Repositories\MerchantRepository;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class RollingReserveController extends Controller
{
    public function __construct(
        private readonly DynamicLogger $logger,
        private readonly MerchantRepository $merchantRepository

    ) {}

    /**
     * Get all rolling reserves for authenticated merchant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $merchant = $request->user();
            $perPage = $request->get('per_page', 15);

            $validator = Validator::make($request->all(), [
                'per_page' => 'sometimes|integer|min:5|max:100',
                'status' => 'sometimes|string|in:pending,released',
                'start_date' => 'sometimes|date|date_format:Y-m-d',
                'end_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:start_date',
                'currency' => 'sometimes|string|size:3',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = $merchant->rollingReserves()->latest();

            // Apply filters if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date')) {
                $query->where('period_start', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('period_end', '<=', $request->end_date);
            }

            if ($request->has('currency')) {
                $query->where('original_currency', $request->currency);
            }

            $reserves = $query->paginate($perPage);

            $this->logger->log('info', 'Merchant retrieved rolling reserves', [
                'merchant_id' => $merchant->id,
                'count' => $reserves->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => RollingReserveResource::collection($reserves),
                'meta' => [
                    'current_page' => $reserves->currentPage(),
                    'last_page' => $reserves->lastPage(),
                    'per_page' => $reserves->perPage(),
                    'total' => $reserves->total(),
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving merchant rolling reserves', [
                'merchant_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rolling reserves',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific rolling reserve details
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $merchant = $request->user();
            $reserve = $merchant->rollingReserves()->findOrFail($id);

            $this->logger->log('info', 'Merchant retrieved rolling reserve details', [
                'merchant_id' => $merchant->id,
                'reserve_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'data' => new RollingReserveResource($reserve)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rolling reserve not found or does not belong to this merchant'
            ], 404);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving rolling reserve details', [
                'merchant_id' => $request->user()->id,
                'reserve_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reserve details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics of merchant rolling reserves
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $merchant = $request->user();
            $currency = $request->get('currency');

            $validator = Validator::make($request->all(), [
                'currency' => 'sometimes|string|size:3',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = $merchant->rollingReserves();

            if ($currency) {
                $query->where('original_currency', $currency);
            }

            // Get summary statistics
            $pendingReserves = (clone $query)
                ->where('status', 'pending')
                ->selectRaw('original_currency, SUM(original_amount) as total_amount')
                ->groupBy('original_currency')
                ->get()
                ->keyBy('original_currency')
                ->map(function ($item) {
                    return round($item->total_amount / 100, 2);
                })
                ->toArray();

            $pendingCount = (clone $query)->where('status', 'pending')->count();
            $releasedCount = (clone $query)->where('status', 'released')->count();

            // Get upcoming releases (next 30 days)
            $today = Carbon::today();
            $nextMonth = Carbon::today()->addDays(30);

            $upcomingReleases = (clone $query)
                ->where('status', 'pending')
                ->whereBetween('release_due_date', [$today, $nextMonth])
                ->selectRaw('original_currency, SUM(original_amount) as total_amount')
                ->groupBy('original_currency')
                ->get()
                ->keyBy('original_currency')
                ->map(function ($item) {
                    return round($item->total_amount / 100, 2);
                })
                ->toArray();

            $this->logger->log('info', 'Merchant retrieved rolling reserves summary', [
                'merchant_id' => $merchant->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_reserves' => $pendingReserves,
                    'pending_count' => $pendingCount,
                    'released_count' => $releasedCount,
                    'upcoming_releases' => $upcomingReleases
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error retrieving rolling reserves summary', [
                'merchant_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reserves summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
