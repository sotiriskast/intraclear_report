<?php

namespace Modules\MerchantPortal\Repositories;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

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
            ->with(['shop'])
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

        $results = $query->paginate($perPage);

        // Manually load shop data for each transaction
        $this->loadShopData($results->getCollection());

        return $results;
    }


    public function findByIdAndMerchant(int $id, int $merchantId): ?DectaTransaction
    {
        $transaction = $this->model->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->first();

        if ($transaction) {
            $this->loadShopData(collect([$transaction]));
        }

        return $transaction;
    }

    public function getRecentByMerchant(int $merchantId, int $limit = 10): Collection
    {
        return $this->model->where('merchant_id', $merchantId)
            ->with(['shop'])
            ->orderBy('tr_date_time', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countTodayByMerchant(int $merchantId): int
    {
        return $this->model->where('merchant_id', $merchantId)
            ->whereDate('tr_date_time', Carbon::today())
            ->count();
    }

    public function getMonthlyVolumeByMerchant(int $merchantId): float
    {
        $totalCents = $this->model->where('merchant_id', $merchantId)
            ->whereRaw('EXTRACT(MONTH FROM tr_date_time) = ?', [Carbon::now()->month])
            ->whereRaw('EXTRACT(YEAR FROM tr_date_time) = ?', [Carbon::now()->year])
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->sum('tr_amount') ?? 0;

        return $totalCents / 100;
    }

    public function getMonthlyStatsByMerchant(int $merchantId): array
    {
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
            $chartData[$data->month - 1] = (float) ($data->volume / 100);
            $totalVolume += $data->volume;
            $totalCount += $data->count;
        }

        return [
            'chart_data' => $chartData,
            'volume' => $totalVolume / 100,
            'count' => $totalCount,
        ];
    }

    public function getSuccessRateByMerchant(int $merchantId): float
    {
        $total = $this->model->where('merchant_id', $merchantId)->count();

        if ($total === 0) {
            return 0;
        }

        $successful = $this->model->where('merchant_id', $merchantId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->count();

        return ($successful / $total) * 100;
    }

    public function getAverageAmountByMerchant(int $merchantId): float
    {
        $avgCents = $this->model->where('merchant_id', $merchantId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->avg('tr_amount') ?? 0;

        return $avgCents / 100;
    }

    public function getTransactionsByPaymentType(int $merchantId): array
    {
        return $this->model->where('merchant_id', $merchantId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->selectRaw('card_type_name, COUNT(*) as count, SUM(tr_amount) as total_amount')
            ->groupBy('card_type_name')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->card_type_name,
                    'count' => $item->count,
                    'amount' => $item->total_amount / 100,
                ];
            })
            ->toArray();
    }

    public function getTransactionsByCountry(int $merchantId): array
    {
        return $this->model->where('merchant_id', $merchantId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->selectRaw('issuer_country, COUNT(*) as count, SUM(tr_amount) as total_amount')
            ->groupBy('issuer_country')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'country' => $item->issuer_country,
                    'count' => $item->count,
                    'amount' => $item->total_amount / 100,
                ];
            })
            ->toArray();
    }

    /**
     * Get daily transaction stats for the last 30 days
     */
    public function getDailyStatsByMerchant(int $merchantId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

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
            $volumes[] = (float) ($data->volume / 100);
            $counts[] = $data->count;
        }

        return [
            'labels' => $labels,
            'volumes' => $volumes,
            'counts' => $counts,
        ];
    }

    /**
     * Get transaction stats by hour for today
     */
    public function getHourlyStatsByMerchant(int $merchantId): array
    {
        $today = Carbon::today();

        $hourlyData = $this->model->where('merchant_id', $merchantId)
            ->whereDate('tr_date_time', $today)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->selectRaw('EXTRACT(HOUR FROM tr_date_time) as hour, SUM(tr_amount) as volume, COUNT(*) as count')
            ->groupBy(DB::raw('EXTRACT(HOUR FROM tr_date_time)'))
            ->orderBy(DB::raw('EXTRACT(HOUR FROM tr_date_time)'))
            ->get();

        $chartData = array_fill(0, 24, 0);
        $labels = [];

        for ($i = 0; $i < 24; $i++) {
            $labels[] = sprintf('%02d:00', $i);
        }

        foreach ($hourlyData as $data) {
            $chartData[$data->hour] = (float) ($data->volume / 100);
        }

        return [
            'labels' => $labels,
            'data' => $chartData,
        ];
    }

    /**
     * Load shop data for transactions
     */
    private function loadShopData(Collection $transactions): void
    {
        $shopIds = $transactions->pluck('gateway_shop_id')->filter()->unique();

        if ($shopIds->isEmpty()) {
            return;
        }

        $shops = Shop::whereIn('id', $shopIds)->get()->keyBy('id');

        $transactions->each(function ($transaction) use ($shops) {
            if ($transaction->gateway_shop_id && $shops->has($transaction->gateway_shop_id)) {
                $transaction->shop = $shops->get($transaction->gateway_shop_id);
            }
        });
    }
}
