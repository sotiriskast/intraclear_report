<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Decta\Services\DectaExportService;
use Modules\Decta\Services\DectaReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DectaReportController extends Controller
{
    protected $reportService;
    protected $exportService;

    public function __construct(DectaReportService $reportService, DectaExportService $exportService)
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    /**
     * Display the main reports page
     */
    public function index()
    {
        // Get summary statistics for the dashboard
        $summary = $this->reportService->getSummaryStats();

        // Get active merchants for the dropdown
        $merchants = $this->getActiveMerchants();
        $currency = $this->getCurrency();

        return view('decta::reports.index', compact('summary', 'merchants', 'currency'));
    }

    /**
     * Generate transaction report based on filters
     */
    public function generateReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'report_type' => 'required|in:transactions,settlements,matching,daily_summary,merchant_breakdown,scheme,declined_transactions,approval_analysis,volume_breakdown',
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:pending,matched,failed,approved,declined',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'export_format' => 'nullable|in:json,csv,excel',
            // Add sorting options
            'sort_by' => 'nullable|in:merchant_legal_name,currency,card_type,amount,count',
            'sort_direction' => 'nullable|in:asc,desc'
        ]);




        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only([
                'date_from', 'date_to', 'merchant_id', 'currency',
                'status', 'amount_min', 'amount_max', 'sort_by', 'sort_direction'
            ]);

            $reportType = $request->input('report_type');
            $exportFormat = $request->input('export_format', 'json');

            Log::info('Generating report', [
                'report_type' => $reportType,
                'export_format' => $exportFormat,
                'filters' => $filters
            ]);

            // Generate the report data
            if ($reportType === 'volume_breakdown') {
                $data = $this->reportService->getDetailedVolumeBreakdown($filters);

                if (!$data['has_data']) {
                    if ($exportFormat === 'csv' || $exportFormat === 'excel') {
                        // Create empty export file
                        $emptyData = [
                            [
                                'message' => 'No data found for the selected criteria',
                                'date_from' => $filters['date_from'],
                                'date_to' => $filters['date_to'],
                                'report_type' => $reportType,
                                'generated_at' => Carbon::now()->toISOString()
                            ]
                        ];
                        return $this->handleExport($emptyData, $reportType, $exportFormat, $filters);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => $data,
                        'filters_applied' => $filters,
                        'generated_at' => Carbon::now()->toISOString(),
                        'message' => 'No transaction data found for the selected criteria.'
                    ]);
                }
            } else {
                $data = $this->reportService->generateReport($reportType, $filters);
            }

            // Handle export formats
            if ($exportFormat === 'csv' || $exportFormat === 'excel') {
                return $this->handleExport($data, $reportType, $exportFormat, $filters);
            }

            // Return JSON for online viewing
            return response()->json([
                'success' => true,
                'data' => $data,
                'filters_applied' => $filters,
                'generated_at' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'report_type' => $request->input('report_type'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
                'debug_info' => app()->environment('local') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Handle export generation and download
     */
    private function handleExport($data, $reportType, $exportFormat, $filters)
    {
        try {
            // For volume breakdown, we need to flatten the data structure for CSV
            if ($reportType === 'volume_breakdown') {
                $data = $this->flattenVolumeBreakdownForExport($data);
            }

            if ($exportFormat === 'csv') {
                $filePath = $this->exportService->exportToCsv($data, $reportType, $filters);
            } else {
                $filePath = $this->exportService->exportToExcel($data, $reportType, $filters);
            }

            $fullPath = storage_path('app/' . $filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception("Export file was not created successfully");
            }

            $filename = basename($filePath);

            Log::info('Export completed successfully', [
                'file_path' => $filePath,
                'file_size' => filesize($fullPath)
            ]);

            return response()->download($fullPath, $filename)->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('Export generation failed', [
                'error' => $e->getMessage(),
                'report_type' => $reportType,
                'export_format' => $exportFormat
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flatten volume breakdown data for CSV export
     */
    private function flattenVolumeBreakdownForExport($data)
    {
        if (!isset($data['continent_breakdown']) || empty($data['continent_breakdown'])) {
            return [];
        }

        $flatData = [];

        foreach ($data['continent_breakdown'] as $continent => $continentData) {
            if (!isset($continentData['card_brands'])) continue;

            foreach ($continentData['card_brands'] as $cardBrand => $brandData) {
                if (!isset($brandData['card_types'])) continue;

                foreach ($brandData['card_types'] as $cardType => $typeData) {
                    if (!isset($typeData['currencies'])) continue;

                    foreach ($typeData['currencies'] as $currency => $amount) {
                        $flatData[] = [
                            'continent' => $continent,
                            'card_brand' => $cardBrand,
                            'card_type' => $cardType,
                            'currency' => $currency,
                            'amount' => $amount,
                            'transaction_count' => $typeData['total_transactions'] ?? 0,
                            'percentage_of_total' => $data['totals']['total_volume'] > 0
                                ? round(($amount / $data['totals']['total_volume']) * 100, 2)
                                : 0
                        ];
                    }
                }
            }
        }

        return $flatData;
    }
    /**
     * Get volume breakdown report specifically
     */
    public function getVolumeBreakdown(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'currency' => 'nullable|string|size:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $filters = $request->only(['date_from', 'date_to', 'merchant_id', 'currency']);

            // Validate date range is not too wide (optional safeguard)
            $dateFrom = Carbon::parse($filters['date_from']);
            $dateTo = Carbon::parse($filters['date_to']);
            $daysDiff = $dateFrom->diffInDays($dateTo);

            if ($daysDiff > 365) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 365 days for volume breakdown reports.'
                ], 422);
            }

            $data = $this->reportService->getDetailedVolumeBreakdown($filters);

            // Check if we have any data
            if (!$data['has_data']) {
                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'filters_applied' => $filters,
                    'generated_at' => Carbon::now()->toISOString(),
                    'message' => 'No transaction data found for the selected criteria. Try adjusting your filters or date range.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'filters_applied' => $filters,
                'generated_at' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Volume breakdown generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $filters ?? [],
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate volume breakdown: ' . $e->getMessage(),
                'debug_info' => app()->environment('local') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }
    /**
     * Get declined transactions (API endpoint for modal)
     */
    public function getDeclinedTransactions(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $filters = $request->only(['merchant_id', 'currency', 'date_from', 'date_to', 'amount_min', 'amount_max']);

            $data = $this->reportService->getDeclinedTransactions($filters, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get declined transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get decline reasons summary
     */
    public function getDeclineReasons(Request $request): JsonResponse
    {
        try {
            $days = $request->input('days', 30);
            $data = $this->reportService->getDeclineReasons($days);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get decline reasons: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed decline analysis
     */
    public function getDeclineAnalysis(Request $request): JsonResponse
    {
        try {
            $analyticsService = app(\Modules\Decta\Services\DectaAnalyticsService::class);

            $filters = $request->only(['date_from', 'date_to', 'merchant_id']);
            $data = $analyticsService->analyzeDeclinePatterns($filters);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get decline analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare decline rates between two periods
     */
    public function compareDeclineRates(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period1_start' => 'required|date',
                'period1_end' => 'required|date|after_or_equal:period1_start',
                'period2_start' => 'required|date',
                'period2_end' => 'required|date|after_or_equal:period2_start',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $analyticsService = app(\Modules\Decta\Services\DectaAnalyticsService::class);

            $data = $analyticsService->getDeclineRateComparison(
                $request->input('period1_start'),
                $request->input('period1_end'),
                $request->input('period2_start'),
                $request->input('period2_end')
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare decline rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time dashboard data
     */
    public function getDashboardData(): JsonResponse
    {
        try {
            $data = [
                'summary' => $this->reportService->getSummaryStats(),
                'recent_files' => $this->reportService->getRecentFiles(10),
                'processing_status' => $this->reportService->getProcessingStatus(),
                'matching_trends' => $this->reportService->getMatchingTrends(7),
                'approval_trends' => $this->reportService->getApprovalTrends(7),
                'top_merchants' => $this->reportService->getTopMerchants(5), // Use the improved method
                'currency_breakdown' => $this->reportService->getCurrencyBreakdown(),
                'decline_reasons' => $this->reportService->getDeclineReasons(7)
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard data error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Try fallback with simple method
            try {
                $data = [
                    'summary' => $this->reportService->getSummaryStats(),
                    'recent_files' => $this->reportService->getRecentFiles(10),
                    'processing_status' => $this->reportService->getProcessingStatus(),
                    'matching_trends' => $this->reportService->getMatchingTrends(7),
                    'approval_trends' => $this->reportService->getApprovalTrends(7),
                    'top_merchants' => $this->reportService->getTopMerchantsSimple(5),
                    'currency_breakdown' => $this->reportService->getCurrencyBreakdown(),
                    'decline_reasons' => $this->reportService->getDeclineReasons(7)
                ];

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'timestamp' => Carbon::now()->toISOString(),
                    'note' => 'Using fallback method for top merchants'
                ]);

            } catch (\Exception $fallbackError) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to load dashboard data: ' . $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage()
                ], 500);
            }
        }
    }

    /**
     * Get transaction details for a specific payment ID
     */
    public function getTransactionDetails($paymentId): JsonResponse
    {
        try {
            $transaction = $this->reportService->getTransactionDetails($paymentId);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unmatched transactions for manual review
     */
    public function getUnmatchedTransactions(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $filters = $request->only(['merchant_id', 'currency', 'date_from', 'date_to']);

            $data = $this->reportService->getUnmatchedTransactions($filters, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unmatched transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active merchants for dropdown
     */
    private function getActiveMerchants()
    {
        return DB::table('merchants')
            ->select([
                'id',
                'name',
                'legal_name',
                'account_id'
            ])
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'display_name' => $this->formatMerchantDisplayName($merchant),
                    'account_id' => $merchant->account_id
                ];
            });
    }

    private function getCurrency()
    {
        return DB::table('decta_transactions')
            ->select([
                'tr_ccy'
            ])
            ->distinct()
            ->orderBy('tr_ccy')
            ->pluck('tr_ccy');
    }

    /**
     * Format merchant display name for dropdown
     */
    private function formatMerchantDisplayName($merchant)
    {
        $name = $merchant->name ?: $merchant->legal_name;

        if (!$name) {
            return "Merchant #{$merchant->id}";
        }

        return $merchant->account_id ? "{$name} (ID: {$merchant->account_id})" : $name;
    }

    /**
     * API endpoint to get merchants (for dynamic loading if needed)
     */
    public function getMerchants(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $limit = min($request->get('limit', 50), 100);

            $query = DB::table('merchants')
                ->select(['id', 'name', 'legal_name', 'account_id'])
                ->where('active', true);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('legal_name', 'ILIKE', "%{$search}%")
                        ->orWhere('account_id', 'LIKE', "%{$search}%");
                });
            }

            $merchants = $query->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(function ($merchant) {
                    return [
                        'id' => $merchant->id,
                        'account_id' => $merchant->account_id,
                        'display_name' => $this->formatMerchantDisplayName($merchant)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $merchants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load merchants: ' . $e->getMessage()
            ], 500);
        }
    }
}
