<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\FeeHistory;
use App\Models\FeeType;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\FeeRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * @var RollingReserveRepositoryInterface
     */
    protected $reserveRepository;

    /**
     * @var FeeRepositoryInterface
     */
    protected $feeRepository;

    /**
     * @var MerchantRepository
     */
    protected $merchantRepository;

    /**
     * DashboardController constructor.
     *
     * @param RollingReserveRepositoryInterface $reserveRepository
     * @param FeeRepositoryInterface|null $feeRepository
     * @param MerchantRepository|null $merchantRepository
     */
    public function __construct(
        RollingReserveRepositoryInterface $reserveRepository,
        ?FeeRepositoryInterface           $feeRepository = null,
        ?MerchantRepository               $merchantRepository = null
    )
    {
        $this->reserveRepository = $reserveRepository;
        $this->feeRepository = $feeRepository;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * Get list of merchants for the dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMerchants(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return $this->unauthorizedResponse();
            }

            // Get active merchants with only required fields
            $merchants = Merchant::query()
                ->where('active', true)
                ->select(['id', 'name', 'account_id'])
                ->orderBy('name')
                ->get();

            return $this->successResponse($merchants);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load merchants', $e);
        }
    }

    /**
     * Get rolling reserve summary for dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReserveSummary(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return $this->unauthorizedResponse();
            }

            $currency = $request->input('currency', 'EUR');
            $merchantId = $request->input('merchant_id');

            // If merchant ID is provided, use repository method, otherwise get aggregated summary
            $summary = $merchantId
                ? $this->reserveRepository->getReserveSummary($merchantId, $currency)
                : $this->getAggregatedReserveSummary($currency);

            return $this->successResponse($summary);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load reserve summary', $e);
        }
    }

    /**
     * Get aggregated rolling reserve summary for all merchants
     *
     * @param string $currency
     * @return array
     */
    private function getAggregatedReserveSummary(string $currency): array
    {
        $baseQuery = DB::table('rolling_reserve_entries')
            // Only filter by currency if it's not 'all'
            ->when($currency !== 'all', function ($query) use ($currency) {
                return $query->where('original_currency', $currency);
            });

        // Get pending reserves in original currency
        $pendingReserves = (clone $baseQuery)
            ->where('status', 'pending')
            ->select('original_currency', DB::raw('SUM(original_amount) as total_amount'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(fn($item) => round($item->total_amount / 100, 2))
            ->toArray();

        // Get total reserved amount in EUR
        $totalReservedEur = (clone $baseQuery)
                ->where('status', 'pending')
                ->sum('reserve_amount_eur') / 100;

        // Get pending reserves in EUR
        $pendingReservesEur = (clone $baseQuery)
            ->where('status', 'pending')
            ->select('original_currency', DB::raw('SUM(reserve_amount_eur) as total_eur'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(fn($item) => round($item->total_eur / 100, 2))
            ->toArray();

        // Get upcoming releases in next 30 days
        $releaseableDate = now()->addDays(30);
        $upcomingReleases = (clone $baseQuery)
            ->where('status', 'pending')
            ->where('release_due_date', '<=', $releaseableDate)
            ->select('original_currency', DB::raw('SUM(original_amount) as total_amount'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(fn($item) => round($item->total_amount / 100, 2))
            ->toArray();

        // Get counts by status with a single query for ALL time periods
        $counts = DB::table('rolling_reserve_entries')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Make sure the counts are numeric
        $pendingCount = intval($counts['pending'] ?? 0);
        $releasedCount = intval($counts['released'] ?? 0);

        return [
            'pending_reserves' => $pendingReserves,
            'pending_reserves_eur' => $pendingReservesEur,
            'total_reserved_eur' => round($totalReservedEur, 2),
            'pending_count' => $pendingCount,
            'released_count' => $releasedCount,
            'upcoming_releases' => $upcomingReleases,
            'statistics' => [
                'pending_count' => $pendingCount,
                'released_count' => $releasedCount,
            ],
        ];
    }

    /**
     * Get rolling reserve entries for dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRollingReserves(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return $this->unauthorizedResponse();
            }

            $merchantId = $request->input('merchant_id');
            $status = $request->input('status');
            $currency = $request->input('currency');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50);

            // Build efficient query with proper filtering
            $query = RollingReserveEntry::query()
                ->when($merchantId, fn($q) => $q->where('merchant_id', $merchantId))
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($currency, fn($q) => $q->where('original_currency', $currency));

            // Calculate total reserved amount in EUR
            $totalReservedEur = (clone $query)
                    ->where('status', 'pending')
                    ->sum('reserve_amount_eur') / 100;

            // Get counts for both statuses regardless of the current filter
            $countQuery = RollingReserveEntry::query()
                ->when($merchantId, fn($q) => $q->where('merchant_id', $merchantId))
                ->when($currency, fn($q) => $q->where('original_currency', $currency))
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status');

            $counts = $countQuery->pluck('count', 'status')->toArray();

            // Make sure the counts are numeric
            $pendingCount = intval($counts['pending'] ?? 0);
            $releasedCount = intval($counts['released'] ?? 0);

            // Apply ordering and get the requested entries
            $orderQuery = clone $query;
            $orderQuery->orderBy('created_at', 'desc');

            // Use pagination for better performance on large datasets
            $reserves = $request->input('paginate', true)
                ? $orderQuery->paginate($perPage)
                : $orderQuery->get();

            // Process and format the reserve entries for frontend
            $formattedReserves = $reserves->map(function ($reserve) {
                return [
                    'id' => $reserve->id,
                    'merchant_id' => $reserve->merchant_id,
                    'status' => $reserve->status,
                    // Ensure proper division by 100 for monetary amounts
                    'amount' => round($reserve->original_amount / 100, 2),
                    'currency' => $reserve->original_currency,
                    'amount_eur' => round($reserve->reserve_amount_eur / 100, 2),
                    'exchange_rate' => $reserve->exchange_rate,
                    'release' => [
                        'due_date' => Carbon::parse($reserve->release_due_date)->format('Y-m-d'),
                        'released_at' => $reserve->released_at ? Carbon::parse($reserve->released_at)->format('Y-m-d') : null,
                    ],
                    'period' => [
                        'start' => Carbon::parse($reserve->period_start)->format('Y-m-d'),
                        'end' => Carbon::parse($reserve->period_end)->format('Y-m-d'),
                    ]
                ];
            });

            return $this->successResponse([
                'reserves' => $formattedReserves,
                'total_reserved_eur' => round($totalReservedEur, 2),
                'statistics' => [
                    'pending_count' => $pendingCount,
                    'released_count' => $releasedCount,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load rolling reserves', $e);
        }
    }
    /**
     * Get fee history for dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeeHistory(Request $request): JsonResponse
    {
        try {
            if (!Auth::check()) {
                return $this->unauthorizedResponse();
            }

            $merchantId = $request->input('merchant_id');
            // Default to all-time data if no date range is specified
            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))
                : Carbon::now()->subYears(10); // Go back 10 years to effectively get all data
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))
                : Carbon::now();
            $feeTypeId = $request->input('fee_type_id');

            // Cache fee types to reduce queries
            $feeTypes = FeeType::pluck('name', 'id')->toArray();

            // Build an optimized query with eager loading or joins
            $query = FeeHistory::query()
                ->with(['feeType']) // Eager load to avoid N+1 problem
                ->whereBetween('applied_date', [$startDate, $endDate])
                ->when($merchantId, fn($q) => $q->where('merchant_id', $merchantId))
                ->when($feeTypeId, fn($q) => $q->where('fee_type_id', $feeTypeId))
                ->orderBy('applied_date', 'desc');

            $fees = $query->get()->map(function ($fee) use ($feeTypes) {
                return [
                    'id' => $fee->id,
                    'merchant_id' => $fee->merchant_id,
                    'fee_type_id' => $fee->fee_type_id,
                    'fee_type' => $fee->feeType->name ?? $feeTypes[$fee->fee_type_id] ?? 'Unknown',
                    // Ensure proper division by 100 for monetary amounts with appropriate rounding
                    'fee_amount_eur' => round($fee->fee_amount_eur / 100, 2),
                    'applied_date' => Carbon::parse($fee->applied_date)->format('Y-m-d'),
                    'report_reference' => $fee->report_reference,
                ];
            });

            return $this->successResponse($fees);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load fee history', $e);
        }
    }

    /**
     * Return a successful response with data
     *
     * @param mixed $data
     * @return JsonResponse
     */
    private function successResponse($data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Return an error response with message
     *
     * @param string $message
     * @param \Exception $exception
     * @param int $statusCode
     * @return JsonResponse
     */
    private function errorResponse(string $message, \Exception $exception, int $statusCode = 500): JsonResponse
    {
        // Log the error for debugging
        logger()->error($message, [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $exception->getMessage()
        ], $statusCode);
    }

    /**
     * Return an unauthorized response
     *
     * @return JsonResponse
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }
}
