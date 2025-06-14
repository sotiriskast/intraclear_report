<?php

namespace Modules\Decta\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DectaReportService
{
    /**
     * Get summary statistics for dashboard
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $lastMonth = Carbon::now()->subDays(30);

        // Main statistics using PostgreSQL
        $stats = DB::select("
        SELECT
            COUNT(*) as total_transactions,
            COUNT(*) FILTER (WHERE is_matched = true) as matched_transactions,
            COUNT(*) FILTER (WHERE is_matched = false) as unmatched_transactions,
            COUNT(*) FILTER (WHERE status = 'failed') as failed_transactions,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as approved_transactions,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as declined_transactions,
            COUNT(DISTINCT gateway_account_id) FILTER (WHERE gateway_account_id IS NOT NULL) as unique_merchants,
            COUNT(DISTINCT tr_ccy) FILTER (WHERE tr_ccy IS NOT NULL) as unique_currencies
        FROM decta_transactions
        WHERE created_at >= ?
    ", [$lastMonth]);

        // Today's and yesterday's activity
        $todayStats = DB::select("
        SELECT
            COUNT(*) as today_transactions,
            COUNT(*) FILTER (WHERE is_matched = true) as today_matched,
            COUNT(*) FILTER (WHERE is_matched = false) as today_unmatched,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as today_approved,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as today_declined
        FROM decta_transactions
        WHERE DATE(created_at) = ?
    ", [$today->toDateString()]);

        $yesterdayStats = DB::select("
        SELECT
            COUNT(*) as yesterday_transactions,
            COUNT(*) FILTER (WHERE is_matched = false) as yesterday_unmatched,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as yesterday_approved,
            COUNT(*) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as yesterday_declined
        FROM decta_transactions
        WHERE DATE(created_at) = ?
    ", [$yesterday->toDateString()]);

        // Get amounts by currency for detailed breakdown
        $currencyBreakdown = DB::select("
        SELECT
            tr_ccy as currency,
            SUM(tr_amount) as total_amount,
            SUM(tr_amount) FILTER (WHERE is_matched = true) as matched_amount,
            SUM(tr_amount) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as approved_amount,
            SUM(tr_amount) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as declined_amount,
            COUNT(*) as transaction_count
        FROM decta_transactions
        WHERE created_at >= ?
            AND tr_ccy IS NOT NULL
        GROUP BY tr_ccy
        ORDER BY transaction_count DESC
    ", [$lastMonth]);

        // Get today's and yesterday's amounts by currency
        $todayCurrencyAmounts = DB::select("
        SELECT
            tr_ccy as currency,
            SUM(tr_amount) as total_amount
        FROM decta_transactions
        WHERE DATE(created_at) = ?
            AND tr_ccy IS NOT NULL
        GROUP BY tr_ccy
    ", [$today->toDateString()]);

        $yesterdayCurrencyAmounts = DB::select("
        SELECT
            tr_ccy as currency,
            SUM(tr_amount) as total_amount
        FROM decta_transactions
        WHERE DATE(created_at) = ?
            AND tr_ccy IS NOT NULL
        GROUP BY tr_ccy
    ", [$yesterday->toDateString()]);

        $mainStats = $stats[0] ?? (object)[];
        $todayData = $todayStats[0] ?? (object)[];
        $yesterdayData = $yesterdayStats[0] ?? (object)[];

        // Calculate approval rate
        $totalWithStatus = ($mainStats->approved_transactions ?? 0) + ($mainStats->declined_transactions ?? 0);
        $approvalRate = $totalWithStatus > 0
            ? round((($mainStats->approved_transactions ?? 0) / $totalWithStatus) * 100, 2)
            : 0;

        // Format currency breakdown
        $amountsByCurrency = array_map(function ($row) {
            return [
                'currency' => $row->currency,
                'total_amount' => ($row->total_amount ?? 0) / 100,
                'matched_amount' => ($row->matched_amount ?? 0) / 100,
                'approved_amount' => ($row->approved_amount ?? 0) / 100,
                'declined_amount' => ($row->declined_amount ?? 0) / 100,
                'transaction_count' => $row->transaction_count
            ];
        }, $currencyBreakdown);

        // Format today's currency amounts
        $todayAmountsByCurrency = [];
        foreach ($todayCurrencyAmounts as $row) {
            $todayAmountsByCurrency[$row->currency] = ($row->total_amount ?? 0) / 100;
        }

        // Format yesterday's currency amounts
        $yesterdayAmountsByCurrency = [];
        foreach ($yesterdayCurrencyAmounts as $row) {
            $yesterdayAmountsByCurrency[$row->currency] = ($row->total_amount ?? 0) / 100;
        }

        // Determine display strategy for amounts
        $isMultiCurrency = ($mainStats->unique_currencies ?? 0) > 1;

        if (!$isMultiCurrency && !empty($amountsByCurrency)) {
            // Single currency - show it directly
            $primaryCurrency = $amountsByCurrency[0]['currency'];
            $displayAmount = $amountsByCurrency[0]['total_amount'];
            $todayDisplayAmount = $todayAmountsByCurrency[$primaryCurrency] ?? 0;
            $yesterdayDisplayAmount = $yesterdayAmountsByCurrency[$primaryCurrency] ?? 0;
            $displayCurrency = $primaryCurrency;
        } else if ($isMultiCurrency) {
            // Multi-currency - don't show a total amount, show a breakdown instead
            $primaryCurrency = !empty($amountsByCurrency) ? $amountsByCurrency[0]['currency'] : 'EUR';
            $displayAmount = null; // Don't show mixed currency total
            $todayDisplayAmount = null;
            $yesterdayDisplayAmount = null;
            $displayCurrency = 'Multi';
        } else {
            // No data
            $primaryCurrency = 'EUR';
            $displayAmount = 0;
            $todayDisplayAmount = 0;
            $yesterdayDisplayAmount = 0;
            $displayCurrency = 'EUR';
        }

        return [
            'total_transactions' => $mainStats->total_transactions ?? 0,
            'matched_transactions' => $mainStats->matched_transactions ?? 0,
            'unmatched_transactions' => $mainStats->unmatched_transactions ?? 0,
            'failed_transactions' => $mainStats->failed_transactions ?? 0,
            'approved_transactions' => $mainStats->approved_transactions ?? 0,
            'declined_transactions' => $mainStats->declined_transactions ?? 0,
            'approval_rate' => $approvalRate,
            'match_rate' => $mainStats->total_transactions > 0
                ? round(($mainStats->matched_transactions / $mainStats->total_transactions) * 100, 2)
                : 0,

            // Currency information
            'unique_currencies' => $mainStats->unique_currencies ?? 0,
            'is_multi_currency' => $isMultiCurrency,
            'primary_currency' => $primaryCurrency,
            'display_currency' => $displayCurrency,

            // Display amounts (null for multi-currency)
            'total_amount' => $displayAmount,
            'today_amount' => $todayDisplayAmount,
            'yesterday_amount' => $yesterdayDisplayAmount,

            // Detailed currency breakdown
            'amounts_by_currency' => $amountsByCurrency,
            'today_amounts_by_currency' => $todayAmountsByCurrency,
            'yesterday_amounts_by_currency' => $yesterdayAmountsByCurrency,

            'unique_merchants' => $mainStats->unique_merchants ?? 0,
            'today_transactions' => $todayData->today_transactions ?? 0,
            'today_matched' => $todayData->today_matched ?? 0,
            'today_unmatched' => $todayData->today_unmatched ?? 0,
            'today_approved' => $todayData->today_approved ?? 0,
            'today_declined' => $todayData->today_declined ?? 0,
            'yesterday_transactions' => $yesterdayData->yesterday_transactions ?? 0,
            'yesterday_unmatched' => $yesterdayData->yesterday_unmatched ?? 0,
            'yesterday_approved' => $yesterdayData->yesterday_approved ?? 0,
            'yesterday_declined' => $yesterdayData->yesterday_declined ?? 0,
            'period_days' => 30
        ];
    }
    /**
     * Debug method to understand unmatched transactions discrepancy
     */
    public function debugUnmatchedTransactions(): array
    {
        $lastMonth = Carbon::now()->subDays(30);

        // Count from summary stats query (last 30 days, includes failed)
        $summaryCount = DB::select("
        SELECT
            COUNT(*) FILTER (WHERE is_matched = false) as unmatched_all,
            COUNT(*) FILTER (WHERE is_matched = false AND status != 'failed') as unmatched_non_failed,
            COUNT(*) FILTER (WHERE is_matched = false AND status = 'failed') as unmatched_failed
        FROM decta_transactions
        WHERE created_at >= ?
    ", [$lastMonth]);

        // Count from current unmatched list query (all time, excludes failed)
        $listCount = DB::select("
        SELECT
            COUNT(*) as unmatched_all_time,
            COUNT(*) FILTER (WHERE created_at >= ?) as unmatched_last_30_days
        FROM decta_transactions
        WHERE is_matched = false AND status != 'failed'
    ", [$lastMonth]);

        // Sample of unmatched records
        $samples = DB::select("
        SELECT
            payment_id,
            is_matched,
            status,
            created_at,
            tr_date_time
        FROM decta_transactions
        WHERE is_matched = false
        ORDER BY created_at DESC
        LIMIT 10
    ");

        return [
            'summary_stats' => $summaryCount[0] ?? null,
            'list_stats' => $listCount[0] ?? null,
            'sample_records' => $samples,
            'explanation' => [
                'summary_unmatched_count' => 'Counts from last 30 days, includes failed transactions',
                'list_unmatched_count' => 'Shows all time, excludes failed transactions',
                'discrepancy_reason' => 'Different date filters and failed transaction handling'
            ]
        ];
    }
    /**
     * Get declined transactions with filters
     */
    public function getDeclinedTransactions(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $whereConditions = [
            "(gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED')"
        ];
        $params = [];

        if (!empty($filters['merchant_id'])) {
            $whereConditions[] = 'merchant_id = ?';
            $params[] = $filters['merchant_id'];
        }

        if (!empty($filters['currency'])) {
            $whereConditions[] = 'tr_ccy = ?';
            $params[] = $filters['currency'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'tr_date_time >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['amount_min'])) {
            $whereConditions[] = 'tr_amount >= ?';
            $params[] = $filters['amount_min'] * 100; // Convert to cents
        }

        if (!empty($filters['amount_max'])) {
            $whereConditions[] = 'tr_amount <= ?';
            $params[] = $filters['amount_max'] * 100; // Convert to cents
        }

        $whereClause = implode(' AND ', $whereConditions);
        $params[] = $limit;
        $params[] = $offset;

        $query = "
            SELECT
                id,
                payment_id,
                tr_date_time,
                tr_amount,
                tr_ccy,
                merchant_name,
                merchant_id,
                tr_approval_id,
                tr_ret_ref_nr,
                gateway_transaction_status,
                gateway_transaction_id,
                gateway_trx_id,
                gateway_account_id,
                error_message,
                created_at
            FROM decta_transactions
            WHERE {$whereClause}
            ORDER BY tr_date_time DESC
            LIMIT ? OFFSET ?
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'id' => $row->id,
                'payment_id' => $row->payment_id,
                'transaction_date' => $row->tr_date_time,
                'amount' => $row->tr_amount ? $row->tr_amount / 100 : 0,
                'currency' => $row->tr_ccy,
                'merchant_name' => $row->merchant_name,
                'merchant_id' => $row->merchant_id,
                'approval_id' => $row->tr_approval_id,
                'return_reference' => $row->tr_ret_ref_nr,
                'gateway_status' => $row->gateway_transaction_status,
                'gateway_transaction_id' => $row->gateway_transaction_id,
                'gateway_trx_id' => $row->gateway_trx_id,
                'gateway_account_id' => $row->gateway_account_id,
                'error_message' => $row->error_message,
                'created_at' => $row->created_at
            ];
        }, $results);
    }
    /**
     * Get approval/decline trends for the last N days
     */
    public function getApprovalTrends(int $days = 7): array
    {
        $query = "
            SELECT
                DATE(tr_date_time) as date,
                COUNT(*) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as approved,
                COUNT(*) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as declined,
                COUNT(*) FILTER (WHERE gateway_transaction_status IS NOT NULL AND gateway_transaction_status != '') as total_with_status
            FROM decta_transactions
            WHERE tr_date_time >= CURRENT_DATE - INTERVAL '{$days} days'
            GROUP BY DATE(tr_date_time)
            ORDER BY date
        ";

        $results = DB::select($query);

        return array_map(function ($row) {
            $totalWithStatus = $row->total_with_status;
            $approvalRate = $totalWithStatus > 0 ? round(($row->approved / $totalWithStatus) * 100, 2) : 0;

            return [
                'date' => $row->date,
                'approved' => $row->approved,
                'declined' => $row->declined,
                'total_with_status' => $totalWithStatus,
                'approval_rate' => $approvalRate
            ];
        }, $results);
    }
    /**
     * Get decline reasons summary
     */
    public function getDeclineReasons(int $days = 30): array
    {
        $query = "
            SELECT
                error_message,
                COUNT(*) as count,
                SUM(tr_amount) as total_amount
            FROM decta_transactions
            WHERE (gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED')
                AND tr_date_time >= CURRENT_DATE - INTERVAL '{$days} days'
                AND error_message IS NOT NULL
                AND error_message != ''
            GROUP BY error_message
            ORDER BY count DESC
            LIMIT 10
        ";

        $results = DB::select($query);

        return array_map(function ($row) {
            return [
                'reason' => $row->error_message,
                'count' => $row->count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0
            ];
        }, $results);
    }
    /**
     * Generate reports based on type and filters
     */
    public function generateReport(string $reportType, array $filters): array
    {
        switch ($reportType) {
            case 'transactions':
                return $this->getTransactionReport($filters);
            case 'settlements':
                return $this->getSettlementReport($filters);
            case 'matching':
                return $this->getMatchingReport($filters);
            case 'daily_summary':
                return $this->getDailySummaryReport($filters);
            case 'merchant_breakdown':
                return $this->getMerchantBreakdownReport($filters);
            case 'scheme':
                return $this->getSchemeReport($filters);
            case 'declined_transactions':
                return $this->getDeclinedTransactionsReport($filters);
            case 'approval_analysis':
                return $this->getApprovalAnalysisReport($filters);
            case 'volume_breakdown':
                return $this->getVolumeBreakdownReport($filters);
            default:
                throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }
    }
    /**
     * Get declined transactions report
     */
    protected function getDeclinedTransactionsReport(array $filters): array
    {
        return $this->getDeclinedTransactions($filters, 1000, 0);
    }
    /**
     * Get approval analysis report
     */
    protected function getApprovalAnalysisReport(array $filters): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'tr_date_time >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['merchant_id'])) {
            $whereConditions[] = 'gateway_account_id = (SELECT account_id FROM merchants WHERE id = ?)';
            $params[] = $filters['merchant_id'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "
            SELECT
                merchant_name,
                merchant_id,
                COUNT(*) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as approved_count,
                COUNT(*) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as declined_count,
                SUM(tr_amount) FILTER (WHERE gateway_transaction_status = 'approved' OR gateway_transaction_status = 'APPROVED') as approved_amount,
                SUM(tr_amount) FILTER (WHERE gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED') as declined_amount,
                COUNT(*) FILTER (WHERE gateway_transaction_status IS NOT NULL AND gateway_transaction_status != '') as total_with_status
            FROM decta_transactions
            WHERE {$whereClause}
                AND merchant_id IS NOT NULL
            GROUP BY merchant_id, merchant_name
            ORDER BY total_with_status DESC
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            $totalWithStatus = $row->total_with_status;
            $approvalRate = $totalWithStatus > 0
                ? round(($row->approved_count / $totalWithStatus) * 100, 2)
                : 0;

            return [
                'merchant_id' => $row->merchant_id,
                'merchant_name' => $row->merchant_name,
                'approved_count' => $row->approved_count,
                'declined_count' => $row->declined_count,
                'approved_amount' => $row->approved_amount ? $row->approved_amount / 100 : 0,
                'declined_amount' => $row->declined_amount ? $row->declined_amount / 100 : 0,
                'total_with_status' => $totalWithStatus,
                'approval_rate' => $approvalRate
            ];
        }, $results);
    }
    /**
     * Get scheme report - grouped by card type, transaction type, currency, and merchant
     * Fixed for PostgreSQL GROUP BY requirements
     */
    protected function getSchemeReport(array $filters): array
    {
        $whereConditions = ['1=1'];
        $params = [];
        $joins = [];

        // Build dynamic WHERE conditions with proper parameter binding
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'dt.tr_date_time >= ?::timestamp';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'dt.tr_date_time <= ?::timestamp';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Handle merchant filtering
        if (!empty($filters['merchant_id'])) {
            $joins[] = 'LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id';
            $whereConditions[] = 'm.id = ?';
            $params[] = (int)$filters['merchant_id'];
        }

        if (!empty($filters['currency'])) {
            $whereConditions[] = 'dt.tr_ccy = ?';
            $params[] = $filters['currency'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'matched') {
                $whereConditions[] = 'dt.is_matched = true';
            } elseif ($filters['status'] === 'pending') {
                $whereConditions[] = 'dt.is_matched = false AND dt.status != \'failed\'';
            } else {
                $whereConditions[] = 'dt.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['amount_min'])) {
            $whereConditions[] = 'dt.tr_amount >= ?';
            $params[] = (int)($filters['amount_min'] * 100); // Convert to cents
        }

        if (!empty($filters['amount_max'])) {
            $whereConditions[] = 'dt.tr_amount <= ?';
            $params[] = (int)($filters['amount_max'] * 100); // Convert to cents
        }

        $whereClause = implode(' AND ', $whereConditions);
        $joinClause = implode(' ', $joins);

        // Updated query with proper fee calculation from user_define_field2
        $query = "
    WITH grouped_data AS (
        SELECT
            COALESCE(dt.card_type_name, 'Unknown') as card_type,
            COALESCE(dt.tr_type, 'Unknown') as transaction_type,
            COALESCE(dt.tr_ccy, 'Unknown') as currency,
            COALESCE(dt.merchant_legal_name, dt.merchant_name, 'Unknown') as merchant_legal_name,
            -- Handle sales vs refunds: refunds (06) should be negative for net calculation
            CASE
                WHEN dt.tr_type = '06' THEN -dt.tr_amount
                ELSE dt.tr_amount
            END as adjusted_amount,
            dt.tr_amount as original_amount,
            dt.tr_type,
            -- Extract fee from user_define_field2, handle NULL and non-numeric values
            CASE
                WHEN dt.user_define_field2 IS NOT NULL
                     AND dt.user_define_field2 ~ '^-?[0-9]+\.?[0-9]*$'
                THEN dt.user_define_field2::numeric
                ELSE 0
            END as fee_amount
        FROM decta_transactions dt
        {$joinClause}
        WHERE {$whereClause}
          AND dt.card_type_name IS NOT NULL
          AND dt.tr_type IS NOT NULL
          AND dt.tr_ccy IS NOT NULL
    )
    SELECT
        card_type,
        transaction_type,
        currency,
        merchant_legal_name,
        -- For individual rows, show original amounts (positive for both sales and refunds)
        SUM(original_amount) as total_amount,
        COUNT(*) as transaction_count,
        -- For net calculations, use adjusted amounts (sales positive, refunds negative)
        SUM(adjusted_amount) as net_amount,
        -- Sum the actual fees from user_define_field2
        SUM(fee_amount) as total_fees
    FROM grouped_data
    GROUP BY
        card_type,
        transaction_type,
        currency,
        merchant_legal_name
    ORDER BY
        merchant_legal_name ASC,  -- First sort by Merchant Legal Name
        currency ASC,             -- Then sort by Currency
        card_type ASC,            -- Then by Card Type
        transaction_type ASC      -- Finally by Transaction Type
";

        try {
            $results = DB::select($query, $params);

            return array_map(function ($row) {
                return [
                    'card_type' => $row->card_type,
                    'transaction_type' => $row->transaction_type,
                    'transaction_type_name' => $row->transaction_type === '05' ? 'Sales' : ($row->transaction_type === '06' ? 'Refunds' : $row->transaction_type),
                    'currency' => $row->currency,
                    'amount' => $row->total_amount ? $row->total_amount / 100 : 0, // Original amount for display
                    'net_amount' => $row->net_amount ? $row->net_amount / 100 : 0, // Net amount (sales - refunds)
                    'count' => $row->transaction_count,
                    'fee' => $row->total_fees ? round($row->total_fees, 2) : 0, // Fees are already in base currency
                    'merchant_legal_name' => $row->merchant_legal_name
                ];
            }, $results);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Scheme report query failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'params' => $params,
                'filters' => $filters
            ]);

            // Return empty array on error
            return [];
        }
    }
    /**
     * Get detailed transaction report
     */
    protected function getTransactionReport(array $filters): array
    {
        $whereConditions = ['1=1'];
        $params = [];
        $joins = [];

        // Build dynamic WHERE conditions
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'dt.tr_date_time >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'dt.tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Handle merchant filtering - join with merchants table to get proper filtering
        if (!empty($filters['merchant_id'])) {
            $joins[] = 'LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id';
            $whereConditions[] = 'm.id = ?';
            $params[] = $filters['merchant_id'];
        }

        if (!empty($filters['currency'])) {
            $whereConditions[] = 'dt.tr_ccy = ?';
            $params[] = $filters['currency'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'matched') {
                $whereConditions[] = 'dt.is_matched = true';
            } elseif ($filters['status'] === 'pending') {
                $whereConditions[] = 'dt.is_matched = false AND dt.status != \'failed\'';
            } else {
                $whereConditions[] = 'dt.status = ?';
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['amount_min'])) {
            $whereConditions[] = 'dt.tr_amount >= ?';
            $params[] = $filters['amount_min'] * 100; // Convert to cents
        }

        if (!empty($filters['amount_max'])) {
            $whereConditions[] = 'dt.tr_amount <= ?';
            $params[] = $filters['amount_max'] * 100; // Convert to cents
        }

        $whereClause = implode(' AND ', $whereConditions);
        $joinClause = implode(' ', $joins);

        $query = "
            SELECT
                dt.payment_id,
                dt.tr_date_time,
                dt.tr_amount,
                dt.tr_ccy,
                dt.merchant_name,
                dt.merchant_id,
                dt.terminal_id,
                dt.card_type_name,
                dt.tr_type,
                dt.status,
                dt.is_matched,
                dt.matched_at,
                dt.error_message,
                dt.gateway_transaction_id,
                dt.gateway_account_id,
                dt.gateway_shop_id,
                df.filename,
                df.processed_at" .
            (!empty($filters['merchant_id']) ? ',
                m.name as merchant_db_name,
                m.legal_name as merchant_legal_name' : '') . "
            FROM decta_transactions dt
            LEFT JOIN decta_files df ON dt.decta_file_id = df.id
            {$joinClause}
            WHERE {$whereClause}
            ORDER BY dt.tr_date_time DESC
            LIMIT 1000
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'payment_id' => $row->payment_id,
                'transaction_date' => $row->tr_date_time,
                'amount' => $row->tr_amount ? $row->tr_amount / 100 : 0,
                'currency' => $row->tr_ccy,
                'merchant_name' => $row->merchant_name,
                'merchant_id' => $row->merchant_id,
                'merchant_db_name' => $row->merchant_db_name ?? null,
                'merchant_legal_name' => $row->merchant_legal_name ?? null,
                'terminal_id' => $row->terminal_id,
                'card_type' => $row->card_type_name,
                'transaction_type' => $row->tr_type,
                'status' => $row->status,
                'is_matched' => $row->is_matched,
                'matched_at' => $row->matched_at,
                'error_message' => $row->error_message,
                'gateway_info' => [
                    'transaction_id' => $row->gateway_transaction_id,
                    'account_id' => $row->gateway_account_id,
                    'shop_id' => $row->gateway_shop_id
                ],
                'file_info' => [
                    'filename' => $row->filename,
                    'processed_at' => $row->processed_at
                ]
            ];
        }, $results);
    }
    /**
     * Get daily summary report
     */
    protected function getDailySummaryReport(array $filters): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(tr_date_time) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(tr_date_time) <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "
            SELECT
                DATE(tr_date_time) as transaction_date,
                COUNT(*) as total_transactions,
                COUNT(*) FILTER (WHERE is_matched = true) as matched_count,
                COUNT(*) FILTER (WHERE is_matched = false) as unmatched_count,
                COUNT(*) FILTER (WHERE status = 'failed') as failed_count,
                SUM(tr_amount) as total_amount,
                SUM(tr_amount) FILTER (WHERE is_matched = true) as matched_amount,
                COUNT(DISTINCT merchant_id) as unique_merchants,
                AVG(tr_amount) as avg_transaction_amount
            FROM decta_transactions
            WHERE {$whereClause}
            GROUP BY DATE(tr_date_time)
            ORDER BY transaction_date DESC
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            $matchRate = $row->total_transactions > 0
                ? ($row->matched_count / $row->total_transactions) * 100
                : 0;

            return [
                'date' => $row->transaction_date,
                'total_transactions' => $row->total_transactions,
                'matched_count' => $row->matched_count,
                'unmatched_count' => $row->unmatched_count,
                'failed_count' => $row->failed_count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0,
                'matched_amount' => $row->matched_amount ? $row->matched_amount / 100 : 0,
                'unique_merchants' => $row->unique_merchants,
                'avg_transaction_amount' => $row->avg_transaction_amount ? $row->avg_transaction_amount / 100 : 0,
                'match_rate' => round($matchRate, 2)
            ];
        }, $results);
    }
    /**
     * Get merchant breakdown report
     */
    protected function getMerchantBreakdownReport(array $filters): array
    {
        $whereConditions = ['dt.merchant_id IS NOT NULL'];
        $params = [];
        $joins = ['LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id'];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'dt.tr_date_time >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'dt.tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Filter by specific merchant if provided
        if (!empty($filters['merchant_id'])) {
            $whereConditions[] = 'm.id = ?';
            $params[] = $filters['merchant_id'];
        }

        $whereClause = implode(' AND ', $whereConditions);
        $joinClause = implode(' ', $joins);

        $query = "
            SELECT
                dt.merchant_id,
                dt.merchant_name,
                m.name as merchant_db_name,
                m.legal_name as merchant_legal_name,
                m.account_id,
                COUNT(*) as total_transactions,
                COUNT(*) FILTER (WHERE dt.is_matched = true) as matched_transactions,
                COUNT(*) FILTER (WHERE dt.status = 'failed') as failed_transactions,
                SUM(dt.tr_amount) as total_amount,
                SUM(dt.tr_amount) FILTER (WHERE dt.is_matched = true) as matched_amount,
                AVG(dt.tr_amount) as avg_amount,
                MIN(dt.tr_date_time) as first_transaction,
                MAX(dt.tr_date_time) as last_transaction,
                COUNT(DISTINCT dt.tr_ccy) as currencies_used,
                COUNT(DISTINCT dt.terminal_id) as terminals_used
            FROM decta_transactions dt
            {$joinClause}
            WHERE {$whereClause}
            GROUP BY dt.merchant_id, dt.merchant_name, m.name, m.legal_name, m.account_id
            ORDER BY total_amount DESC
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            $matchRate = $row->total_transactions > 0
                ? ($row->matched_transactions / $row->total_transactions) * 100
                : 0;

            // Use the most appropriate merchant name
            $displayName = $row->merchant_db_name ?: $row->merchant_legal_name ?: $row->merchant_name;

            return [
                'merchant_id' => $row->merchant_id,
                'merchant_name' => $displayName,
                'merchant_account_id' => $row->account_id,
                'merchant_csv_name' => $row->merchant_name,
                'total_transactions' => $row->total_transactions,
                'matched_transactions' => $row->matched_transactions,
                'failed_transactions' => $row->failed_transactions,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0,
                'matched_amount' => $row->matched_amount ? $row->matched_amount / 100 : 0,
                'avg_amount' => $row->avg_amount ? $row->avg_amount / 100 : 0,
                'match_rate' => round($matchRate, 2),
                'first_transaction' => $row->first_transaction,
                'last_transaction' => $row->last_transaction,
                'currencies_used' => $row->currencies_used,
                'terminals_used' => $row->terminals_used
            ];
        }, $results);
    }
    /**
     * Get matching report (success/failure analysis)
     */
    protected function getMatchingReport(array $filters): array
    {
        $whereConditions = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $whereConditions);

        $query = "
            SELECT
                status,
                is_matched,
                COUNT(*) as count,
                AVG(EXTRACT(EPOCH FROM (matched_at - created_at))/60) FILTER (WHERE matched_at IS NOT NULL) as avg_matching_time_minutes,
                COUNT(*) FILTER (WHERE matching_attempts IS NOT NULL) as has_attempts
            FROM decta_transactions
            WHERE {$whereClause}
            GROUP BY status, is_matched
            ORDER BY count DESC
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'status' => $row->status,
                'is_matched' => $row->is_matched,
                'count' => $row->count,
                'avg_matching_time_minutes' => $row->avg_matching_time_minutes ? round($row->avg_matching_time_minutes, 2) : null,
                'has_matching_attempts' => $row->has_attempts
            ];
        }, $results);
    }
    /**
     * Get settlement report (placeholder)
     */
    protected function getSettlementReport(array $filters): array
    {
        return $this->getDailySummaryReport($filters);
    }
    /**
     * Get recent files
     */
    public function getRecentFiles(int $limit = 10): array
    {
        $query = "
            SELECT
                df.id,
                df.filename,
                df.status,
                df.created_at,
                df.processed_at,
                df.file_size,
                COUNT(dt.id) as transaction_count,
                COUNT(dt.id) FILTER (WHERE dt.is_matched = true) as matched_count
            FROM decta_files df
            LEFT JOIN decta_transactions dt ON df.id = dt.decta_file_id
            GROUP BY df.id, df.filename, df.status, df.created_at, df.processed_at, df.file_size
            ORDER BY df.created_at DESC
            LIMIT ?
        ";

        $results = DB::select($query, [$limit]);

        return array_map(function ($row) {
            return [
                'id' => $row->id,
                'filename' => $row->filename,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'processed_at' => $row->processed_at,
                'file_size' => $row->file_size,
                'transaction_count' => $row->transaction_count,
                'matched_count' => $row->matched_count,
                'match_rate' => $row->transaction_count > 0
                    ? round(($row->matched_count / $row->transaction_count) * 100, 2)
                    : 0
            ];
        }, $results);
    }
    /**
     * Get processing status overview
     */
    public function getProcessingStatus(): array
    {
        $query = "
            SELECT
                status,
                COUNT(*) as count
            FROM decta_files
            GROUP BY status
        ";

        $results = DB::select($query);

        return array_map(function ($row) {
            return [
                'status' => $row->status,
                'count' => $row->count
            ];
        }, $results);
    }
    /**
     * Get matching trends for the last N days
     */
    public function getMatchingTrends(int $days = 7): array
    {
        $query = "
            SELECT
                DATE(created_at) as date,
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE is_matched = true) as matched,
                COUNT(*) FILTER (WHERE status = 'failed') as failed
            FROM decta_transactions
            WHERE created_at >= CURRENT_DATE - INTERVAL '{$days} days'
            GROUP BY DATE(created_at)
            ORDER BY date
        ";

        $results = DB::select($query);

        return array_map(function ($row) {
            return [
                'date' => $row->date,
                'total' => $row->total,
                'matched' => $row->matched,
                'failed' => $row->failed,
                'match_rate' => $row->total > 0 ? round(($row->matched / $row->total) * 100, 2) : 0
            ];
        }, $results);
    }
    /**
     * Get top merchants by transaction volume
     */
    public function getTopMerchants(int $limit = 5): array
    {
        // Enhanced query that properly groups merchants by normalizing merchant identification
        $query = "
        WITH merchant_normalization AS (
            SELECT
                dt.merchant_id,
                dt.merchant_name,
                m.name as merchant_db_name,
                m.legal_name as merchant_legal_name,
                m.account_id,
                dt.tr_ccy as currency,

                -- Create a normalized merchant key for grouping
                COALESCE(
                    LOWER(TRIM(m.name)),
                    LOWER(TRIM(m.legal_name)),
                    LOWER(TRIM(dt.merchant_name)),
                    dt.merchant_id::text
                ) as merchant_key,

                -- Choose the best display name
                COALESCE(
                    m.name,
                    m.legal_name,
                    dt.merchant_name,
                    'Merchant ' || dt.merchant_id
                ) as display_name,

                COUNT(*) as transaction_count,
                SUM(dt.tr_amount) as total_amount
            FROM decta_transactions dt
            LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id
            WHERE dt.merchant_id IS NOT NULL
                AND dt.created_at >= CURRENT_DATE - INTERVAL '30 days'
                AND dt.tr_ccy IS NOT NULL
            GROUP BY
                dt.merchant_id, dt.merchant_name, m.name, m.legal_name,
                m.account_id, dt.tr_ccy, merchant_key, display_name
        ),
        merchant_aggregated AS (
            SELECT
                merchant_key,
                -- Take the most complete merchant info
                (array_agg(merchant_id ORDER BY
                    CASE WHEN merchant_db_name IS NOT NULL THEN 1 ELSE 2 END,
                    transaction_count DESC
                ))[1] as primary_merchant_id,
                (array_agg(display_name ORDER BY
                    CASE WHEN merchant_db_name IS NOT NULL THEN 1 ELSE 2 END,
                    transaction_count DESC
                ))[1] as merchant_name,
                (array_agg(merchant_db_name ORDER BY transaction_count DESC))[1] as merchant_db_name,
                (array_agg(merchant_legal_name ORDER BY transaction_count DESC))[1] as merchant_legal_name,
                (array_agg(account_id ORDER BY transaction_count DESC))[1] as account_id,

                SUM(transaction_count) as total_transactions,
                COUNT(DISTINCT currency) as currency_count,

                json_agg(
                    json_build_object(
                        'currency', currency,
                        'transaction_count', transaction_count,
                        'total_amount', total_amount
                    ) ORDER BY transaction_count DESC
                ) as currency_breakdown
            FROM merchant_normalization
            GROUP BY merchant_key
        )
        SELECT *
        FROM merchant_aggregated
        ORDER BY total_transactions DESC
        LIMIT ?
    ";

        try {
            $results = DB::select($query, [$limit]);

            return array_map(function ($row) {
                $displayName = $row->merchant_db_name ?: $row->merchant_legal_name ?: $row->merchant_name;
                $currencyBreakdown = json_decode($row->currency_breakdown, true);

                // Calculate percentages and format amounts
                $totalTransactions = $row->total_transactions;
                $currencySummary = array_map(function ($curr) use ($totalTransactions) {
                    return [
                        'currency' => $curr['currency'],
                        'transaction_count' => $curr['transaction_count'],
                        'total_amount' => $curr['total_amount'] / 100, // Convert from cents
                        'percentage' => round(($curr['transaction_count'] / $totalTransactions) * 100, 1)
                    ];
                }, $currencyBreakdown);

                // Get dominant currency (most transactions)
                $dominantCurrency = $currencySummary[0] ?? null;

                return [
                    'merchant_id' => $row->primary_merchant_id,
                    'merchant_name' => $displayName,
                    'merchant_account_id' => $row->account_id,
                    'total_transactions' => $row->total_transactions,
                    'currency_count' => $row->currency_count,
                    'is_multi_currency' => $row->currency_count > 1,

                    // Dominant currency info for main display
                    'dominant_currency' => $dominantCurrency['currency'] ?? 'N/A',
                    'dominant_currency_amount' => $dominantCurrency['total_amount'] ?? 0,
                    'dominant_currency_transactions' => $dominantCurrency['transaction_count'] ?? 0,

                    // Full breakdown for detailed view
                    'currency_breakdown' => $currencySummary,

                    // Display summary for the UI
                    'display_summary' => $this->formatCurrencyDisplaySummary($currencySummary, $row->currency_count)
                ];
            }, $results);

        } catch (\Exception $e) {
            \Log::error('Top merchants query failed, falling back to simple method', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);

            // Fallback to the simple method if the advanced query fails
            return $this->getTopMerchantsSimple($limit);
        }
    }    /**
     * Alternative simpler approach if the above is still complex
     */
    public function getTopMerchantsSimple(int $limit = 5): array
    {
        // First get top merchants with normalized grouping
        $topMerchantsQuery = "
        WITH merchant_groups AS (
            SELECT
                -- Create a normalized merchant key for grouping similar merchants
                COALESCE(
                    LOWER(TRIM(m.name)),
                    LOWER(TRIM(m.legal_name)),
                    LOWER(TRIM(dt.merchant_name)),
                    dt.merchant_id::text
                ) as merchant_key,

                -- Choose the best merchant identifiers
                (array_agg(dt.merchant_id ORDER BY
                    CASE WHEN m.name IS NOT NULL THEN 1 ELSE 2 END,
                    COUNT(*) DESC
                ))[1] as merchant_id,

                (array_agg(COALESCE(m.name, m.legal_name, dt.merchant_name, 'Merchant ' || dt.merchant_id) ORDER BY
                    CASE WHEN m.name IS NOT NULL THEN 1 ELSE 2 END,
                    COUNT(*) DESC
                ))[1] as merchant_name,

                (array_agg(m.name ORDER BY COUNT(*) DESC))[1] as merchant_db_name,
                (array_agg(m.legal_name ORDER BY COUNT(*) DESC))[1] as merchant_legal_name,
                (array_agg(m.account_id ORDER BY COUNT(*) DESC))[1] as account_id,

                SUM(1) as total_transactions,
                COUNT(DISTINCT dt.tr_ccy) as currency_count
            FROM decta_transactions dt
            LEFT JOIN merchants m ON dt.gateway_account_id = m.account_id
            WHERE dt.merchant_id IS NOT NULL
                AND dt.created_at >= CURRENT_DATE - INTERVAL '30 days'
                AND dt.tr_ccy IS NOT NULL
            GROUP BY merchant_key
        )
        SELECT *
        FROM merchant_groups
        ORDER BY total_transactions DESC
        LIMIT ?
    ";

        $topMerchants = DB::select($topMerchantsQuery, [$limit]);

        if (empty($topMerchants)) {
            return [];
        }

        // Get currency breakdown for these merchants
        $merchantIds = array_map(function ($merchant) {
            return $merchant->merchant_id;
        }, $topMerchants);

        $currencyBreakdownQuery = "
        SELECT
            dt.merchant_id,
            dt.tr_ccy as currency,
            COUNT(*) as transaction_count,
            SUM(dt.tr_amount) as total_amount
        FROM decta_transactions dt
        WHERE dt.merchant_id = ANY(?)
            AND dt.created_at >= CURRENT_DATE - INTERVAL '30 days'
            AND dt.tr_ccy IS NOT NULL
        GROUP BY dt.merchant_id, dt.tr_ccy
        ORDER BY dt.merchant_id, transaction_count DESC
    ";

        $currencyBreakdowns = DB::select($currencyBreakdownQuery, ['{' . implode(',', $merchantIds) . '}']);

        // Group currency breakdowns by merchant
        $currencyByMerchant = [];
        foreach ($currencyBreakdowns as $breakdown) {
            $merchantId = $breakdown->merchant_id;
            if (!isset($currencyByMerchant[$merchantId])) {
                $currencyByMerchant[$merchantId] = [];
            }
            $currencyByMerchant[$merchantId][] = [
                'currency' => $breakdown->currency,
                'transaction_count' => $breakdown->transaction_count,
                'total_amount' => $breakdown->total_amount / 100
            ];
        }

        // Combine the data
        return array_map(function ($merchant) use ($currencyByMerchant) {
            $displayName = $merchant->merchant_db_name ?: $merchant->merchant_legal_name ?: $merchant->merchant_name;
            $currencyBreakdown = $currencyByMerchant[$merchant->merchant_id] ?? [];

            // Calculate percentages
            foreach ($currencyBreakdown as &$currency) {
                $currency['percentage'] = round(($currency['transaction_count'] / $merchant->total_transactions) * 100, 1);
            }

            $dominantCurrency = $currencyBreakdown[0] ?? null;

            return [
                'merchant_id' => $merchant->merchant_id,
                'merchant_name' => $displayName,
                'merchant_account_id' => $merchant->account_id,
                'total_transactions' => $merchant->total_transactions,
                'currency_count' => $merchant->currency_count,
                'is_multi_currency' => $merchant->currency_count > 1,

                // Dominant currency info for main display
                'dominant_currency' => $dominantCurrency['currency'] ?? 'N/A',
                'dominant_currency_amount' => $dominantCurrency['total_amount'] ?? 0,
                'dominant_currency_transactions' => $dominantCurrency['transaction_count'] ?? 0,

                // Full breakdown for detailed view
                'currency_breakdown' => $currencyBreakdown,

                // Display summary
                'display_summary' => $this->formatCurrencyDisplaySummary($currencyBreakdown, $merchant->currency_count)
            ];
        }, $topMerchants);
    }
    private function formatCurrencyDisplaySummary(array $currencies, int $currencyCount): string
    {
        if (empty($currencies)) {
            return 'No data';
        }

        if ($currencyCount === 1) {
            $curr = $currencies[0];
            return number_format($curr['total_amount'], 2) . ' ' . $curr['currency'];
        }

        // Multi-currency: show top 2 currencies
        $summary = [];
        for ($i = 0; $i < min(2, count($currencies)); $i++) {
            $curr = $currencies[$i];
            $summary[] = number_format($curr['total_amount'], 0) . ' ' . $curr['currency'];
        }

        if ($currencyCount > 2) {
            $summary[] = '+' . ($currencyCount - 2) . ' more';
        }

        return implode(', ', $summary);
    }
    /**
     * Get currency breakdown
     */
    public function getCurrencyBreakdown(): array
    {
        $query = "
            SELECT
                tr_ccy as currency,
                COUNT(*) as transaction_count,
                SUM(tr_amount) as total_amount,
                COUNT(*) FILTER (WHERE is_matched = true) as matched_count
            FROM decta_transactions
            WHERE tr_ccy IS NOT NULL
                AND created_at >= CURRENT_DATE - INTERVAL '30 days'
            GROUP BY tr_ccy
            ORDER BY total_amount DESC
        ";

        $results = DB::select($query);

        return array_map(function ($row) {
            return [
                'currency' => $row->currency,
                'transaction_count' => $row->transaction_count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0,
                'matched_count' => $row->matched_count,
                'match_rate' => $row->transaction_count > 0
                    ? round(($row->matched_count / $row->transaction_count) * 100, 2)
                    : 0
            ];
        }, $results);
    }
    /**
     * Get transaction details by payment ID
     */
    public function getTransactionDetails(string $paymentId): ?array
    {
        $query = "
            SELECT
                dt.*,
                df.filename,
                df.processed_at as file_processed_at,
                df.status as file_status
            FROM decta_transactions dt
            JOIN decta_files df ON dt.decta_file_id = df.id
            WHERE dt.payment_id = ?
        ";

        $result = DB::select($query, [$paymentId]);

        if (empty($result)) {
            return null;
        }

        $row = $result[0];

        return [
            'payment_id' => $row->payment_id,
            'transaction_details' => [
                'amount' => $row->tr_amount ? $row->tr_amount / 100 : 0,
                'currency' => $row->tr_ccy,
                'date_time' => $row->tr_date_time,
                'type' => $row->tr_type,
                'approval_id' => $row->tr_approval_id,
                'return_reference' => $row->tr_ret_ref_nr
            ],
            'merchant_details' => [
                'id' => $row->merchant_id,
                'name' => $row->merchant_name,
                'legal_name' => $row->merchant_legal_name,
                'terminal_id' => $row->terminal_id,
                'iban' => $row->merchant_iban_code,
                'country' => $row->merchant_country
            ],
            'card_details' => [
                'masked_number' => $row->card,
                'type' => $row->card_type_name,
                'product_type' => $row->card_product_type,
                'product_class' => $row->card_product_class
            ],
            'matching_status' => [
                'is_matched' => $row->is_matched,
                'matched_at' => $row->matched_at,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'attempts' => json_decode($row->matching_attempts, true)
            ],
            'gateway_info' => [
                'transaction_id' => $row->gateway_transaction_id,
                'account_id' => $row->gateway_account_id,
                'shop_id' => $row->gateway_shop_id,
                'trx_id' => $row->gateway_trx_id,
                'transaction_date' => $row->gateway_transaction_date,
                'bank_response_date' => $row->gateway_bank_response_date,
                'status' => $row->gateway_transaction_status
            ],
            'file_info' => [
                'filename' => $row->filename,
                'processed_at' => $row->file_processed_at,
                'status' => $row->file_status
            ]
        ];
    }
    /**
     * Get unmatched transactions with filters
     */
    public function getUnmatchedTransactions(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $whereConditions = ['is_matched = false'];
        $params = [];

        // Apply default 30-day filter if no date filters provided
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $lastMonth = Carbon::now()->subDays(60);
            $whereConditions[] = 'created_at >= ?';
            $params[] = $lastMonth;
        }

        if (!empty($filters['merchant_id'])) {
            $whereConditions[] = 'merchant_id = ?';
            $params[] = $filters['merchant_id'];
        }

        if (!empty($filters['currency'])) {
            $whereConditions[] = 'tr_ccy = ?';
            $params[] = $filters['currency'];
        }

        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'tr_date_time >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $whereConditions);
        $params[] = $limit;
        $params[] = $offset;

        $query = "
        SELECT
            payment_id,
            tr_date_time,
            tr_amount,
            tr_ccy,
            merchant_name,
            merchant_id,
            tr_approval_id,
            tr_ret_ref_nr,
            matching_attempts,
            created_at
        FROM decta_transactions
        WHERE {$whereClause}
        ORDER BY tr_date_time DESC
        LIMIT ? OFFSET ?
    ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'payment_id' => $row->payment_id,
                'transaction_date' => $row->tr_date_time,
                'amount' => $row->tr_amount ? $row->tr_amount / 100 : 0,
                'currency' => $row->tr_ccy,
                'merchant_name' => $row->merchant_name,
                'merchant_id' => $row->merchant_id,
                'approval_id' => $row->tr_approval_id,
                'return_reference' => $row->tr_ret_ref_nr,
                'attempts' => json_decode($row->matching_attempts, true),
                'created_at' => $row->created_at
            ];
        }, $results);
    }
    public function getCardVolumeBreakdownByRegion(array $filters = []): array
    {
        $whereConditions = ['dt.tr_amount IS NOT NULL', 'dt.tr_amount > 0'];
        $params = [];

        // Apply date filters
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'dt.tr_date_time >= ?::timestamp';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'dt.tr_date_time <= ?::timestamp';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Apply merchant filter if provided
        if (!empty($filters['merchant_id'])) {
            $whereConditions[] = 'EXISTS (SELECT 1 FROM merchants m WHERE dt.gateway_account_id = m.account_id AND m.id = ?)';
            $params[] = (int)$filters['merchant_id'];
        }

        // Apply currency filter if provided
        if (!empty($filters['currency'])) {
            $whereConditions[] = 'dt.tr_ccy = ?';
            $params[] = $filters['currency'];
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Define EU countries list with 3-letter ISO codes
        $euCountries = [
            'AUT', 'BEL', 'BGR', 'HRV', 'CYP', 'CZE', 'DNK', 'EST', 'FIN', 'FRA',
            'DEU', 'GRC', 'HUN', 'IRL', 'ITA', 'LVA', 'LTU', 'LUX', 'MLT', 'NLD',
            'POL', 'PRT', 'ROU', 'SVK', 'SVN', 'ESP', 'SWE'
        ];

        $euCountriesString = "'" . implode("','", $euCountries) . "'";

        $query = "
        WITH enhanced_breakdown AS (
            SELECT
                -- Continent classification using 3-letter country codes
                CASE
                    WHEN dt.issuer_country IS NULL OR dt.issuer_country = '' THEN 'Unknown'
                    WHEN dt.issuer_country IN ({$euCountriesString}) THEN 'Europe'
                    ELSE 'Non-Europe'
                END as continent,

                -- Card Brand classification (from card_type_name)
                CASE
                    WHEN dt.card_type_name IS NULL OR dt.card_type_name = '' THEN 'Unknown Brand'
                    WHEN UPPER(dt.card_type_name) LIKE '%VISA%' OR UPPER(dt.card_type_name) LIKE '%VI%' THEN 'Visa'
                    WHEN UPPER(dt.card_type_name) LIKE '%MASTERCARD%' OR UPPER(dt.card_type_name) LIKE '%MC%' OR UPPER(dt.card_type_name) LIKE '%MASTER%' THEN 'Mastercard'
                    WHEN UPPER(dt.card_type_name) LIKE '%AMEX%' OR UPPER(dt.card_type_name) LIKE '%AMERICAN EXPRESS%' THEN 'American Express'
                    WHEN UPPER(dt.card_type_name) LIKE '%DINERS%' THEN 'Diners Club'
                    WHEN UPPER(dt.card_type_name) LIKE '%DISCOVER%' THEN 'Discover'
                    WHEN UPPER(dt.card_type_name) LIKE '%JCB%' THEN 'JCB'
                    WHEN UPPER(dt.card_type_name) LIKE '%UNION%' THEN 'UnionPay'
                    ELSE INITCAP(dt.card_type_name)
                END as card_brand,

                -- Card Type classification (from card_product_type)
                CASE
                    WHEN dt.card_product_type IS NULL OR dt.card_product_type = '' THEN 'Unknown Type'
                    WHEN LOWER(dt.card_product_type) LIKE '%personal%' OR LOWER(dt.card_product_type) LIKE '%consumer%' OR LOWER(dt.card_product_type) LIKE '%individual%' OR LOWER(dt.card_product_type) LIKE '%private%' THEN 'Personal'
                    WHEN LOWER(dt.card_product_type) LIKE '%commercial%' OR LOWER(dt.card_product_type) LIKE '%business%' OR LOWER(dt.card_product_type) LIKE '%corporate%' OR LOWER(dt.card_product_type) LIKE '%company%' THEN 'Commercial'
                    WHEN LOWER(dt.card_product_type) LIKE '%premium%' OR LOWER(dt.card_product_type) LIKE '%platinum%' OR LOWER(dt.card_product_type) LIKE '%gold%' THEN 'Premium Personal'
                    ELSE INITCAP(dt.card_product_type)
                END as card_type,

                dt.tr_amount,
                dt.tr_ccy,
                dt.issuer_country
            FROM decta_transactions dt
            WHERE {$whereClause}
        )
        SELECT
            continent,
            card_brand,
            card_type,
            SUM(tr_amount) as total_amount_cents,
            COUNT(*) as transaction_count,
            tr_ccy as currency,
            COUNT(DISTINCT issuer_country) as countries_count,
            array_agg(DISTINCT issuer_country) as countries
        FROM enhanced_breakdown
        GROUP BY continent, card_brand, card_type, tr_ccy
        ORDER BY continent, card_brand, card_type, tr_ccy
    ";

        try {
            $results = DB::select($query, $params);
            return $this->formatVolumeBreakdownResults($results);

        } catch (\Exception $e) {
            \Log::error('Enhanced card volume breakdown query failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'params' => $params,
                'filters' => $filters
            ]);

            // Return empty but properly structured data
            return $this->getEmptyVolumeBreakdownStructure();
        }
    }
    private function getEmptyVolumeBreakdownStructure(): array
    {
        return [
            'totals' => [
                'europe_cards_volume' => 0,
                'non_europe_cards_volume' => 0,
                'unknown_volume' => 0,
                'total_volume' => 0,
                'total_transactions' => 0
            ],
            'totals_by_currency' => [],
            'currency_totals' => [],
            'continent_breakdown' => [],
            'brand_summary' => [],
            'type_summary' => [],
            'grand_total_transactions' => 0,
            'generated_at' => now()->toISOString(),
            'has_data' => false,
            'message' => 'No data found for the selected criteria'
        ];
    }
    /**
     * Format the enhanced volume breakdown results with proper currency separation
     */
    private function formatVolumeBreakdownResults(array $results): array
    {
        $continentData = [];
        $brandSummary = [];
        $typeSummary = [];
        $currencyTotals = [];

        // Handle empty results
        if (empty($results)) {
            return $this->getEmptyVolumeBreakdownStructure();
        }

        // Group results by continent → card brand → card type → currency
        foreach ($results as $row) {
            $continent = $row->continent ?? 'Unknown';
            $cardBrand = $row->card_brand ?? 'Unknown Brand';
            $cardType = $row->card_type ?? 'Unknown Type';
            $currency = $row->currency ?: 'Unknown';
            $amountInBaseCurrency = ($row->total_amount_cents ?? 0) / 100;
            $transactionCount = $row->transaction_count ?? 0;
            $countriesCount = $row->countries_count ?? 0;

            // Skip if no meaningful data
            if ($transactionCount === 0 && $amountInBaseCurrency === 0) {
                continue;
            }

            // Initialize continent data structure
            if (!isset($continentData[$continent])) {
                $continentData[$continent] = [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'currencies' => [],
                    'card_brands' => [],
                    'countries_count' => 0
                ];
            }

            // Initialize card brand data structure
            if (!isset($continentData[$continent]['card_brands'][$cardBrand])) {
                $continentData[$continent]['card_brands'][$cardBrand] = [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'currencies' => [],
                    'card_types' => []
                ];
            }

            // Initialize card type data structure
            if (!isset($continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType])) {
                $continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType] = [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'currencies' => []
                ];
            }

            // Initialize currency structures
            if (!isset($continentData[$continent]['currencies'][$currency])) {
                $continentData[$continent]['currencies'][$currency] = 0;
            }
            if (!isset($continentData[$continent]['card_brands'][$cardBrand]['currencies'][$currency])) {
                $continentData[$continent]['card_brands'][$cardBrand]['currencies'][$currency] = 0;
            }
            if (!isset($continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType]['currencies'][$currency])) {
                $continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType]['currencies'][$currency] = 0;
            }

            // Accumulate data
            $continentData[$continent]['total_transactions'] += $transactionCount;
            $continentData[$continent]['total_amount'] += $amountInBaseCurrency;
            $continentData[$continent]['countries_count'] = max($continentData[$continent]['countries_count'], $countriesCount);

            $continentData[$continent]['card_brands'][$cardBrand]['total_transactions'] += $transactionCount;
            $continentData[$continent]['card_brands'][$cardBrand]['total_amount'] += $amountInBaseCurrency;
            $continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType]['total_transactions'] += $transactionCount;
            $continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType]['total_amount'] += $amountInBaseCurrency;

            // Update currency amounts
            $continentData[$continent]['currencies'][$currency] += $amountInBaseCurrency;
            $continentData[$continent]['card_brands'][$cardBrand]['currencies'][$currency] += $amountInBaseCurrency;
            $continentData[$continent]['card_brands'][$cardBrand]['card_types'][$cardType]['currencies'][$currency] += $amountInBaseCurrency;

            // Update brand summary by currency
            if (!isset($brandSummary[$cardBrand])) {
                $brandSummary[$cardBrand] = [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'europe_amount' => 0,
                    'non_europe_amount' => 0,
                    'unknown_amount' => 0,
                    'currencies' => []
                ];
            }
            if (!isset($brandSummary[$cardBrand]['currencies'][$currency])) {
                $brandSummary[$cardBrand]['currencies'][$currency] = [
                    'europe_amount' => 0,
                    'non_europe_amount' => 0,
                    'unknown_amount' => 0,
                    'total_amount' => 0
                ];
            }

            $brandSummary[$cardBrand]['total_transactions'] += $transactionCount;
            $brandSummary[$cardBrand]['total_amount'] += $amountInBaseCurrency;
            $brandSummary[$cardBrand]['currencies'][$currency]['total_amount'] += $amountInBaseCurrency;

            switch ($continent) {
                case 'Europe':
                    $brandSummary[$cardBrand]['europe_amount'] += $amountInBaseCurrency;
                    $brandSummary[$cardBrand]['currencies'][$currency]['europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Non-Europe':
                    $brandSummary[$cardBrand]['non_europe_amount'] += $amountInBaseCurrency;
                    $brandSummary[$cardBrand]['currencies'][$currency]['non_europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Unknown':
                    $brandSummary[$cardBrand]['unknown_amount'] += $amountInBaseCurrency;
                    $brandSummary[$cardBrand]['currencies'][$currency]['unknown_amount'] += $amountInBaseCurrency;
                    break;
            }

            // Update type summary by currency
            if (!isset($typeSummary[$cardType])) {
                $typeSummary[$cardType] = [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'europe_amount' => 0,
                    'non_europe_amount' => 0,
                    'unknown_amount' => 0,
                    'currencies' => []
                ];
            }
            if (!isset($typeSummary[$cardType]['currencies'][$currency])) {
                $typeSummary[$cardType]['currencies'][$currency] = [
                    'europe_amount' => 0,
                    'non_europe_amount' => 0,
                    'unknown_amount' => 0,
                    'total_amount' => 0
                ];
            }

            $typeSummary[$cardType]['total_transactions'] += $transactionCount;
            $typeSummary[$cardType]['total_amount'] += $amountInBaseCurrency;
            $typeSummary[$cardType]['currencies'][$currency]['total_amount'] += $amountInBaseCurrency;

            switch ($continent) {
                case 'Europe':
                    $typeSummary[$cardType]['europe_amount'] += $amountInBaseCurrency;
                    $typeSummary[$cardType]['currencies'][$currency]['europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Non-Europe':
                    $typeSummary[$cardType]['non_europe_amount'] += $amountInBaseCurrency;
                    $typeSummary[$cardType]['currencies'][$currency]['non_europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Unknown':
                    $typeSummary[$cardType]['unknown_amount'] += $amountInBaseCurrency;
                    $typeSummary[$cardType]['currencies'][$currency]['unknown_amount'] += $amountInBaseCurrency;
                    break;
            }

            // Update currency totals
            if (!isset($currencyTotals[$currency])) {
                $currencyTotals[$currency] = [
                    'europe_amount' => 0,
                    'non_europe_amount' => 0,
                    'unknown_amount' => 0,
                    'total_amount' => 0,
                    'total_transactions' => 0
                ];
            }

            $currencyTotals[$currency]['total_amount'] += $amountInBaseCurrency;
            $currencyTotals[$currency]['total_transactions'] += $transactionCount;

            switch ($continent) {
                case 'Europe':
                    $currencyTotals[$currency]['europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Non-Europe':
                    $currencyTotals[$currency]['non_europe_amount'] += $amountInBaseCurrency;
                    break;
                case 'Unknown':
                    $currencyTotals[$currency]['unknown_amount'] += $amountInBaseCurrency;
                    break;
            }
        }

        // If after processing we still have no meaningful data, return empty structure
        if (empty($currencyTotals)) {
            return $this->getEmptyVolumeBreakdownStructure();
        }

        // Calculate overall totals across all currencies
        $grandTotals = [
            'europe_cards_volume' => 0,
            'non_europe_cards_volume' => 0,
            'unknown_volume' => 0,
            'total_volume' => 0,
            'total_transactions' => 0
        ];

        foreach ($currencyTotals as $currency => $data) {
            $grandTotals['europe_cards_volume'] += $data['europe_amount'];
            $grandTotals['non_europe_cards_volume'] += $data['non_europe_amount'];
            $grandTotals['unknown_volume'] += $data['unknown_amount'];
            $grandTotals['total_volume'] += $data['total_amount'];
            $grandTotals['total_transactions'] += $data['total_transactions'];
        }

        return [
            'totals' => $grandTotals,
            'totals_by_currency' => $currencyTotals,
            'currency_totals' => $currencyTotals,
            'continent_breakdown' => $continentData,
            'brand_summary' => $brandSummary,
            'type_summary' => $typeSummary,
            'grand_total_transactions' => $grandTotals['total_transactions'],
            'generated_at' => now()->toISOString(),
            'has_data' => true
        ];
    }
    /**
     * Get detailed card volume summary with regional and card type breakdowns
     */
    public function getDetailedVolumeBreakdown(array $filters = []): array
    {
        try {
            $volumeData = $this->getCardVolumeBreakdownByRegion($filters);

            // If no data found, return early with proper structure
            if (!$volumeData['has_data']) {
                return [
                    'summary' => [
                        'overview' => [
                            'total_volume' => 0,
                            'europe_percentage' => 0,
                            'non_europe_percentage' => 0,
                        ],
                        'continent_details' => [],
                        'brand_analysis' => [],
                        'type_analysis' => []
                    ],
                    'totals' => $volumeData['totals'],
                    'currency_totals' => $volumeData['currency_totals'],
                    'continent_breakdown' => $volumeData['continent_breakdown'],
                    'brand_summary' => $volumeData['brand_summary'],
                    'type_summary' => $volumeData['type_summary'],
                    'has_data' => false,
                    'message' => $volumeData['message']
                ];
            }

            // Build summary using the corrected data structure
            $totals = $volumeData['totals'];

            $summary = [
                'overview' => [
                    'total_volume' => $totals['total_volume'],
                    'europe_percentage' => $totals['total_volume'] > 0
                        ? round(($totals['europe_cards_volume'] / $totals['total_volume']) * 100, 2)
                        : 0,
                    'non_europe_percentage' => $totals['total_volume'] > 0
                        ? round(($totals['non_europe_cards_volume'] / $totals['total_volume']) * 100, 2)
                        : 0,
                ],
                'continent_details' => [],
                'brand_analysis' => [],
                'type_analysis' => []
            ];

            // Build continent details
            foreach ($volumeData['continent_breakdown'] as $continent => $data) {
                $continentSummary = [
                    'continent' => $continent,
                    'total_volume' => $data['total_amount'],
                    'total_transactions' => $data['total_transactions'],
                    'percentage_of_total' => $totals['total_volume'] > 0
                        ? round(($data['total_amount'] / $totals['total_volume']) * 100, 2)
                        : 0,
                    'card_brand_breakdown' => [],
                    'currency_breakdown' => $data['currencies']
                ];

                foreach ($data['card_brands'] as $cardBrand => $brandData) {
                    $brandSummary = [
                        'brand' => $cardBrand,
                        'volume' => $brandData['total_amount'],
                        'transactions' => $brandData['total_transactions'],
                        'percentage_of_continent' => $data['total_amount'] > 0
                            ? round(($brandData['total_amount'] / $data['total_amount']) * 100, 2)
                            : 0,
                        'card_type_breakdown' => [],
                        'currencies' => $brandData['currencies']
                    ];

                    foreach ($brandData['card_types'] as $cardType => $typeData) {
                        $brandSummary['card_type_breakdown'][$cardType] = [
                            'type' => $cardType,
                            'volume' => $typeData['total_amount'],
                            'transactions' => $typeData['total_transactions'],
                            'percentage_of_brand' => $brandData['total_amount'] > 0
                                ? round(($typeData['total_amount'] / $brandData['total_amount']) * 100, 2)
                                : 0,
                            'currencies' => $typeData['currencies']
                        ];
                    }

                    $continentSummary['card_brand_breakdown'][$cardBrand] = $brandSummary;
                }

                $summary['continent_details'][$continent] = $continentSummary;
            }

            // Build brand analysis
            foreach ($volumeData['brand_summary'] as $brand => $brandData) {
                $summary['brand_analysis'][$brand] = [
                    'brand' => $brand,
                    'total_volume' => $brandData['total_amount'],
                    'total_transactions' => $brandData['total_transactions'],
                    'europe_volume' => $brandData['europe_amount'],
                    'non_europe_volume' => $brandData['non_europe_amount'],
                    'europe_percentage' => $brandData['total_amount'] > 0
                        ? round(($brandData['europe_amount'] / $brandData['total_amount']) * 100, 2)
                        : 0,
                    'percentage_of_total' => $totals['total_volume'] > 0
                        ? round(($brandData['total_amount'] / $totals['total_volume']) * 100, 2)
                        : 0
                ];
            }

            // Build type analysis
            foreach ($volumeData['type_summary'] as $type => $typeData) {
                $summary['type_analysis'][$type] = [
                    'type' => $type,
                    'total_volume' => $typeData['total_amount'],
                    'total_transactions' => $typeData['total_transactions'],
                    'europe_volume' => $typeData['europe_amount'],
                    'non_europe_volume' => $typeData['non_europe_amount'],
                    'europe_percentage' => $typeData['total_amount'] > 0
                        ? round(($typeData['europe_amount'] / $typeData['total_amount']) * 100, 2)
                        : 0,
                    'percentage_of_total' => $totals['total_volume'] > 0
                        ? round(($typeData['total_amount'] / $totals['total_volume']) * 100, 2)
                        : 0
                ];
            }

            return array_merge($volumeData, ['summary' => $summary]);

        } catch (\Exception $e) {
            \Log::error('Detailed volume breakdown failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return $this->getEmptyVolumeBreakdownStructure();
        }
    }
    protected function getVolumeBreakdownReport(array $filters): array
    {
        return $this->getDetailedVolumeBreakdown($filters);
    }

}

