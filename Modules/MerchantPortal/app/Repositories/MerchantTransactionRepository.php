<?php

namespace Modules\MerchantPortal\Repositories;

use Modules\Decta\Models\DectaTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MerchantTransactionRepository
{
    protected DectaTransaction $model;

    public function __construct(DectaTransaction $model)
    {
        $this->model = $model;
    }

    public function getByMerchantWithFilters(int $merchantId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->where('merchant_id', $merchantId)
            ->with(['shop:id,shop_id,email,website,owner_name,active']) // Only load needed fields
            ->orderBy('tr_date_time', 'desc');

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('tr_date_time', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('tr_date_time', '<=', $filters['date_to']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['shop_id'])) {
            $query->where('gateway_shop_id', $filters['shop_id']);
        }

        if (!empty($filters['amount_min'])) {
            $query->where('tr_amount', '>=', $filters['amount_min'] * 100);
        }

        if (!empty($filters['amount_max'])) {
            $query->where('tr_amount', '<=', $filters['amount_max'] * 100);
        }

        if (!empty($filters['payment_id'])) {
            $query->where('payment_id', 'like', '%' . $filters['payment_id'] . '%');
        }

        if (!empty($filters['merchant_name'])) {
            $query->where('merchant_name', 'like', '%' . $filters['merchant_name'] . '%');
        }

        if (!empty($filters['card_type'])) {
            $query->where('card_type_name', $filters['card_type']);
        }

        if (!empty($filters['currency'])) {
            $query->where('tr_ccy', $filters['currency']);
        }

        return $query->paginate($perPage);
    }

    public function findByIdAndMerchant(int $id, int $merchantId): ?DectaTransaction
    {
        return $this->model->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->with(['shop', 'dectaFile:id,filename,created_at'])
            ->first();
    }

    public function getRecentByMerchant(int $merchantId, int $limit = 10): Collection
    {
        return $this->model->where('merchant_id', $merchantId)
            ->with(['shop:id,shop_id,email,website,owner_name'])
            ->orderBy('tr_date_time', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countTodayByMerchant(int $merchantId): int
    {
        return Cache::remember("merchant_transactions_today_{$merchantId}", 300, function () use ($merchantId) {
            return $this->model->where('merchant_id', $merchantId)
                ->whereDate('tr_date_time', Carbon::today())
                ->count();
        });
    }

    public function getMonthlyVolumeByMerchant(int $merchantId): float
    {
        $cacheKey = "merchant_monthly_volume_{$merchantId}_" . Carbon::now()->format('Y-m');

        return Cache::remember($cacheKey, 3600, function () use ($merchantId) {
            $totalCents = $this->model->where('merchant_id', $merchantId)
                ->whereRaw('EXTRACT(MONTH FROM tr_date_time) = ?', [Carbon::now()->month])
                ->whereRaw('EXTRACT(YEAR FROM tr_date_time) = ?', [Carbon::now()->year])
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->sum('tr_amount') ?? 0;

            return $totalCents / 100;
        });
    }

    public function getMonthlyStatsByMerchant(int $merchantId): array
    {
        $cacheKey = "merchant_monthly_stats_{$merchantId}_" . Carbon::now()->format('Y');

        return Cache::remember($cacheKey, 3600, function () use ($merchantId) {
// PostgreSQL compatible query
            $monthlyData = $this->model->where('merchant_id', $merchantId)
                ->whereRaw('EXTRACT(YEAR FROM tr_date_time) = ?', [Carbon::now()->year])
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->selectRaw('EXTRACT(MONTH FROM tr_date_time) as month, SUM(tr_amount) as volume, COUNT(*) as count')
                ->groupBy(DB::raw('EXTRACT(MONTH FROM tr_date_time)'))
                ->orderBy(DB::raw('EXTRACT(MONTH FROM tr_date_time)'))
                ->get();

            $chartData = array_fill(0, 12, 0);
            $totalVolume = 0;
            $totalCount = 0;

            foreach ($monthlyData as $data) {
                $chartData[$data->month - 1] = (float)($data->volume / 100);
                $totalVolume += $data->volume;
                $totalCount += $data->count;
            }

            return [
                'chart_data' => $chartData,
                'volume' => $totalVolume / 100,
                'count' => $totalCount,
            ];
        });
    }

    public function getSuccessRateByMerchant(int $merchantId): float
    {
        return Cache::remember("merchant_success_rate_{$merchantId}", 1800, function () use ($merchantId) {
            $total = $this->model->where('merchant_id', $merchantId)->count();

            if ($total === 0) {
                return 0;
            }

            $successful = $this->model->where('merchant_id', $merchantId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->count();

            return ($successful / $total) * 100;
        });
    }

    public function getAverageAmountByMerchant(int $merchantId): float
    {
        return Cache::remember("merchant_avg_amount_{$merchantId}", 1800, function () use ($merchantId) {
            $avgCents = $this->model->where('merchant_id', $merchantId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->avg('tr_amount') ?? 0;

            return $avgCents / 100;
        });
    }

    public function getTransactionsByPaymentType(int $merchantId): array
    {
        return Cache::remember("merchant_payment_types_{$merchantId}", 3600, function () use ($merchantId) {
            return $this->model->where('merchant_id', $merchantId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->selectRaw('card_type_name, COUNT(*) as count, SUM(tr_amount) as total_amount')
                ->whereNotNull('card_type_name')
                ->groupBy('card_type_name')
                ->orderBy('count', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => $item->card_type_name,
                        'count' => $item->count,
                        'amount' => $item->total_amount / 100,
                        'percentage' => 0, // Will be calculated in the service
                    ];
                })
                ->toArray();
        });
    }

    public function getTransactionsByCountry(int $merchantId): array
    {
        return Cache::remember("merchant_countries_{$merchantId}", 3600, function () use ($merchantId) {
            return $this->model->where('merchant_id', $merchantId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->selectRaw('issuer_country, COUNT(*) as count, SUM(tr_amount) as total_amount')
                ->whereNotNull('issuer_country')
                ->groupBy('issuer_country')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'country' => $item->issuer_country,
                        'country_name' => $this->getCountryName($item->issuer_country),
                        'count' => $item->count,
                        'amount' => $item->total_amount / 100,
                    ];
                })
                ->toArray();
        });
    }

    public function getDailyStatsByMerchant(int $merchantId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        return Cache::remember("merchant_daily_stats_{$merchantId}_{$days}", 1800, function () use ($merchantId, $startDate) {
            $dailyData = $this->model->where('merchant_id', $merchantId)
                ->where('tr_date_time', '>=', $startDate)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->selectRaw('DATE(tr_date_time) as date, SUM(tr_amount) as volume, COUNT(*) as count')
                ->groupBy(DB::raw('DATE(tr_date_time)'))
                ->orderBy(DB::raw('DATE(tr_date_time)'))
                ->get();

            $labels = [];
            $volumes = [];
            $counts = [];

            foreach ($dailyData as $data) {
                $labels[] = Carbon::parse($data->date)->format('M j');
                $volumes[] = (float)($data->volume / 100);
                $counts[] = $data->count;
            }

            return [
                'labels' => $labels,
                'volumes' => $volumes,
                'counts' => $counts,
            ];
        });
    }

    /**
     * Get comprehensive statistics for merchant
     */
    public function getComprehensiveStats(int $merchantId): array
    {
        return [
            'overview' => [
                'total_transactions' => $this->model->where('merchant_id', $merchantId)->count(),
                'successful_transactions' => $this->model->where('merchant_id', $merchantId)
                    ->where('status', DectaTransaction::STATUS_MATCHED)->count(),
                'failed_transactions' => $this->model->where('merchant_id', $merchantId)
                    ->where('status', DectaTransaction::STATUS_FAILED)->count(),
                'pending_transactions' => $this->model->where('merchant_id', $merchantId)
                    ->where('status', DectaTransaction::STATUS_PENDING)->count(),
            ],
            'volumes' => [
                'total_volume' => $this->getTotalVolumeByMerchant($merchantId),
                'monthly_volume' => $this->getMonthlyVolumeByMerchant($merchantId),
                'average_transaction' => $this->getAverageAmountByMerchant($merchantId),
            ],
            'performance' => [
                'success_rate' => $this->getSuccessRateByMerchant($merchantId),
                'transactions_today' => $this->countTodayByMerchant($merchantId),
            ],
        ];
    }

    private function getTotalVolumeByMerchant(int $merchantId): float
    {
        return Cache::remember("merchant_total_volume_{$merchantId}", 3600, function () use ($merchantId) {
            $totalCents = $this->model->where('merchant_id', $merchantId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->sum('tr_amount') ?? 0;

            return $totalCents / 100;
        });
    }

    private function getCountryName(string $countryCode): string
    {
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
        ];

        return $countries[$countryCode] ?? $countryCode;
    }

    /**
     * Clear cache for merchant
     */
    public function clearCacheForMerchant(int $merchantId): void
    {
        $patterns = [
            "merchant_transactions_today_{$merchantId}",
            "merchant_monthly_volume_{$merchantId}_*",
            "merchant_monthly_stats_{$merchantId}_*",
            "merchant_success_rate_{$merchantId}",
            "merchant_avg_amount_{$merchantId}",
            "merchant_payment_types_{$merchantId}",
            "merchant_countries_{$merchantId}",
            "merchant_daily_stats_{$merchantId}_*",
            "merchant_total_volume_{$merchantId}",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
// For patterns with wildcards, we'd need to implement cache tagging
// For now, we'll skip these or implement a simple solution
                continue;
            }
            Cache::forget($pattern);
        }
    }
}
