<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DectaAnalyticsService
{
    /**
     * Analyze decline patterns and provide insights
     */
    public function analyzeDeclinePatterns(array $filters = []): array
    {
        $whereConditions = [
            "(gateway_transaction_status = 'declined' OR gateway_transaction_status = 'DECLINED')"
        ];
        $params = [];

        // Apply date filters
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'tr_date_time >= ?';
            $params[] = $filters['date_from'];
        } else {
            // Default to last 30 days
            $whereConditions[] = 'tr_date_time >= ?';
            $params[] = Carbon::now()->subDays(30)->toDateString();
        }

        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'tr_date_time <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get decline patterns by various dimensions
        $patterns = [
            'by_reason' => $this->getDeclinesByReason($whereClause, $params),
            'by_merchant' => $this->getDeclinesByMerchant($whereClause, $params),
            'by_card_type' => $this->getDeclinesByCardType($whereClause, $params),
            'by_amount_range' => $this->getDeclinesByAmountRange($whereClause, $params),
            'by_hour' => $this->getDeclinesByHour($whereClause, $params),
            'by_day_of_week' => $this->getDeclinesByDayOfWeek($whereClause, $params),
            'temporal_trends' => $this->getDeclineTemporalTrends($whereClause, $params)
        ];

        return [
            'patterns' => $patterns,
            'insights' => $this->generateInsights($patterns),
            'recommendations' => $this->generateRecommendations($patterns)
        ];
    }

    /**
     * Get declines grouped by reason
     */
    private function getDeclinesByReason(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                CASE
                    WHEN error_message IS NULL OR error_message = '' THEN 'No reason provided'
                    ELSE error_message
                END as decline_reason,
                COUNT(*) as count,
                SUM(tr_amount) as total_amount,
                AVG(tr_amount) as avg_amount,
                COUNT(DISTINCT merchant_id) as affected_merchants
            FROM decta_transactions
            WHERE {$whereClause}
            GROUP BY decline_reason
            ORDER BY count DESC
            LIMIT 20
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'reason' => $row->decline_reason,
                'count' => $row->count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0,
                'avg_amount' => $row->avg_amount ? $row->avg_amount / 100 : 0,
                'affected_merchants' => $row->affected_merchants
            ];
        }, $results);
    }

    /**
     * Get declines grouped by merchant
     */
    private function getDeclinesByMerchant(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                merchant_id,
                merchant_name,
                COUNT(*) as decline_count,
                SUM(tr_amount) as decline_amount,
                COUNT(DISTINCT DATE(tr_date_time)) as days_with_declines
            FROM decta_transactions
            WHERE {$whereClause}
                AND merchant_id IS NOT NULL
            GROUP BY merchant_id, merchant_name
            ORDER BY decline_count DESC
            LIMIT 15
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'merchant_id' => $row->merchant_id,
                'merchant_name' => $row->merchant_name,
                'decline_count' => $row->decline_count,
                'decline_amount' => $row->decline_amount ? $row->decline_amount / 100 : 0,
                'days_with_declines' => $row->days_with_declines
            ];
        }, $results);
    }

    /**
     * Get declines grouped by card type
     */
    private function getDeclinesByCardType(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                COALESCE(card_type_name, 'Unknown') as card_type,
                COUNT(*) as count,
                SUM(tr_amount) as total_amount,
                AVG(tr_amount) as avg_amount
            FROM decta_transactions
            WHERE {$whereClause}
            GROUP BY card_type_name
            ORDER BY count DESC
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'card_type' => $row->card_type,
                'count' => $row->count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0,
                'avg_amount' => $row->avg_amount ? $row->avg_amount / 100 : 0
            ];
        }, $results);
    }

    /**
     * Get declines grouped by amount ranges
     */
    private function getDeclinesByAmountRange(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                CASE
                    WHEN tr_amount IS NULL THEN 'Unknown'
                    WHEN tr_amount < 1000 THEN '€0-10'
                    WHEN tr_amount < 5000 THEN '€10-50'
                    WHEN tr_amount < 10000 THEN '€50-100'
                    WHEN tr_amount < 25000 THEN '€100-250'
                    WHEN tr_amount < 50000 THEN '€250-500'
                    WHEN tr_amount < 100000 THEN '€500-1000'
                    ELSE '€1000+'
                END as amount_range,
                COUNT(*) as count,
                SUM(tr_amount) as total_amount
            FROM decta_transactions
            WHERE {$whereClause}
            GROUP BY amount_range
            ORDER BY
                CASE amount_range
                    WHEN 'Unknown' THEN 0
                    WHEN '€0-10' THEN 1
                    WHEN '€10-50' THEN 2
                    WHEN '€50-100' THEN 3
                    WHEN '€100-250' THEN 4
                    WHEN '€250-500' THEN 5
                    WHEN '€500-1000' THEN 6
                    WHEN '€1000+' THEN 7
                END
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'amount_range' => $row->amount_range,
                'count' => $row->count,
                'total_amount' => $row->total_amount ? $row->total_amount / 100 : 0
            ];
        }, $results);
    }

    /**
     * Get declines by hour of day
     */
    private function getDeclinesByHour(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                EXTRACT(HOUR FROM tr_date_time) as hour,
                COUNT(*) as count
            FROM decta_transactions
            WHERE {$whereClause}
                AND tr_date_time IS NOT NULL
            GROUP BY EXTRACT(HOUR FROM tr_date_time)
            ORDER BY hour
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'hour' => $row->hour,
                'count' => $row->count
            ];
        }, $results);
    }

    /**
     * Get declines by day of week
     */
    private function getDeclinesByDayOfWeek(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                EXTRACT(DOW FROM tr_date_time) as day_of_week,
                COUNT(*) as count
            FROM decta_transactions
            WHERE {$whereClause}
                AND tr_date_time IS NOT NULL
            GROUP BY EXTRACT(DOW FROM tr_date_time)
            ORDER BY day_of_week
        ";

        $results = DB::select($query, $params);

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return array_map(function ($row) use ($dayNames) {
            return [
                'day_of_week' => $dayNames[$row->day_of_week] ?? 'Unknown',
                'day_number' => $row->day_of_week,
                'count' => $row->count
            ];
        }, $results);
    }

    /**
     * Get temporal trends for declines
     */
    private function getDeclineTemporalTrends(string $whereClause, array $params): array
    {
        $query = "
            SELECT
                DATE(tr_date_time) as date,
                COUNT(*) as decline_count,
                SUM(tr_amount) as decline_amount,
                COUNT(DISTINCT merchant_id) as affected_merchants
            FROM decta_transactions
            WHERE {$whereClause}
                AND tr_date_time IS NOT NULL
            GROUP BY DATE(tr_date_time)
            ORDER BY date DESC
            LIMIT 30
        ";

        $results = DB::select($query, $params);

        return array_map(function ($row) {
            return [
                'date' => $row->date,
                'decline_count' => $row->decline_count,
                'decline_amount' => $row->decline_amount ? $row->decline_amount / 100 : 0,
                'affected_merchants' => $row->affected_merchants
            ];
        }, $results);
    }

    /**
     * Generate insights based on patterns
     */
    private function generateInsights(array $patterns): array
    {
        $insights = [];

        // Top decline reason
        if (!empty($patterns['by_reason'])) {
            $topReason = $patterns['by_reason'][0];
            $insights[] = [
                'type' => 'top_decline_reason',
                'title' => 'Top Decline Reason',
                'message' => "'{$topReason['reason']}' accounts for {$topReason['count']} declines (€{$topReason['total_amount']})",
                'severity' => 'high'
            ];
        }

        // Merchant with most declines
        if (!empty($patterns['by_merchant'])) {
            $topMerchant = $patterns['by_merchant'][0];
            $insights[] = [
                'type' => 'problem_merchant',
                'title' => 'Merchant with Most Declines',
                'message' => "Merchant '{$topMerchant['merchant_name']}' has {$topMerchant['decline_count']} declines",
                'severity' => 'medium'
            ];
        }

        // Peak decline hours
        if (!empty($patterns['by_hour'])) {
            $hourCounts = collect($patterns['by_hour']);
            $peakHour = $hourCounts->sortByDesc('count')->first();
            $insights[] = [
                'type' => 'peak_hours',
                'title' => 'Peak Decline Hour',
                'message' => "Most declines occur at hour {$peakHour['hour']}:00 with {$peakHour['count']} incidents",
                'severity' => 'low'
            ];
        }

        // Trend analysis
        if (!empty($patterns['temporal_trends'])) {
            $trends = collect($patterns['temporal_trends']);
            $recent = $trends->take(7)->avg('decline_count');
            $older = $trends->skip(7)->take(7)->avg('decline_count');

            if ($recent > $older * 1.2) {
                $insights[] = [
                    'type' => 'increasing_trend',
                    'title' => 'Increasing Decline Trend',
                    'message' => 'Decline rate has increased by ' . round((($recent - $older) / $older) * 100, 1) . '% in recent days',
                    'severity' => 'high'
                ];
            }
        }

        return $insights;
    }

    /**
     * Generate recommendations based on patterns
     */
    private function generateRecommendations(array $patterns): array
    {
        $recommendations = [];

        // Check for common decline reasons that might be addressable
        if (!empty($patterns['by_reason'])) {
            foreach ($patterns['by_reason'] as $reason) {
                if (stripos($reason['reason'], 'insufficient funds') !== false) {
                    $recommendations[] = [
                        'type' => 'payment_method',
                        'title' => 'Insufficient Funds Issues',
                        'description' => 'Consider implementing retry logic for insufficient funds declines',
                        'priority' => 'medium'
                    ];
                }

                if (stripos($reason['reason'], 'expired') !== false) {
                    $recommendations[] = [
                        'type' => 'card_validation',
                        'title' => 'Expired Card Prevention',
                        'description' => 'Implement card expiry checks before processing',
                        'priority' => 'high'
                    ];
                }

                if (stripos($reason['reason'], 'fraud') !== false || stripos($reason['reason'], 'risk') !== false) {
                    $recommendations[] = [
                        'type' => 'fraud_prevention',
                        'title' => 'Fraud Risk Management',
                        'description' => 'Review fraud detection rules and thresholds',
                        'priority' => 'high'
                    ];
                }
            }
        }

        // Check for merchants with high decline rates
        if (!empty($patterns['by_merchant'])) {
            $highDeclineMerchants = array_filter($patterns['by_merchant'], function($merchant) {
                return $merchant['decline_count'] > 50; // Threshold for "high"
            });

            if (!empty($highDeclineMerchants)) {
                $recommendations[] = [
                    'type' => 'merchant_support',
                    'title' => 'High-Decline Merchant Support',
                    'description' => 'Provide targeted support to merchants with high decline rates',
                    'priority' => 'medium'
                ];
            }
        }

        // Check for amount-based patterns
        if (!empty($patterns['by_amount_range'])) {
            $highAmountDeclines = array_filter($patterns['by_amount_range'], function($range) {
                return in_array($range['amount_range'], ['€500-1000', '€1000+']) && $range['count'] > 10;
            });

            if (!empty($highAmountDeclines)) {
                $recommendations[] = [
                    'type' => 'high_value_processing',
                    'title' => 'High-Value Transaction Processing',
                    'description' => 'Consider special handling for high-value transactions',
                    'priority' => 'low'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get decline rate comparison between periods
     */
    public function getDeclineRateComparison(string $period1Start, string $period1End, string $period2Start, string $period2End): array
    {
        $query = "
            SELECT
                'period1' as period,
                COUNT(*) FILTER (WHERE gateway_transaction_status IN ('declined', 'DECLINED')) as declined_count,
                COUNT(*) FILTER (WHERE gateway_transaction_status IN ('approved', 'APPROVED')) as approved_count,
                COUNT(*) FILTER (WHERE gateway_transaction_status IS NOT NULL AND gateway_transaction_status != '') as total_count
            FROM decta_transactions
            WHERE tr_date_time BETWEEN ? AND ?

            UNION ALL

            SELECT
                'period2' as period,
                COUNT(*) FILTER (WHERE gateway_transaction_status IN ('declined', 'DECLINED')) as declined_count,
                COUNT(*) FILTER (WHERE gateway_transaction_status IN ('approved', 'APPROVED')) as approved_count,
                COUNT(*) FILTER (WHERE gateway_transaction_status IS NOT NULL AND gateway_transaction_status != '') as total_count
            FROM decta_transactions
            WHERE tr_date_time BETWEEN ? AND ?
        ";

        $results = DB::select($query, [$period1Start, $period1End, $period2Start, $period2End]);

        $comparison = [];
        foreach ($results as $result) {
            $declineRate = $result->total_count > 0 ? ($result->declined_count / $result->total_count) * 100 : 0;
            $comparison[$result->period] = [
                'declined_count' => $result->declined_count,
                'approved_count' => $result->approved_count,
                'total_count' => $result->total_count,
                'decline_rate' => round($declineRate, 2)
            ];
        }

        // Calculate change
        $period1Rate = $comparison['period1']['decline_rate'] ?? 0;
        $period2Rate = $comparison['period2']['decline_rate'] ?? 0;
        $change = $period1Rate - $period2Rate;
        $percentChange = $period2Rate > 0 ? (($period1Rate - $period2Rate) / $period2Rate) * 100 : 0;

        return [
            'period1' => $comparison['period1'] ?? null,
            'period2' => $comparison['period2'] ?? null,
            'change' => [
                'absolute' => round($change, 2),
                'percent' => round($percentChange, 2),
                'direction' => $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'no_change')
            ]
        ];
    }
}
