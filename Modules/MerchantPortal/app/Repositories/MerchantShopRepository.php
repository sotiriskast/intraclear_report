<?php
namespace Modules\MerchantPortal\Repositories;

use App\Models\Shop;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MerchantShopRepository
{
    protected Shop $model;

    public function __construct(Shop $model)
    {
        $this->model = $model;
    }

    public function getByMerchant(int $merchantId): Collection
    {
        $shops = $this->model->where('merchant_id', $merchantId)
            ->with(['settings:shop_id,rolling_reserve_percentage,mdr_percentage'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Load transaction statistics for all shops in one query
        $shopStats = $this->getShopsStatistics($shops->pluck('id')->toArray());

        return $shops->map(function ($shop) use ($shopStats) {
            $stats = $shopStats[$shop->id] ?? $this->getEmptyStats();

            $shop->monthly_volume = $stats['monthly_volume'];
            $shop->success_rate = $stats['success_rate'];
            $shop->total_transactions = $stats['total_transactions'];
            $shop->total_volume = $stats['total_volume'];
            $shop->average_transaction = $stats['average_transaction'];
            $shop->last_transaction_at = $stats['last_transaction_at'];

            return $shop;
        });
    }

    public function findByIdAndMerchant(int $id, int $merchantId): ?Shop
    {
        $shop = $this->model->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->with(['settings', 'rollingReserves' => function ($query) {
                $query->where('status', 'pending')->limit(5);
            }])
            ->first();

        if ($shop) {
            $stats = $this->getShopDetailedStats($shop->id);

            // Add computed statistics
            $shop->total_transactions = $stats['total_transactions'];
            $shop->total_volume = $stats['total_volume'];
            $shop->success_rate = $stats['success_rate'];
            $shop->average_transaction = $stats['average_transaction'];
            $shop->last_transaction_at = $stats['last_transaction_at'];

            // Load recent transactions with relationships
            $shop->recentTransactions = DectaTransaction::where('gateway_shop_id', $shop->id)
                ->with(['dectaFile:id,filename'])
                ->orderBy('tr_date_time', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($transaction) {
                    $transaction->amount = $transaction->tr_amount / 100;
                    $transaction->transaction_id = $transaction->payment_id;
                    $transaction->created_at = $transaction->tr_date_time;
                    return $transaction;
                });
        }

        return $shop;
    }

    public function countByMerchant(int $merchantId): int
    {
        return Cache::remember("merchant_shop_count_{$merchantId}", 3600, function () use ($merchantId) {
            return $this->model->where('merchant_id', $merchantId)->count();
        });
    }

    public function getTopPerformingByMerchant(int $merchantId): ?Shop
    {
        $cacheKey = "merchant_top_shop_{$merchantId}_" . Carbon::now()->format('Y-m');

        return Cache::remember($cacheKey, 3600, function () use ($merchantId) {
            // Get top performing shop based on monthly volume
            $topShopId = DectaTransaction::join('shops', 'decta_transactions.gateway_shop_id', '=', 'shops.id')
                ->where('shops.merchant_id', $merchantId)
                ->where('decta_transactions.status', DectaTransaction::STATUS_MATCHED)
                ->whereRaw('EXTRACT(MONTH FROM tr_date_time) = ?', [Carbon::now()->month])
                ->whereRaw('EXTRACT(YEAR FROM tr_date_time) = ?', [Carbon::now()->year])
                ->selectRaw('gateway_shop_id, SUM(tr_amount) as total_volume')
                ->groupBy('gateway_shop_id')
                ->orderBy('total_volume', 'desc')
                ->value('gateway_shop_id');

            if (!$topShopId) {
                return null;
            }

            $shop = $this->model->find($topShopId);

            if ($shop) {
                $shop->monthly_volume = $this->getMonthlyVolume($shop->id);
            }

            return $shop;
        });
    }

    /**
     * Get statistics for multiple shops at once (optimized)
     */
    private function getShopsStatistics(array $shopIds): array
    {
        if (empty($shopIds)) {
            return [];
        }

        $cacheKey = 'shops_stats_' . md5(implode(',', $shopIds));

        return Cache::remember($cacheKey, 1800, function () use ($shopIds) {
            // Get all statistics in one query
            $stats = DectaTransaction::whereIn('gateway_shop_id', $shopIds)
                ->selectRaw('
                    gateway_shop_id,
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = ? THEN tr_amount ELSE 0 END) as total_volume_cents,
                    SUM(CASE WHEN status = ? AND EXTRACT(MONTH FROM tr_date_time) = ? AND EXTRACT(YEAR FROM tr_date_time) = ? THEN tr_amount ELSE 0 END) as monthly_volume_cents,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_transactions,
                    AVG(CASE WHEN status = ? THEN tr_amount END) as avg_amount_cents,
                    MAX(tr_date_time) as last_transaction_at
                ', [
                    DectaTransaction::STATUS_MATCHED,
                    DectaTransaction::STATUS_MATCHED,
                    Carbon::now()->month,
                    Carbon::now()->year,
                    DectaTransaction::STATUS_MATCHED,
                    DectaTransaction::STATUS_MATCHED
                ])
                ->groupBy('gateway_shop_id')
                ->get()
                ->keyBy('gateway_shop_id');

            $result = [];
            foreach ($shopIds as $shopId) {
                $stat = $stats->get($shopId);

                if ($stat) {
                    $result[$shopId] = [
                        'total_transactions' => $stat->total_transactions,
                        'total_volume' => ($stat->total_volume_cents ?? 0) / 100,
                        'monthly_volume' => ($stat->monthly_volume_cents ?? 0) / 100,
                        'success_rate' => $stat->total_transactions > 0
                            ? ($stat->successful_transactions / $stat->total_transactions) * 100
                            : 0,
                        'average_transaction' => ($stat->avg_amount_cents ?? 0) / 100,
                        'last_transaction_at' => $stat->last_transaction_at ? Carbon::parse($stat->last_transaction_at) : null,
                    ];
                } else {
                    $result[$shopId] = $this->getEmptyStats();
                }
            }

            return $result;
        });
    }

    /**
     * Get detailed statistics for a single shop
     */
    private function getShopDetailedStats(int $shopId): array
    {
        return Cache::remember("shop_detailed_stats_{$shopId}", 1800, function () use ($shopId) {
            $stats = DectaTransaction::where('gateway_shop_id', $shopId)
                ->selectRaw('
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = ? THEN tr_amount ELSE 0 END) as total_volume_cents,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful_transactions,
                    AVG(CASE WHEN status = ? THEN tr_amount END) as avg_amount_cents,
                    MAX(tr_date_time) as last_transaction_at
                ', [
                    DectaTransaction::STATUS_MATCHED,
                    DectaTransaction::STATUS_MATCHED,
                    DectaTransaction::STATUS_MATCHED
                ])
                ->first();

            return [
                'total_transactions' => $stats->total_transactions ?? 0,
                'total_volume' => ($stats->total_volume_cents ?? 0) / 100,
                'success_rate' => $stats->total_transactions > 0
                    ? (($stats->successful_transactions ?? 0) / $stats->total_transactions) * 100
                    : 0,
                'average_transaction' => ($stats->avg_amount_cents ?? 0) / 100,
                'last_transaction_at' => $stats->last_transaction_at ? Carbon::parse($stats->last_transaction_at) : null,
            ];
        });
    }

    private function getEmptyStats(): array
    {
        return [
            'total_transactions' => 0,
            'total_volume' => 0,
            'monthly_volume' => 0,
            'success_rate' => 0,
            'average_transaction' => 0,
            'last_transaction_at' => null,
        ];
    }

    private function getMonthlyVolume(int $shopId): float
    {
        $cacheKey = "shop_monthly_volume_{$shopId}_" . Carbon::now()->format('Y-m');

        return Cache::remember($cacheKey, 3600, function () use ($shopId) {
            $totalCents = DectaTransaction::where('gateway_shop_id', $shopId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->whereRaw('EXTRACT(MONTH FROM tr_date_time) = ?', [Carbon::now()->month])
                ->whereRaw('EXTRACT(YEAR FROM tr_date_time) = ?', [Carbon::now()->year])
                ->sum('tr_amount') ?? 0;

            return $totalCents / 100;
        });
    }

    public function getShopPerformanceByMonth(int $shopId, int $months = 12): array
    {
        return Cache::remember("shop_performance_{$shopId}_{$months}", 3600, function () use ($shopId, $months) {
            $monthlyData = DectaTransaction::where('gateway_shop_id', $shopId)
                ->where('status', DectaTransaction::STATUS_MATCHED)
                ->where('tr_date_time', '>=', Carbon::now()->subMonths($months))
                ->selectRaw('EXTRACT(YEAR FROM tr_date_time) as year, EXTRACT(MONTH FROM tr_date_time) as month, SUM(tr_amount) as volume, COUNT(*) as count')
                ->groupBy(DB::raw('EXTRACT(YEAR FROM tr_date_time), EXTRACT(MONTH FROM tr_date_time)'))
                ->orderBy(DB::raw('EXTRACT(YEAR FROM tr_date_time), EXTRACT(MONTH FROM tr_date_time)'))
                ->get();

            $labels = [];
            $volumes = [];
            $counts = [];

            foreach ($monthlyData as $data) {
                $labels[] = Carbon::create($data->year, $data->month, 1)->format('M Y');
                $volumes[] = (float) ($data->volume / 100);
                $counts[] = $data->count;
            }

            return [
                'labels' => $labels,
                'volumes' => $volumes,
                'counts' => $counts,
            ];
        });
    }
}
