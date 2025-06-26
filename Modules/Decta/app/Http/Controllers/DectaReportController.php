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
            'report_type' => 'required|in:transactions,settlements,matching,daily_summary,merchant_breakdown,shop_breakdown,scheme,declined_transactions,approval_analysis,volume_breakdown',
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'shop_id' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:pending,matched,failed,approved,declined',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'export_format' => 'nullable|in:json,csv,excel',
            'sort_by' => 'nullable|in:merchant_legal_name,currency,card_type,amount,count,shop_id',
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
                'date_from', 'date_to', 'merchant_id', 'shop_id', 'currency',
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
     * Get shops for a specific merchant using the shops table
     */
    public function getShopsForMerchant(Request $request): JsonResponse
    {
        $merchantId = $request->route('merchantId'); // Get from URL parameter

        $validator = Validator::make(['merchant_id' => $merchantId], [
            'merchant_id' => 'required|integer|exists:merchants,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get the merchant details
            $merchant = DB::table('merchants')
                ->select('id', 'account_id', 'name', 'legal_name')
                ->where('id', $merchantId)
                ->first();

            if (!$merchant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }

            // UPDATED: Get shops from shops table with transaction statistics
            $shops = DB::table('shops as s')
                ->select([
                    's.id as shop_table_id',
                    's.shop_id',
                    's.merchant_id',
                    's.owner_name',
                    's.email',
                    's.website',
                    's.active',
                    's.created_at as shop_created_at',
                    // Get transaction statistics
                    DB::raw('COUNT(dt.id) as transaction_count'),
                    DB::raw('COALESCE(SUM(dt.tr_amount), 0) as total_amount'),
                    DB::raw('MIN(dt.tr_date_time) as first_transaction'),
                    DB::raw('MAX(dt.tr_date_time) as last_transaction'),
                    DB::raw('COUNT(DISTINCT dt.tr_ccy) as currencies_used'),
                    // FIXED: Proper array aggregation with filtering
                    DB::raw('array_agg(DISTINCT dt.tr_ccy) FILTER (WHERE dt.tr_ccy IS NOT NULL) as currencies_list')
                ])
                ->leftJoin('decta_transactions as dt', function ($join) {
                    $join->on('s.shop_id', '=', 'dt.gateway_shop_id')
                        ->where('dt.gateway_account_id', '=', DB::raw('(SELECT account_id FROM merchants WHERE id = s.merchant_id)'));
                })
                ->where('s.merchant_id', $merchantId)
                ->where('s.active', true)
                ->groupBy([
                    's.id', 's.shop_id', 's.merchant_id', 's.owner_name',
                    's.email', 's.website', 's.active', 's.created_at'
                ])
                ->orderBy('transaction_count', 'desc')
                ->get();

            $formattedShops = $shops->map(function ($shop) {
                // Handle currencies list properly
                $currencies = [];
                if ($shop->currencies_list) {
                    if (is_string($shop->currencies_list)) {
                        // Remove PostgreSQL array braces and split
                        $currencyString = trim($shop->currencies_list, '{}');
                        if (!empty($currencyString)) {
                            $currencies = array_filter(explode(',', $currencyString), function ($currency) {
                                return !empty(trim($currency)) && trim($currency) !== 'NULL';
                            });
                            $currencies = array_map('trim', $currencies);
                        }
                    } elseif (is_array($shop->currencies_list)) {
                        $currencies = array_filter($shop->currencies_list, function ($currency) {
                            return !empty($currency) && $currency !== 'NULL';
                        });
                    }
                }

                // Create display name with shop details
                $displayName = "Shop {$shop->shop_id}";
                if ($shop->owner_name) {
                    $displayName .= " - {$shop->owner_name}";
                }
                if ($shop->transaction_count > 0) {
                    $displayName .= " ({$shop->transaction_count} transactions)";
                } else {
                    $displayName .= " (No transactions)";
                }

                return [
                    'shop_table_id' => $shop->shop_table_id,
                    'shop_id' => $shop->shop_id,
                    'display_name' => $displayName,
                    'owner_name' => $shop->owner_name,
                    'email' => $shop->email,
                    'website' => $shop->website,
                    'active' => $shop->active,
                    'transaction_count' => $shop->transaction_count,
                    'total_amount' => $shop->total_amount ? $shop->total_amount / 100 : 0,
                    'first_transaction' => $shop->first_transaction,
                    'last_transaction' => $shop->last_transaction,
                    'currencies_used' => $shop->currencies_used,
                    'currencies' => array_values($currencies),
                    'shop_created_at' => $shop->shop_created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'merchant' => [
                        'id' => $merchantId,
                        'account_id' => $merchant->account_id,
                        'name' => $merchant->name ?: $merchant->legal_name
                    ],
                    'shops' => $formattedShops,
                    'summary' => [
                        'total_shops' => $formattedShops->count(),
                        'active_shops' => $formattedShops->where('active', true)->count(),
                        'shops_with_transactions' => $formattedShops->where('transaction_count', '>', 0)->count(),
                        'total_transactions' => $formattedShops->sum('transaction_count'),
                        'total_amount' => $formattedShops->sum('total_amount')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get shops for merchant', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load shops: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all shops with search functionality
     */
    public function getShops(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $limit = min($request->get('limit', 50), 100);
            $merchantId = $request->get('merchant_id');
            $activeOnly = $request->get('active_only', true);

            $query = DB::table('shops as s')
                ->select([
                    's.id as shop_table_id',
                    's.shop_id',
                    's.merchant_id',
                    's.owner_name',
                    's.email',
                    's.website',
                    's.active',
                    's.created_at as shop_created_at',
                    'm.name as merchant_name',
                    'm.legal_name as merchant_legal_name',
                    'm.account_id as merchant_account_id',
                    // Transaction statistics
                    DB::raw('COUNT(dt.id) as transaction_count'),
                    DB::raw('COALESCE(SUM(dt.tr_amount), 0) as total_amount'),
                    DB::raw('COUNT(DISTINCT dt.tr_ccy) as currency_count'),
                    DB::raw('MIN(dt.tr_date_time) as first_transaction'),
                    DB::raw('MAX(dt.tr_date_time) as last_transaction')
                ])
                ->leftJoin('merchants as m', 's.merchant_id', '=', 'm.id')
                ->leftJoin('decta_transactions as dt', function ($join) {
                    $join->on('s.shop_id', '=', 'dt.gateway_shop_id')
                        ->on('m.account_id', '=', 'dt.gateway_account_id');
                });

            // Apply filters
            if ($activeOnly) {
                $query->where('s.active', true);
            }

            if ($merchantId) {
                $query->where('s.merchant_id', $merchantId);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('s.shop_id', 'LIKE', "%{$search}%")
                        ->orWhere('s.owner_name', 'ILIKE', "%{$search}%")
                        ->orWhere('s.email', 'ILIKE', "%{$search}%")
                        ->orWhere('m.name', 'ILIKE', "%{$search}%")
                        ->orWhere('m.legal_name', 'ILIKE', "%{$search}%");
                });
            }

            $shops = $query->groupBy([
                's.id', 's.shop_id', 's.merchant_id', 's.owner_name', 's.email',
                's.website', 's.active', 's.created_at', 'm.name', 'm.legal_name', 'm.account_id'
            ])
                ->orderBy('transaction_count', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($shop) {
                    return [
                        'shop_table_id' => $shop->shop_table_id,
                        'shop_id' => $shop->shop_id,
                        'merchant_id' => $shop->merchant_id,
                        'merchant_name' => $shop->merchant_name ?: $shop->merchant_legal_name,
                        'merchant_account_id' => $shop->merchant_account_id,
                        'display_name' => $this->formatShopDisplayName($shop),
                        'owner_name' => $shop->owner_name,
                        'email' => $shop->email,
                        'website' => $shop->website,
                        'active' => $shop->active,
                        'transaction_count' => $shop->transaction_count,
                        'total_amount' => $shop->total_amount ? $shop->total_amount / 100 : 0,
                        'currency_count' => $shop->currency_count,
                        'first_transaction' => $shop->first_transaction,
                        'last_transaction' => $shop->last_transaction,
                        'shop_created_at' => $shop->shop_created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $shops
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load shops: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncShopsFromTransactions(Request $request): JsonResponse
    {
        try {
            $dryRun = $request->get('dry_run', true);
            $merchantId = $request->get('merchant_id'); // Optional: sync for specific merchant

            // Find shops in transactions that don't exist in shops table
            $query = "
            SELECT DISTINCT
                dt.gateway_shop_id as shop_id,
                dt.gateway_account_id,
                m.id as merchant_id,
                m.name as merchant_name,
                COUNT(*) as transaction_count,
                MIN(dt.tr_date_time) as first_seen,
                MAX(dt.tr_date_time) as last_seen
            FROM decta_transactions dt
            LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id
            LEFT JOIN shops s ON dt.gateway_shop_id = s.shop_id AND s.merchant_id = m.id
            WHERE dt.gateway_shop_id IS NOT NULL
              AND dt.gateway_shop_id != 0
              AND dt.gateway_shop_id != ''
              AND m.id IS NOT NULL
              AND s.id IS NULL  -- Shop doesn't exist in shops table
        ";

            $params = [];
            if ($merchantId) {
                $query .= " AND m.id = ?";
                $params[] = $merchantId;
            }

            $query .= "
            GROUP BY dt.gateway_shop_id, dt.gateway_account_id, m.id, m.name
            ORDER BY transaction_count DESC
        ";

            $missingShops = DB::select($query, $params);

            if ($dryRun) {
                return response()->json([
                    'success' => true,
                    'dry_run' => true,
                    'found_missing_shops' => count($missingShops),
                    'missing_shops' => array_map(function ($shop) {
                        return [
                            'shop_id' => $shop->shop_id,
                            'merchant_id' => $shop->merchant_id,
                            'merchant_name' => $shop->merchant_name,
                            'transaction_count' => $shop->transaction_count,
                            'first_seen' => $shop->first_seen,
                            'last_seen' => $shop->last_seen
                        ];
                    }, $missingShops)
                ]);
            }

            // Actually create the missing shops
            $created = 0;
            $errors = [];

            foreach ($missingShops as $shop) {
                try {
                    DB::table('shops')->insert([
                        'shop_id' => $shop->shop_id,
                        'merchant_id' => $shop->merchant_id,
                        'owner_name' => null, // Will need to be filled manually
                        'email' => null,
                        'website' => null,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    $errors[] = "Shop {$shop->shop_id} for merchant {$shop->merchant_id}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'dry_run' => false,
                'total_missing_shops' => count($missingShops),
                'shops_created' => $created,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync shops: ' . $e->getMessage()
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
            ->select(['tr_ccy'])
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
     * Format shop display name for dropdown
     */
    private function formatShopDisplayName($shop): string
    {
        $shopId = $shop->shop_id;
        $ownerName = $shop->owner_name;
        $merchantName = $shop->merchant_name ?? $shop->merchant_legal_name ?? 'Unknown Merchant';

        // Build display name
        $displayName = "Shop {$shopId}";

        if ($ownerName) {
            $displayName .= " - {$ownerName}";
        }

        if (isset($shop->transaction_count) && $shop->transaction_count > 0) {
            $displayName .= " ({$shop->transaction_count} txns)";
        } else {
            $displayName .= " (No transactions)";
        }

        return $displayName;
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
