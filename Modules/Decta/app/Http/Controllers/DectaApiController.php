<?php

namespace Modules\Decta\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Decta\Services\DectaReportService;
use Modules\Decta\Services\DectaExportService;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Models\DectaTransaction;
use Modules\Decta\Models\DectaFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DectaApiController extends Controller
{
    protected $reportService;
    protected $exportService;
    protected $transactionRepository;

    public function __construct(
        DectaReportService $reportService,
        DectaExportService $exportService,
        DectaTransactionRepository $transactionRepository
    ) {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Get real-time statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            // Cache for 30 seconds to reduce database load
            $stats = Cache::remember('decta_stats', 30, function () {
                return $this->reportService->getSummaryStats();
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get transaction trends for charts
     */
    public function getTransactionTrends(Request $request): JsonResponse
    {
        try {
            $days = min($request->get('days', 7), 30); // Limit to 30 days max

            $trends = Cache::remember("decta_trends_{$days}", 300, function () use ($days) {
                return $this->reportService->getMatchingTrends($days);
            });

            return response()->json([
                'success' => true,
                'data' => $trends,
                'period_days' => $days
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load transaction trends',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search transactions with advanced filters
     */
    public function searchTransactions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'status' => 'nullable|in:pending,matched,failed',
                'amount_min' => 'nullable|numeric|min:0',
                'amount_max' => 'nullable|numeric|min:0',
                'merchant_id' => 'nullable|string',
                'currency' => 'nullable|string|size:3'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = $request->only([
                'date_from', 'date_to', 'status', 'amount_min', 'amount_max',
                'merchant_id', 'currency'
            ]);

            $query = $request->get('q');
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            // Build the search query
            $transactions = DectaTransaction::query()->with('dectaFile');

            // Text search
            if ($query) {
                $transactions->where(function ($q) use ($query) {
                    $q->where('payment_id', 'ILIKE', "%{$query}%")
                        ->orWhere('merchant_name', 'ILIKE', "%{$query}%")
                        ->orWhere('merchant_id', 'ILIKE', "%{$query}%")
                        ->orWhere('tr_approval_id', 'ILIKE', "%{$query}%")
                        ->orWhere('tr_ret_ref_nr', 'ILIKE', "%{$query}%");
                });
            }

            // Apply filters
            foreach ($filters as $key => $value) {
                if ($value !== null && $value !== '') {
                    switch ($key) {
                        case 'date_from':
                            $transactions->whereDate('tr_date_time', '>=', $value);
                            break;
                        case 'date_to':
                            $transactions->whereDate('tr_date_time', '<=', $value);
                            break;
                        case 'status':
                            if ($value === 'matched') {
                                $transactions->where('is_matched', true);
                            } elseif ($value === 'pending') {
                                $transactions->where('is_matched', false)
                                    ->where('status', '!=', 'failed');
                            } else {
                                $transactions->where('status', $value);
                            }
                            break;
                        case 'amount_min':
                            $transactions->where('tr_amount', '>=', $value * 100);
                            break;
                        case 'amount_max':
                            $transactions->where('tr_amount', '<=', $value * 100);
                            break;
                        case 'merchant_id':
                            $transactions->where('merchant_id', $value);
                            break;
                        case 'currency':
                            $transactions->where('tr_ccy', $value);
                            break;
                    }
                }
            }

            $total = $transactions->count();
            $results = $transactions->offset($offset)
                ->limit($limit)
                ->orderBy('tr_date_time', 'desc')
                ->get();

            // Format results for API
            $formattedResults = $results->map(function ($transaction) {
                return [
                    'payment_id' => $transaction->payment_id,
                    'transaction_date' => $transaction->tr_date_time,
                    'amount' => $transaction->tr_amount ? $transaction->tr_amount / 100 : 0,
                    'currency' => $transaction->tr_ccy,
                    'merchant_name' => $transaction->merchant_name,
                    'merchant_id' => $transaction->merchant_id,
                    'status' => $transaction->status,
                    'is_matched' => $transaction->is_matched,
                    'matched_at' => $transaction->matched_at,
                    'file_name' => $transaction->dectaFile->filename ?? null,
                    'gateway_info' => [
                        'transaction_id' => $transaction->gateway_transaction_id,
                        'account_id' => $transaction->gateway_account_id,
                        'shop_id' => $transaction->gateway_shop_id,
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResults,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ],
                'filters_applied' => array_filter($filters),
                'query' => $query
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get processing queue status
     */
    public function getProcessingStatus(): JsonResponse
    {
        try {
            $status = [
                'files' => [
                    'pending' => DectaFile::where('status', 'pending')->count(),
                    'processing' => DectaFile::where('status', 'processing')->count(),
                    'processed' => DectaFile::where('status', 'processed')
                        ->whereDate('processed_at', '>=', Carbon::today())
                        ->count(),
                    'failed' => DectaFile::where('status', 'failed')
                        ->whereDate('created_at', '>=', Carbon::today())
                        ->count(),
                ],
                'transactions' => [
                    'unmatched' => DectaTransaction::where('is_matched', false)
                        ->where('status', '!=', 'failed')
                        ->count(),
                    'failed_matching' => DectaTransaction::where('status', 'failed')
                        ->whereDate('created_at', '>=', Carbon::today())
                        ->count(),
                    'matched_today' => DectaTransaction::where('is_matched', true)
                        ->whereDate('matched_at', Carbon::today())
                        ->count(),
                ],
                'system' => [
                    'last_file_processed' => DectaFile::where('status', 'processed')
                        ->latest('processed_at')
                        ->value('processed_at'),
                    'stuck_files' => DectaFile::where('status', 'processing')
                        ->where('updated_at', '<', Carbon::now()->subHours(2))
                        ->count(),
                    'disk_usage' => $this->getDiskUsage(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $status,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get processing status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Trigger manual file processing
     */
    public function triggerProcessing(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->hasPermissionTo('process-decta-files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_ids' => 'nullable|array',
                'file_ids.*' => 'integer|exists:decta_files,id',
                'retry_failed' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // This would trigger background processing
            // Implementation depends on your queue system

            return response()->json([
                'success' => true,
                'message' => 'Processing triggered successfully',
                'queued_at' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger processing',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export data via API
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()->hasPermissionTo('export-decta-reports')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export permission required'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'report_type' => 'required|in:transactions,daily_summary,merchant_breakdown,matching',
                'format' => 'required|in:csv,excel,json',
                'filters' => 'nullable|array',
                'email' => 'nullable|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reportType = $request->get('report_type');
            $format = $request->get('format');
            $filters = $request->get('filters', []);
            $email = $request->get('email');

            // Generate the report data
            $data = $this->reportService->generateReport($reportType, $filters);

            // Export based on format
            switch ($format) {
                case 'csv':
                    $filePath = $this->exportService->exportToCsv($data, $reportType, $filters);
                    break;
                case 'excel':
                    $filePath = $this->exportService->exportToExcel($data, $reportType, $filters);
                    break;
                case 'json':
                    $filePath = $this->exportService->exportToJson($data, $reportType, $filters);
                    break;
            }

            // If email provided, send the file via email (implement as needed)
            if ($email) {
                // Queue email job
                // Mail::to($email)->queue(new ExportReady($filePath));
            }

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'download_url' => $this->exportService->getDownloadUrl($filePath),
                'file_size' => $this->exportService->getFileSize($filePath),
                'expires_at' => Carbon::now()->addDays(7)->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Webhook endpoint for external systems
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Validate webhook signature if needed
            // $this->validateWebhookSignature($request);

            $event = $request->get('event');
            $data = $request->get('data', []);

            switch ($event) {
                case 'file.processed':
                    $this->handleFileProcessedWebhook($data);
                    break;
                case 'transaction.matched':
                    $this->handleTransactionMatchedWebhook($data);
                    break;
                case 'system.alert':
                    $this->handleSystemAlertWebhook($data);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown event type'
                    ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get system health metrics
     */
    public function getHealthMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'database' => $this->checkDatabaseHealth(),
                'storage' => $this->checkStorageHealth(),
                'processing' => $this->checkProcessingHealth(),
                'matching' => $this->checkMatchingHealth(),
                'overall_status' => 'healthy' // Will be calculated based on individual checks
            ];

            // Determine overall status
            $hasErrors = collect($metrics)->except('overall_status')
                ->contains(function ($check) {
                    return $check['status'] === 'error';
                });

            $hasWarnings = collect($metrics)->except('overall_status')
                ->contains(function ($check) {
                    return $check['status'] === 'warning';
                });

            if ($hasErrors) {
                $metrics['overall_status'] = 'error';
            } elseif ($hasWarnings) {
                $metrics['overall_status'] = 'warning';
            }

            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function getDiskUsage(): array
    {
        $storagePath = storage_path('app');
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);

        return [
            'free_space' => $freeBytes,
            'total_space' => $totalBytes,
            'used_percentage' => $totalBytes > 0 ? (($totalBytes - $freeBytes) / $totalBytes) * 100 : 0
        ];
    }

    private function checkDatabaseHealth(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed'];
        }
    }

    private function checkStorageHealth(): array
    {
        $usage = $this->getDiskUsage();

        if ($usage['used_percentage'] > 90) {
            return ['status' => 'error', 'message' => 'Disk space critically low'];
        } elseif ($usage['used_percentage'] > 80) {
            return ['status' => 'warning', 'message' => 'Disk space running low'];
        }

        return ['status' => 'healthy', 'message' => 'Storage OK'];
    }

    private function checkProcessingHealth(): array
    {
        $stuckFiles = DectaFile::where('status', 'processing')
            ->where('updated_at', '<', Carbon::now()->subHours(2))
            ->count();

        if ($stuckFiles > 0) {
            return ['status' => 'warning', 'message' => "{$stuckFiles} files stuck in processing"];
        }

        return ['status' => 'healthy', 'message' => 'Processing OK'];
    }

    private function checkMatchingHealth(): array
    {
        $failedMatches = DectaTransaction::where('status', 'failed')
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($failedMatches > 100) {
            return ['status' => 'error', 'message' => 'High number of matching failures'];
        } elseif ($failedMatches > 50) {
            return ['status' => 'warning', 'message' => 'Elevated matching failures'];
        }

        return ['status' => 'healthy', 'message' => 'Matching OK'];
    }

    private function handleFileProcessedWebhook(array $data): void
    {
        // Handle file processed event
        // Update cache, send notifications, etc.
    }

    private function handleTransactionMatchedWebhook(array $data): void
    {
        // Handle transaction matched event
    }

    private function handleSystemAlertWebhook(array $data): void
    {
        // Handle system alert event
    }
}
