<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Decta\Services\DectaReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DectaReportController extends Controller
{
    protected $reportService;

    public function __construct(DectaReportService $reportService)
    {
        $this->reportService = $reportService;
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
    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'report_type' => 'required|in:transactions,settlements,matching,daily_summary,merchant_breakdown,scheme,declined_transactions,approval_analysis',
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:pending,matched,failed,approved,declined',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'export_format' => 'nullable|in:json,csv,excel'
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
                'status', 'amount_min', 'amount_max'
            ]);

            $reportType = $request->input('report_type');
            $exportFormat = $request->input('export_format', 'json');

            $data = $this->reportService->generateReport($reportType, $filters);

            if ($exportFormat === 'csv') {
                return $this->exportToCsv($data, $reportType);
            } elseif ($exportFormat === 'excel') {
                return $this->exportToExcel($data, $reportType);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'filters_applied' => $filters,
                'generated_at' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
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
    public function debugMerchantData(): JsonResponse
    {
        try {
            // Get raw merchant data to see duplications
            $rawMerchantData = DB::select("
                SELECT
                    dt.merchant_id,
                    dt.merchant_name,
                    m.name as merchant_db_name,
                    m.legal_name as merchant_legal_name,
                    m.account_id,
                    COUNT(*) as transaction_count,
                    COUNT(DISTINCT dt.tr_ccy) as currency_count,
                    array_agg(DISTINCT dt.tr_ccy) as currencies
                FROM decta_transactions dt
                LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id
                WHERE dt.merchant_id IS NOT NULL
                    AND dt.created_at >= CURRENT_DATE - INTERVAL '30 days'
                    AND dt.tr_ccy IS NOT NULL
                GROUP BY dt.merchant_id, dt.merchant_name, m.name, m.legal_name, m.account_id
                ORDER BY transaction_count DESC
                LIMIT 20
            ");

            // Find potential duplicates by name similarity
            $merchantNames = [];
            foreach ($rawMerchantData as $merchant) {
                $normalizedName = strtolower(trim($merchant->merchant_db_name ?: $merchant->merchant_legal_name ?: $merchant->merchant_name));
                if (!isset($merchantNames[$normalizedName])) {
                    $merchantNames[$normalizedName] = [];
                }
                $merchantNames[$normalizedName][] = $merchant;
            }

            $duplicates = array_filter($merchantNames, function($merchants) {
                return count($merchants) > 1;
            });

            // Get specific data for "fintzero"
            $fintzeroData = DB::select("
                SELECT
                    dt.merchant_id,
                    dt.merchant_name,
                    m.name as merchant_db_name,
                    m.legal_name as merchant_legal_name,
                    m.account_id,
                    dt.tr_ccy,
                    COUNT(*) as transaction_count,
                    SUM(dt.tr_amount) as total_amount
                FROM decta_transactions dt
                LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id
                WHERE (
                    LOWER(dt.merchant_name) LIKE '%fintzero%'
                    OR LOWER(m.name) LIKE '%fintzero%'
                    OR LOWER(m.legal_name) LIKE '%fintzero%'
                )
                AND dt.created_at >= CURRENT_DATE - INTERVAL '30 days'
                GROUP BY dt.merchant_id, dt.merchant_name, m.name, m.legal_name, m.account_id, dt.tr_ccy
                ORDER BY transaction_count DESC
            ");

            return response()->json([
                'success' => true,
                'debug_data' => [
                    'raw_merchant_count' => count($rawMerchantData),
                    'raw_merchants' => array_slice($rawMerchantData, 0, 10), // First 10 for inspection
                    'duplicate_groups' => $duplicates,
                    'fintzero_specific' => $fintzeroData,
                    'total_duplicate_groups' => count($duplicates)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Debug failed: ' . $e->getMessage()
            ], 500);
        }
    }
    public function testMerchantGrouping(): JsonResponse
    {
        try {
            $originalMethod = $this->reportService->getTopMerchantsSimple(10);
            $improvedMethod = $this->reportService->getTopMerchants(10);

            return response()->json([
                'success' => true,
                'comparison' => [
                    'original_method' => [
                        'count' => count($originalMethod),
                        'merchants' => array_map(function($m) {
                            return [
                                'id' => $m['merchant_id'],
                                'name' => $m['merchant_name'],
                                'transactions' => $m['total_transactions'],
                                'currencies' => $m['currency_count']
                            ];
                        }, $originalMethod)
                    ],
                    'improved_method' => [
                        'count' => count($improvedMethod),
                        'merchants' => array_map(function($m) {
                            return [
                                'id' => $m['merchant_id'],
                                'name' => $m['merchant_name'],
                                'transactions' => $m['total_transactions'],
                                'currencies' => $m['currency_count']
                            ];
                        }, $improvedMethod)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function debugDashboardData(): JsonResponse
    {
        $results = [];
        $errors = [];

        // Test each component individually
        try {
            $results['summary'] = $this->reportService->getSummaryStats();
            \Log::info('Summary stats loaded successfully');
        } catch (\Exception $e) {
            $errors['summary'] = $e->getMessage();
            \Log::error('Summary stats failed: ' . $e->getMessage());
        }

        try {
            $results['recent_files'] = $this->reportService->getRecentFiles(10);
            \Log::info('Recent files loaded successfully');
        } catch (\Exception $e) {
            $errors['recent_files'] = $e->getMessage();
            \Log::error('Recent files failed: ' . $e->getMessage());
        }

        try {
            $results['processing_status'] = $this->reportService->getProcessingStatus();
            \Log::info('Processing status loaded successfully');
        } catch (\Exception $e) {
            $errors['processing_status'] = $e->getMessage();
            \Log::error('Processing status failed: ' . $e->getMessage());
        }

        try {
            $results['matching_trends'] = $this->reportService->getMatchingTrends(7);
            \Log::info('Matching trends loaded successfully');
        } catch (\Exception $e) {
            $errors['matching_trends'] = $e->getMessage();
            \Log::error('Matching trends failed: ' . $e->getMessage());
        }

        try {
            $results['approval_trends'] = $this->reportService->getApprovalTrends(7);
            \Log::info('Approval trends loaded successfully');
        } catch (\Exception $e) {
            $errors['approval_trends'] = $e->getMessage();
            \Log::error('Approval trends failed: ' . $e->getMessage());
        }

        try {
            $results['top_merchants'] = $this->reportService->getTopMerchantsSimple(5);
            \Log::info('Top merchants loaded successfully');
        } catch (\Exception $e) {
            $errors['top_merchants'] = $e->getMessage();
            \Log::error('Top merchants failed: ' . $e->getMessage());
        }

        try {
            $results['currency_breakdown'] = $this->reportService->getCurrencyBreakdown();
            \Log::info('Currency breakdown loaded successfully');
        } catch (\Exception $e) {
            $errors['currency_breakdown'] = $e->getMessage();
            \Log::error('Currency breakdown failed: ' . $e->getMessage());
        }

        try {
            $results['decline_reasons'] = $this->reportService->getDeclineReasons(7);
            \Log::info('Decline reasons loaded successfully');
        } catch (\Exception $e) {
            $errors['decline_reasons'] = $e->getMessage();
            \Log::error('Decline reasons failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'component_count' => count($results),
            'error_count' => count($errors),
            'debug_info' => [
                'service_class' => get_class($this->reportService),
                'available_methods' => get_class_methods($this->reportService)
            ]
        ]);
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
     * Get CSV headers for different report types
     */
    protected function getCsvHeaders($reportType): array
    {
        switch ($reportType) {
            case 'transactions':
                return [
                    'Payment ID', 'Date', 'Amount', 'Currency', 'Merchant', 'Status', 'Gateway Status', 'Matched'
                ];
            case 'declined_transactions':
                return [
                    'Payment ID', 'Date', 'Amount', 'Currency', 'Merchant', 'Gateway Status', 'Reason'
                ];
            case 'approval_analysis':
                return [
                    'Merchant ID', 'Merchant Name', 'Approved Count', 'Declined Count',
                    'Approved Amount', 'Declined Amount', 'Total with Status', 'Approval Rate %'
                ];
            case 'settlements':
                return [
                    'Date', 'Merchant', 'Total Transactions', 'Total Amount',
                    'Matched Transactions', 'Unmatched Transactions', 'Match Rate'
                ];
            case 'daily_summary':
                return [
                    'Date', 'Total Transactions', 'Total Amount', 'Matched', 'Match Rate',
                    'Approved', 'Declined', 'Approval Rate'
                ];
            case 'scheme':
                return [
                    'Card Type', 'Transaction Type', 'Currency', 'Amount',
                    'Count', 'Fee', 'Merchant Legal Name'
                ];
            default:
                return ['Data'];
        }
    }

    /**
     * Format row data for CSV export
     */
    protected function formatRowForCsv($row, $reportType): array
    {
        switch ($reportType) {
            case 'transactions':
                return [
                    $row['payment_id'] ?? '',
                    $row['tr_date_time'] ?? '',
                    ($row['tr_amount'] ?? 0) / 100,
                    $row['tr_ccy'] ?? '',
                    $row['merchant_name'] ?? '',
                    $row['status'] ?? '',
                    $row['gateway_transaction_status'] ?? '',
                    $row['is_matched'] ? 'Yes' : 'No'
                ];
            case 'declined_transactions':
                return [
                    $row['payment_id'] ?? '',
                    $row['transaction_date'] ?? '',
                    $row['amount'] ?? 0,
                    $row['currency'] ?? '',
                    $row['merchant_name'] ?? '',
                    $row['gateway_status'] ?? '',
                    $row['error_message'] ?? ''
                ];
            case 'approval_analysis':
                return [
                    $row['merchant_id'] ?? '',
                    $row['merchant_name'] ?? '',
                    $row['approved_count'] ?? 0,
                    $row['declined_count'] ?? 0,
                    $row['approved_amount'] ?? 0,
                    $row['declined_amount'] ?? 0,
                    $row['total_with_status'] ?? 0,
                    round($row['approval_rate'] ?? 0, 2)
                ];
            case 'daily_summary':
                return [
                    $row['date'] ?? '',
                    $row['total_transactions'] ?? 0,
                    $row['total_amount'] ?? 0,
                    $row['matched_count'] ?? 0,
                    round($row['match_rate'] ?? 0, 2),
                    $row['approved_count'] ?? 0,
                    $row['declined_count'] ?? 0,
                    round($row['approval_rate'] ?? 0, 2)
                ];
            case 'scheme':
                return [
                    $row['card_type'] ?? '',
                    $row['transaction_type'] ?? '',
                    $row['currency'] ?? '',
                    $row['amount'] ?? 0,
                    $row['count'] ?? 0,
                    $row['fee'] ?? 0,
                    $row['merchant_legal_name'] ?? ''
                ];
            default:
                return array_values((array)$row);
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

    /**
     * Export data to CSV
     */
    protected function exportToCsv($data, $reportType)
    {
        $filename = "decta_{$reportType}_" . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data, $reportType) {
            $file = fopen('php://output', 'w');

            // Add headers based on report type
            $csvHeaders = $this->getCsvHeaders($reportType);
            fputcsv($file, $csvHeaders);

            // Add data rows
            foreach ($data as $row) {
                fputcsv($file, $this->formatRowForCsv($row, $reportType));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data to Excel (placeholder - would need PhpSpreadsheet)
     */
    protected function exportToExcel($data, $reportType)
    {
        return $this->exportToCsv($data, $reportType);
    }
}
