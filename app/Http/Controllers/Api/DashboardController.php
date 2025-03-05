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

class DashboardController extends Controller
{
    protected $reserveRepository;

    public function __construct(RollingReserveRepositoryInterface $reserveRepository)
    {
        // Ensure web auth is applied
        $this->middleware('auth');
        $this->reserveRepository = $reserveRepository;
    }

    /**
     * Get list of merchants for the dashboard
     */
    public function getMerchants(Request $request)
    {
        try {
            // Use Auth facade directly for standard web authentication
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

            // Get merchant ID based on user role
            // For now, we'll use a dummy implementation that returns reserve summary for all merchants
            $merchantId = 1; // Replace with actual merchant ID from request or user

            $summary = $this->reserveRepository->getReserveSummary($merchantId, $currency);

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

            $merchantId = 1; // Replace with actual merchant ID from request or user
            $status = $request->input('status');
            $currency = $request->input('currency');

            $query = RollingReserveEntry::query()
                ->where('merchant_id', $merchantId);

            if ($status) {
                $query->where('status', $status);
            }

            if ($currency) {
                $query->where('original_currency', $currency);
            }

            $reserves = $query->get();

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

            $fees = FeeHistory::query()
                ->join('fee_types', 'fee_history.fee_type_id', '=', 'fee_types.id')
                ->where('merchant_id', $merchantId)
                ->where('base_currency', $currency)
                ->where('applied_date', '>=', $startDate)
                ->select([
                    'fee_history.*',
                    'fee_types.name as fee_type'
                ])
                ->get();

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
