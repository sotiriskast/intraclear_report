<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Services\DectaExportService;
use Modules\Decta\Services\DectaReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DectaTransactionController extends Controller
{
    protected $transactionRepository;
    protected $exportService;
    protected $reportService;

    public function __construct(
        DectaTransactionRepository $transactionRepository,
        DectaExportService $exportService,
        DectaReportService $reportService
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->exportService = $exportService;
        $this->reportService = $reportService;
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
            $format ='csv';// $request->get('format', 'csv');
            $search = null;//$request->get('search');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            // Build query for export (without pagination)
            $query = DectaTransaction::select([
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
                    'error_message',
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
     * NEW: Export scheme report for selected date range
     */
    public function exportSchemeReport(Request $request)
    {
        try {
            $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'format' => 'in:csv,excel',
                'merchant_id' => 'nullable|integer',
                'currency' => 'nullable|string|size:3',
            ]);

            $fromDate = $request->input('date_from');
            $toDate = $request->input('date_to');
            $format = $request->input('format', 'csv');
            $merchantId = $request->input('merchant_id');
            $currency = $request->input('currency');

            // Check date range to prevent huge exports
            $daysDiff = Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate));
            if ($daysDiff > 365) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 365 days for scheme reports.'
                ], 422);
            }

            $filters = [
                'date_from' => $fromDate,
                'date_to' => $toDate,
                'merchant_id' => $merchantId,
                'currency' => $currency,
            ];

            Log::info('Starting scheme report export', [
                'filters' => $filters,
                'format' => $format
            ]);

            // Use the report service to get scheme data
            $schemeData = $this->reportService->generateReport('scheme', $filters);

            Log::info('Scheme data retrieved', [
                'record_count' => count($schemeData),
                'sample_data' => array_slice($schemeData, 0, 2) // Log first 2 records for debugging
            ]);

            if (empty($schemeData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected criteria.'
                ], 404);
            }

            // Generate filename
            $dateRange = Carbon::parse($fromDate)->format('Y-m-d') . '_to_' . Carbon::parse($toDate)->format('Y-m-d');
            $filename = "decta_scheme_report_{$dateRange}_" . Carbon::now()->format('Y-m-d_H-i-s') . ".{$format}";

            try {
                if ($format === 'excel') {
                    $filePath = $this->exportService->exportToExcel($schemeData, 'scheme', $filters);
                } else {
                    $filePath = $this->exportService->exportToCsv($schemeData, 'scheme', $filters);
                }

                Log::info('Export file created', [
                    'file_path' => $filePath,
                    'full_path' => storage_path('app/' . $filePath),
                    'file_exists' => file_exists(storage_path('app/' . $filePath))
                ]);

                // Check if file actually exists before trying to download
                $fullPath = storage_path('app/' . $filePath);
                if (!file_exists($fullPath)) {
                    Log::error('Export file not found after creation', [
                        'expected_path' => $fullPath,
                        'storage_path' => storage_path('app/'),
                        'exports_dir_exists' => is_dir(storage_path('app/exports/')),
                        'exports_dir_writable' => is_writable(storage_path('app/exports/'))
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create export file. Check server logs for details.'
                    ], 500);
                }

                return response()->download($fullPath, $filename)->deleteFileAfterSend();

            } catch (\Exception $exportException) {
                Log::error('Export service failed', [
                    'error' => $exportException->getMessage(),
                    'trace' => $exportException->getTraceAsString(),
                    'data_count' => count($schemeData)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Export creation failed: ' . $exportException->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Scheme report export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Scheme report export failed: ' . $e->getMessage()
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
    /**
     * Export large complete transaction dataset
     */
    public function exportLargeDataset(Request $request)
    {
        try {
            $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'format' => 'in:csv,excel',
                'merchant_id' => 'nullable|string',
                'currency' => 'nullable|string|size:3',
                'status' => 'nullable|in:matched,unmatched,pending,failed',
            ]);

            $filters = $request->only(['date_from', 'date_to', 'merchant_id', 'currency', 'status']);
            $format = $request->input('format', 'csv');

            Log::info('Starting large dataset export request', [
                'filters' => $filters,
                'format' => $format,
                'user_id' => auth()->id()
            ]);

            // Check estimated size
            $largeExportService = app(\Modules\Decta\Services\DectaLargeExportService::class);
            $estimatedCount = $this->getEstimatedRecordCount($filters);

            // Warn if dataset is very large
            if ($estimatedCount > 1000000) {
                Log::warning('Very large export requested', [
                    'estimated_records' => $estimatedCount,
                    'filters' => $filters
                ]);
            }

            // Set longer execution time for large exports
            set_time_limit(3600); // 1 hour
            ini_set('memory_limit', '1G'); // 1GB memory

            $result = $largeExportService->exportLargeTransactionDataset($filters, $format);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export failed: ' . $result['error']
                ], 500);
            }

            $fullPath = storage_path('app/' . $result['file_path']);

            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export file was not created successfully'
                ], 500);
            }

            Log::info('Large dataset export completed successfully', [
                'file_size' => $result['file_size'],
                'execution_time' => $result['execution_time'],
                'record_count' => $result['record_count']
            ]);

            // Generate download filename
            $downloadFilename = $result['filename'];

            return response()->download($fullPath, $downloadFilename)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('Large dataset export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Large export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get estimated record count for export preview
     */
    public function getExportEstimate(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'merchant_id', 'currency', 'status']);
            $estimatedCount = $this->getEstimatedRecordCount($filters);

            // Estimate file size (rough calculation)
            $estimatedSizeBytes = $estimatedCount * 1000; // ~1KB per record
            $estimatedSizeMB = round($estimatedSizeBytes / 1024 / 1024, 2);

            // Estimate processing time (rough calculation)
            $estimatedTimeMinutes = round($estimatedCount / 10000, 1); // ~10k records per minute

            return response()->json([
                'success' => true,
                'estimated_records' => $estimatedCount,
                'estimated_size_mb' => $estimatedSizeMB,
                'estimated_time_minutes' => $estimatedTimeMinutes,
                'is_large_dataset' => $estimatedCount > 100000,
                'recommendations' => $this->getExportRecommendations($estimatedCount)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to estimate export size: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to estimate record count
     */
    private function getEstimatedRecordCount(array $filters): int
    {
        $query = DectaTransaction::query();

        if (!empty($filters['date_from'])) {
            $query->whereDate('tr_date_time', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('tr_date_time', '<=', $filters['date_to']);
        }

        if (!empty($filters['merchant_id'])) {
            $query->where('merchant_id', $filters['merchant_id']);
        }

        if (!empty($filters['currency'])) {
            $query->where('tr_ccy', $filters['currency']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'matched') {
                $query->where('is_matched', true);
            } elseif ($filters['status'] === 'unmatched') {
                $query->where('is_matched', false);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        return $query->count();
    }

    /**
     * Get export recommendations based on dataset size
     */
    private function getExportRecommendations(int $recordCount): array
    {
        $recommendations = [];

        if ($recordCount > 1000000) {
            $recommendations[] = 'Very large dataset (1M+ records). Consider using CSV format for better performance.';
            $recommendations[] = 'Export may take 10-30 minutes to complete.';
            $recommendations[] = 'Consider filtering by smaller date ranges if possible.';
        } elseif ($recordCount > 500000) {
            $recommendations[] = 'Large dataset (500K+ records). Estimated processing time: 5-15 minutes.';
            $recommendations[] = 'CSV format recommended for faster processing.';
        } elseif ($recordCount > 100000) {
            $recommendations[] = 'Medium dataset (100K+ records). Estimated processing time: 1-5 minutes.';
        } else {
            $recommendations[] = 'Small dataset. Should export quickly in either format.';
        }

        return $recommendations;
    }
}
