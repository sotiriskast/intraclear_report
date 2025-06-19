<?php

namespace Modules\MerchantPortal\Repositories;
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
            ->whereHas('shop', function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
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

        return $query->paginate($perPage);
    }

    public function findByIdAndMerchant(int $id, int $merchantId): ?DectaTransaction
    {
        return $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->with(['shop', 'dectaFile'])
            ->find($id);
    }

    public function getRecentByMerchant(int $merchantId, int $limit = 10): Collection
    {
        return $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->with(['shop'])
            ->orderBy('tr_date_time', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countTodayByMerchant(int $merchantId): int
    {
        return $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->whereDate('tr_date_time', Carbon::today())
            ->count();
    }

    public function getMonthlyVolumeByMerchant(int $merchantId): float
    {
        $totalCents = $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->whereMonth('tr_date_time', Carbon::now()->month)
            ->whereYear('tr_date_time', Carbon::now()->year)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->sum('tr_amount') ?? 0;

        return $totalCents / 100;
    }

    public function getMonthlyStatsByMerchant(int $merchantId): array
    {
        $monthlyData = $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->whereYear('tr_date_time', Carbon::now()->year)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->selectRaw('MONTH(tr_date_time) as month, SUM(tr_amount) as volume, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
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
        $total = $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })->count();

        if ($total === 0) {
            return 0;
        }

        $successful = $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->count();

        return ($successful / $total) * 100;
    }

    public function getAverageAmountByMerchant(int $merchantId): float
    {
        $avgCents = $this->model->whereHas('shop', function ($query) use ($merchantId) {
            $query->where('merchant_id', $merchantId);
        })
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->avg('tr_amount') ?? 0;

        return $avgCents / 100;
    }
}
