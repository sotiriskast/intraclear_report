<?php

namespace Modules\Decta\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Decta\Repositories\DectaFileRepository;
use Modules\Decta\Repositories\DectaTransactionRepository;
use Modules\Decta\Models\DectaFile;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DectaDashboardController extends Controller
{
    /**
     * @var DectaFileRepository
     */
    protected $fileRepository;

    /**
     * @var DectaTransactionRepository
     */
    protected $transactionRepository;

    public function __construct(
        DectaFileRepository $fileRepository,
        DectaTransactionRepository $transactionRepository
    ) {
        $this->fileRepository = $fileRepository;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Display the main dashboard
     */
    public function index()
    {
        $fileStats = $this->fileRepository->getStatistics(30);
        $transactionStats = $this->transactionRepository->getStatistics();

        // Get recent files
        $recentFiles = DectaFile::with(['dectaTransactions' => function($query) {
            $query->select('decta_file_id')
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('SUM(CASE WHEN is_matched = 1 THEN 1 ELSE 0 END) as matched_count')
                ->groupBy('decta_file_id');
        }])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get unmatched transactions by file
        $unmatchedByFile = DectaTransaction::unmatched()
            ->with('dectaFile')
            ->selectRaw('decta_file_id, COUNT(*) as count')
            ->groupBy('decta_file_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        return view('decta::dashboard.index', compact(
            'fileStats',
            'transactionStats',
            'recentFiles',
            'unmatchedByFile'
        ));
    }

    /**
     * Get dashboard statistics as JSON
     */
    public function getStats(): JsonResponse
    {
        $fileStats = $this->fileRepository->getStatistics(30);
        $transactionStats = $this->transactionRepository->getStatistics();

        // Recent processing activity
        $recentActivity = DectaFile::whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as files_processed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Matching trends
        $matchingTrends = DectaTransaction::whereDate('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('DATE(created_at) as date,
                        COUNT(*) as total_transactions,
                        SUM(CASE WHEN is_matched = 1 THEN 1 ELSE 0 END) as matched_transactions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'file_stats' => $fileStats,
            'transaction_stats' => $transactionStats,
            'recent_activity' => $recentActivity,
            'matching_trends' => $matchingTrends,
        ]);
    }

    /**
     * Display file details
     */
    public function showFile(int $fileId)
    {
        $file = DectaFile::findOrFail($fileId);

        $stats = $this->transactionRepository->getStatistics($fileId);

        $transactions = $this->transactionRepository->getPaginated([
            'file_id' => $fileId
        ], 50);

        return view('decta::dashboard.file-details', compact(
            'file',
            'stats',
            'transactions'
        ));
    }

    /**
     * Display transaction details
     */
    public function showTransaction(int $transactionId)
    {
        $transaction = DectaTransaction::with('dectaFile')->findOrFail($transactionId);

        // Get matching attempts
        $matchingAttempts = $transaction->matching_attempts ?? [];

        // Get similar transactions for comparison
        $similarTransactions = DectaTransaction::where('id', '!=', $transactionId)
            ->where(function($query) use ($transaction) {
                $query->where('tr_amount', $transaction->tr_amount)
                    ->orWhere('tr_approval_id', $transaction->tr_approval_id)
                    ->orWhere('tr_ret_ref_nr', $transaction->tr_ret_ref_nr);
            })
            ->limit(5)
            ->get();

        return view('decta::dashboard.transaction-details', compact(
            'transaction',
            'matchingAttempts',
            'similarTransactions'
        ));
    }

    /**
     * Get transactions with filters
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $filters = $request->only([
            'file_id',
            'status',
            'is_matched',
            'payment_id',
            'merchant_id',
            'currency',
            'date_from',
            'date_to',
            'amount_from',
            'amount_to'
        ]);

        $perPage = $request->get('per_page', 20);

        $transactions = $this->transactionRepository->getPaginated($filters, $perPage);

        return response()->json($transactions);
    }

    /**
     * Get unmatched transactions for review
     */
    public function getUnmatchedTransactions(Request $request): JsonResponse
    {
        $fileId = $request->get('file_id');
        $limit = $request->get('limit', 50);

        $transactions = $this->transactionRepository->getUnmatched($fileId, $limit);

        // Add suggested matches for each transaction
        $transactionsWithSuggestions = $transactions->map(function($transaction) {
            $suggestions = $this->getSuggestedMatches($transaction);
            $transaction->suggested_matches = $suggestions;
            return $transaction;
        });

        return response()->json($transactionsWithSuggestions);
    }

    /**
     * Manual transaction matching
     */
    public function manualMatch(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:decta_transactions,id',
            'gateway_transaction_id' => 'required|integer',
            'account_id' => 'required|integer',
            'shop_id' => 'required|integer',
            'trx_id' => 'required|string',
        ]);

        try {
            $transaction = DectaTransaction::findOrFail($request->transaction_id);

            $gatewayData = [
                'transaction_id' => $request->gateway_transaction_id,
                'account_id' => $request->account_id,
                'shop_id' => $request->shop_id,
                'trx_id' => $request->trx_id,
            ];

            $transaction->markAsMatched($gatewayData);
            $transaction->addMatchingAttempt([
                'strategy' => 'manual_match',
                'matched_by' => auth()->user()->id ?? 'system',
                'gateway_data' => $gatewayData,
                'result' => 'success',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction matched successfully',
                'transaction' => $transaction->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to match transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations on transactions
     */
    public function bulkOperation(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:retry_matching,mark_failed,delete',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer|exists:decta_transactions,id'
        ]);

        try {
            $transactionIds = $request->transaction_ids;
            $action = $request->action;
            $affected = 0;

            switch ($action) {
                case 'retry_matching':
                    $affected = $this->transactionRepository->bulkUpdateStatus(
                        $transactionIds,
                        DectaTransaction::STATUS_PENDING,
                        ['matching_attempts' => null]
                    );
                    break;

                case 'mark_failed':
                    $affected = $this->transactionRepository->bulkUpdateStatus(
                        $transactionIds,
                        DectaTransaction::STATUS_FAILED,
                        ['error_message' => 'Manually marked as failed']
                    );
                    break;

                case 'delete':
                    $affected = DectaTransaction::whereIn('id', $transactionIds)->delete();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk operation completed. {$affected} transactions affected.",
                'affected_count' => $affected
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get processing health status
     */
    public function getHealthStatus(): JsonResponse
    {
        try {
            // Check recent file processing
            $recentFiles = DectaFile::whereDate('created_at', '>=', Carbon::now()->subDays(1))->count();

            // Check for stuck processing
            $stuckProcessing = DectaFile::where('status', DectaFile::STATUS_PROCESSING)
                ->where('updated_at', '<', Carbon::now()->subHours(2))
                ->count();

            // Check match rates
            $recentTransactions = DectaTransaction::whereDate('created_at', '>=', Carbon::now()->subDays(1));
            $totalRecent = $recentTransactions->count();
            $matchedRecent = $recentTransactions->where('is_matched', true)->count();
            $matchRate = $totalRecent > 0 ? ($matchedRecent / $totalRecent) * 100 : 0;

            // Check for failed files
            $failedFiles = DectaFile::where('status', DectaFile::STATUS_FAILED)
                ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            $status = 'healthy';
            $issues = [];

            if ($recentFiles === 0) {
                $status = 'warning';
                $issues[] = 'No files processed in the last 24 hours';
            }

            if ($stuckProcessing > 0) {
                $status = 'error';
                $issues[] = "{$stuckProcessing} files stuck in processing state";
            }

            if ($matchRate < 70 && $totalRecent > 10) {
                $status = 'warning';
                $issues[] = "Low match rate: {$matchRate}% (last 24h)";
            }

            if ($failedFiles > 5) {
                $status = 'warning';
                $issues[] = "{$failedFiles} files failed in the last 7 days";
            }

            return response()->json([
                'status' => $status,
                'issues' => $issues,
                'metrics' => [
                    'recent_files' => $recentFiles,
                    'stuck_processing' => $stuckProcessing,
                    'match_rate' => round($matchRate, 2),
                    'failed_files' => $failedFiles,
                ],
                'timestamp' => Carbon::now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'issues' => ['Health check failed: ' . $e->getMessage()],
                'timestamp' => Carbon::now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get suggested matches for a transaction
     */
    private function getSuggestedMatches(DectaTransaction $transaction, int $limit = 3): array
    {
        // This would implement fuzzy matching logic
        // For now, return empty array - can be enhanced later
        return [];
    }
}
