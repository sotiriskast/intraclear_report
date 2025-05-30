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

        return view('decta::reports.index', compact('summary', 'merchants'));
    }

    /**
     * Generate transaction report based on filters
     */
    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'report_type' => 'required|in:transactions,settlements,matching,daily_summary,merchant_breakdown,scheme',
            'merchant_id' => 'nullable|integer|exists:merchants,id',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|in:pending,matched,failed',
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
     * Get CSV headers for different report types
     */
    protected function getCsvHeaders($reportType): array
    {
        switch ($reportType) {
            case 'transactions':
                return [
                    'Payment ID', 'Transaction Date', 'Amount', 'Currency',
                    'Merchant Name', 'Merchant ID', 'Status', 'Gateway Match',
                    'Processing Date', 'Error Message'
                ];
            case 'settlements':
                return [
                    'Date', 'Merchant', 'Total Transactions', 'Total Amount',
                    'Matched Transactions', 'Unmatched Transactions', 'Match Rate'
                ];
            case 'daily_summary':
                return [
                    'Date', 'Total Transactions', 'Total Amount', 'Matched Count',
                    'Unmatched Count', 'Failed Count', 'Match Rate %'
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
                    ($row['tr_amount'] ?? 0) / 100, // Convert from cents
                    $row['tr_ccy'] ?? '',
                    $row['merchant_name'] ?? '',
                    $row['merchant_id'] ?? '',
                    $row['status'] ?? '',
                    $row['is_matched'] ? 'Yes' : 'No',
                    $row['matched_at'] ?? '',
                    $row['error_message'] ?? ''
                ];
            case 'daily_summary':
                return [
                    $row['date'] ?? '',
                    $row['total_transactions'] ?? 0,
                    $row['total_amount'] ?? 0,
                    $row['matched_count'] ?? 0,
                    $row['unmatched_count'] ?? 0,
                    $row['failed_count'] ?? 0,
                    round($row['match_rate'] ?? 0, 2)
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
                'top_merchants' => $this->reportService->getTopMerchants(5),
                'currency_breakdown' => $this->reportService->getCurrencyBreakdown()
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data: ' . $e->getMessage()
            ], 500);
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
     * Export data to CSV
     */
    protected function exportToCsv($data, $reportType)
    {
        $filename = "decta_{$reportType}_" . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data, $reportType) {
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
