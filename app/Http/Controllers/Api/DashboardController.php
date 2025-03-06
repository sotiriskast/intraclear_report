<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\FeeHistory;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $reserveRepository;

    public function __construct(RollingReserveRepositoryInterface $reserveRepository)
    {
        // We'll apply auth middleware in routes
        $this->reserveRepository = $reserveRepository;
    }

    /**
     * Get list of merchants for the dashboard
     */
    public function getMerchants(Request $request)
    {
        try {
            // Check if user is authenticated
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // For admin users, return all merchants
            // For merchant users, return only their merchant
            $merchants = Merchant::query()
                ->where('active', true)
                ->select(['id', 'name', 'account_id'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $merchants
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load merchants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rolling reserve summary for dashboard
     */
    public function getReserveSummary(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $currency = $request->input('currency', 'EUR');
            $merchantId = $request->input('merchant_id');

            if ($merchantId) {
                // Get data for specific merchant
                $summary = $this->reserveRepository->getReserveSummary($merchantId, $currency);
            } else {
                // Get aggregated data for all merchants
                $summary = $this->getAggregatedReserveSummary($currency);
            }

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load reserve summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get aggregated rolling reserve summary for all merchants
     */
    private function getAggregatedReserveSummary($currency)
    {
        $pendingReserves = DB::table('rolling_reserve_entries')
            ->where('status', 'pending')
            ->where(function($query) use ($currency) {
                if ($currency !== 'all') {
                    $query->where('original_currency', $currency);
                }
            })
            ->select('original_currency', DB::raw('SUM(original_amount) as total_amount'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(function ($item) {
                return round($item->total_amount / 100, 2);
            })
            ->toArray();

        $pendingReservesEur = DB::table('rolling_reserve_entries')
            ->where('status', 'pending')
            ->where(function($query) use ($currency) {
                if ($currency !== 'all') {
                    $query->where('original_currency', $currency);
                }
            })
            ->select('original_currency', DB::raw('SUM(reserve_amount_eur) as total_eur'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(function ($item) {
                return round($item->total_eur / 100, 2);
            })
            ->toArray();

        // Get upcoming releases in next 30 days
        $releaseableDate = now()->addDays(30);
        $upcomingReleases = DB::table('rolling_reserve_entries')
            ->where('status', 'pending')
            ->where('release_due_date', '<=', $releaseableDate)
            ->where(function($query) use ($currency) {
                if ($currency !== 'all') {
                    $query->where('original_currency', $currency);
                }
            })
            ->select('original_currency', DB::raw('SUM(original_amount) as total_amount'))
            ->groupBy('original_currency')
            ->get()
            ->keyBy('original_currency')
            ->map(function ($item) {
                return round($item->total_amount / 100, 2);
            })
            ->toArray();

        // Get counts
        $counts = DB::table('rolling_reserve_entries')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending_reserves' => $pendingReserves,
            'pending_reserves_eur' => $pendingReservesEur,
            'pending_count' => $counts['pending'] ?? 0,
            'released_count' => $counts['released'] ?? 0,
            'upcoming_releases' => $upcomingReleases,
            'statistics' => [
                'pending_count' => $counts['pending'] ?? 0,
                'released_count' => $counts['released'] ?? 0,
            ],
        ];
    }

    /**
     * Get rolling reserve entries for dashboard
     */
    public function getRollingReserves(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $merchantId = $request->input('merchant_id');
            $status = $request->input('status');
            $currency = $request->input('currency');

            $query = RollingReserveEntry::query();

            if ($merchantId) {
                $query->where('merchant_id', $merchantId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($currency) {
                $query->where('original_currency', $currency);
            }

            $reserves = $query->get()->map(function($reserve) {
                // Format dates and amounts for frontend
                return [
                    'id' => $reserve->id,
                    'merchant_id' => $reserve->merchant_id,
                    'status' => $reserve->status,
                    'amount' => $reserve->original_amount / 100, // Convert from cents
                    'currency' => $reserve->original_currency,
                    'amount_eur' => $reserve->reserve_amount_eur / 100, // Convert from cents
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

            return response()->json([
                'success' => true,
                'data' => $reserves
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load rolling reserves',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fee history for dashboard
     */
    public function getFeeHistory(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $merchantId = $request->input('merchant_id');
            $currency = $request->input('currency', 'EUR');
            $startDate = Carbon::now()->subMonths(6);

            $query = FeeHistory::query()
                ->join('fee_types', 'fee_history.fee_type_id', '=', 'fee_types.id')
                ->where('base_currency', $currency)
                ->where('applied_date', '>=', $startDate)
                ->select([
                    'fee_history.*',
                    'fee_types.name as fee_type'
                ]);

            if ($merchantId) {
                $query->where('merchant_id', $merchantId);
            }

            $fees = $query->get();

            return response()->json([
                'success' => true,
                'data' => $fees
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load fee history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
