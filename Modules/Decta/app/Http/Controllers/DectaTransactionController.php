<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Services\DectaExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DectaTransactionController extends Controller
{
    protected $transactionRepository;
    protected $exportService;

    public function __construct(
        DectaTransactionRepository $transactionRepository,
        DectaExportService $exportService
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->exportService = $exportService;
    }

    /**
     * Display transaction details listing page
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // Build the query
        $query = DectaTransaction::with('dectaFile')
            ->select([
                'id',
                'payment_id',
                'merchant_name',
                'merchant_id',
                'card_type_name',
                'tr_amount',
                'tr_ccy',
                'tr_type',
                'tr_date_time',
                'status',
                'is_matched',
                'decta_file_id',
                'acq_ref_nr',
                'gateway_trx_id',
                'gateway_account_id',
                'gateway_transaction_id'
            ]);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_id', 'ILIKE', "%{$search}%")
                    ->orWhere('merchant_name', 'ILIKE', "%{$search}%")
                    ->orWhere('merchant_id', 'ILIKE', "%{$search}%")
                    ->orWhere('card_type_name', 'ILIKE', "%{$search}%")
                    ->orWhere('acq_ref_nr', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_trx_id', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_transaction_id', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_account_id', 'ILIKE', "%{$search}%");
            });
        }

        // Apply date filters
        if ($fromDate) {
            $query->whereDate('tr_date_time', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('tr_date_time', '<=', $toDate);
        }

        // Get paginated results
        $transactions = $query->orderBy('tr_date_time', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        // Get summary statistics (applying same filters)
        $statsQuery = DectaTransaction::query();

        if ($search) {
            $statsQuery->where(function ($q) use ($search) {
                $q->where('payment_id', 'ILIKE', "%{$search}%")
                    ->orWhere('merchant_name', 'ILIKE', "%{$search}%")
                    ->orWhere('merchant_id', 'ILIKE', "%{$search}%")
                    ->orWhere('card_type_name', 'ILIKE', "%{$search}%")
                    ->orWhere('acq_ref_nr', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_trx_id', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_transaction_id', 'ILIKE', "%{$search}%")
                    ->orWhere('gateway_account_id', 'ILIKE', "%{$search}%");
            });
        }

        if ($fromDate) {
            $statsQuery->whereDate('tr_date_time', '>=', $fromDate);
        }

        if ($toDate) {
            $statsQuery->whereDate('tr_date_time', '<=', $toDate);
        }

        $totalCount = $statsQuery->count();
        $matchedCount = (clone $statsQuery)->where('is_matched', true)->count();
        $unmatchedCount = (clone $statsQuery)->where('is_matched', false)->count();

        $stats = [
            'total' => $totalCount,
            'matched' => $matchedCount,
            'unmatched' => $unmatchedCount,
            'match_rate' => $totalCount > 0 ? round(($matchedCount / $totalCount) * 100, 1) : 0
        ];

        return view('decta::transactions.index', compact(
            'transactions',
            'stats',
            'search',
            'perPage',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * AJAX search for transactions
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $perPage = min($request->get('per_page', 10), 100);
            $page = $request->get('page', 1);

            $query = DectaTransaction::with('dectaFile')
                ->select([
                    'id',
                    'payment_id',
                    'merchant_name',
                    'merchant_id',
                    'card_type_name',
                    'tr_amount',
                    'tr_ccy',
                    'tr_type',
                    'tr_date_time',
                    'status',
                    'is_matched',
                    'decta_file_id',
                    'acq_ref_nr',
                    'gateway_trx_id',
                    'gateway_account_id',
                    'gateway_transaction_id'
                ]);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_id', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_name', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_id', 'ILIKE', "%{$search}%")
                        ->orWhere('card_type_name', 'ILIKE', "%{$search}%")
                        ->orWhere('acq_ref_nr', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_trx_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_transaction_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_account_id', 'ILIKE', "%{$search}%");
                });
            }

            // Apply date filters
            if ($fromDate) {
                try {
                    $parsedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate);
                    $query->whereDate('tr_date_time', '>=', $parsedFromDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            if ($toDate) {
                try {
                    $parsedToDate = Carbon::createFromFormat('Y-m-d', $toDate);
                    $query->whereDate('tr_date_time', '<=', $parsedToDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            $transactions = $query->orderBy('tr_date_time', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            // Format the data for JSON response
            $formattedTransactions = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'payment_id' => $transaction->payment_id,
                    'merchant_name' => $transaction->merchant_name,
                    'merchant_id' => $transaction->merchant_id,
                    'card_type' => $transaction->card_type_name,
                    'tr_amount' => $transaction->tr_amount ? $transaction->tr_amount / 100 : 0,
                    'tr_ccy' => $transaction->tr_ccy,
                    'tr_type' => $transaction->tr_type,
                    'tr_date_time' => $transaction->tr_date_time ? $transaction->tr_date_time->format('Y-m-d H:i:s') : null,
                    'status' => $transaction->status,
                    'is_matched' => $transaction->is_matched,
                    'acq_ref_nr' => $transaction->acq_ref_nr,
                    'gateway_trx_id' => $transaction->gateway_trx_id,
                    'gateway_account_id' => $transaction->gateway_account_id,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'status_badge' => $this->getStatusBadge($transaction->status, $transaction->is_matched)
                ];
            });

            // Get updated statistics with filters applied
            $statsQuery = DectaTransaction::query();

            if ($search) {
                $statsQuery->where(function ($q) use ($search) {
                    $q->where('payment_id', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_name', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_id', 'ILIKE', "%{$search}%")
                        ->orWhere('card_type_name', 'ILIKE', "%{$search}%")
                        ->orWhere('acq_ref_nr', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_trx_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_transaction_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_account_id', 'ILIKE', "%{$search}%");
                });
            }

            if ($fromDate) {
                try {
                    $parsedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate);
                    $statsQuery->whereDate('tr_date_time', '>=', $parsedFromDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            if ($toDate) {
                try {
                    $parsedToDate = Carbon::createFromFormat('Y-m-d', $toDate);
                    $statsQuery->whereDate('tr_date_time', '<=', $parsedToDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            $totalCount = $statsQuery->count();
            $matchedCount = (clone $statsQuery)->where('is_matched', true)->count();
            $unmatchedCount = (clone $statsQuery)->where('is_matched', false)->count();

            $stats = [
                'total' => $totalCount,
                'matched' => $matchedCount,
                'unmatched' => $unmatchedCount,
                'match_rate' => $totalCount > 0 ? round(($matchedCount / $totalCount) * 100, 1) : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedTransactions,
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific transaction details
     */
    public function show($id)
    {
        $transaction = DectaTransaction::with('dectaFile')->findOrFail($id);

        return view('decta::transactions.show', compact('transaction'));
    }

    /**
     * Export transactions to CSV/Excel
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'csv');
            $search = $request->get('search');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            // Build query for export (without pagination)
            $query = DectaTransaction::with('dectaFile')
                ->select([
                    'payment_id',
                    'merchant_name',
                    'merchant_id',
                    'card_type_name',
                    'tr_amount',
                    'tr_ccy',
                    'tr_type',
                    'tr_date_time',
                    'status',
                    'is_matched',
                    'tr_approval_id',
                    'tr_ret_ref_nr',
                    'acq_ref_nr',
                    'gateway_trx_id',
                    'gateway_account_id',
                    'gateway_transaction_id',
                    'error_message'
                ]);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_id', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_name', 'ILIKE', "%{$search}%")
                        ->orWhere('merchant_id', 'ILIKE', "%{$search}%")
                        ->orWhere('card_type_name', 'ILIKE', "%{$search}%")
                        ->orWhere('acq_ref_nr', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_trx_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_transaction_id', 'ILIKE', "%{$search}%")
                        ->orWhere('gateway_account_id', 'ILIKE', "%{$search}%");
                });
            }

            // Apply date filters
            if ($fromDate) {
                try {
                    $parsedFromDate = Carbon::createFromFormat('Y-m-d', $fromDate);
                    $query->whereDate('tr_date_time', '>=', $parsedFromDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            if ($toDate) {
                try {
                    $parsedToDate = Carbon::createFromFormat('Y-m-d', $toDate);
                    $query->whereDate('tr_date_time', '<=', $parsedToDate);
                } catch (\Exception $e) {
                    // Invalid date format, skip filter
                }
            }

            $transactions = $query->orderBy('tr_date_time', 'desc')
                ->limit(10000) // Limit export to 10k records for performance
                ->get();

            // Format data for export
            $exportData = $transactions->map(function ($transaction) {
                return [
                    'payment_id' => $transaction->payment_id,
                    'merchant_name' => $transaction->merchant_name,
                    'merchant_id' => $transaction->merchant_id,
                    'card_type' => $transaction->card_type_name,
                    'amount' => $transaction->tr_amount ? $transaction->tr_amount / 100 : 0,
                    'currency' => $transaction->tr_ccy,
                    'transaction_type' => $transaction->tr_type,
                    'transaction_date' => $transaction->tr_date_time ? $transaction->tr_date_time->format('Y-m-d H:i:s') : '',
                    'status' => $transaction->status,
                    'is_matched' => $transaction->is_matched ? 'Yes' : 'No',
                    'approval_id' => $transaction->tr_approval_id,
                    'return_reference' => $transaction->tr_ret_ref_nr,
                    'acq_ref_nr' => $transaction->acq_ref_nr,
                    'gateway_trx_id' => $transaction->gateway_trx_id,
                    'gateway_account_id' => $transaction->gateway_account_id,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'error_message' => $transaction->error_message
                ];
            })->toArray();

            $filters = [
                'search' => $search,
                'from_date' => $fromDate,
                'to_date' => $toDate
            ];

            if ($format === 'excel') {
                $filePath = $this->exportService->exportToExcel($exportData, 'transactions', $filters);
            } else {
                $filePath = $this->exportService->exportToCsv($exportData, 'transactions', $filters);
            }

            return response()->download(storage_path('app/' . $filePath))->deleteFileAfterSend();

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status badge HTML for a transaction
     */
    private function getStatusBadge($status, $isMatched)
    {
        if ($isMatched) {
            return '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Matched</span>';
        }

        switch ($status) {
            case 'pending':
                return '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>';
            case 'failed':
                return '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>';
            case 'processing':
                return '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Processing</span>';
            default:
                return '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">' . ucfirst($status) . '</span>';
        }
    }
}
