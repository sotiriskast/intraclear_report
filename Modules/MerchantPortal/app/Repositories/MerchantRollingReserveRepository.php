<?php

namespace Modules\MerchantPortal\Repositories;

use App\Models\RollingReserveEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class MerchantRollingReserveRepository
{
    protected RollingReserveEntry $model;

    public function __construct(RollingReserveEntry $model)
    {
        $this->model = $model;
    }

    public function getByMerchantWithFilters(int $merchantId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->where('merchant_id', $merchantId)
            ->with(['shop', 'merchant'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['currency'])) {
            $query->where('original_currency', $filters['currency']);
        }

        return $query->paginate($perPage);
    }

    public function getPendingByMerchant(int $merchantId): Collection
    {
        return $this->model->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->with(['shop'])
            ->orderBy('release_due_date', 'asc')
            ->get();
    }

    public function getTotalPendingByMerchant(int $merchantId): float
    {
        $totalCents = $this->model->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->sum('reserve_amount_eur') ?? 0;

        return $totalCents / 100;
    }

    public function getSummaryByMerchant(int $merchantId): array
    {
        // Get total reserved amount (all time)
        $totalReservedCents = $this->model->where('merchant_id', $merchantId)
            ->sum('reserve_amount_eur') ?? 0;

        // Get pending release amount
        $pendingReleaseCents = $this->model->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->sum('reserve_amount_eur') ?? 0;

        // Get released this month
        $releasedThisMonthCents = $this->model->where('merchant_id', $merchantId)
            ->where('status', 'released')
            ->whereMonth('released_at', Carbon::now()->month)
            ->whereYear('released_at', Carbon::now()->year)
            ->sum('reserve_amount_eur') ?? 0;

        // Get reserves by currency for pending
        $reservesByCurrency = $this->model->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->selectRaw('original_currency, SUM(original_amount) as total_original, SUM(reserve_amount_eur) as total_eur')
            ->groupBy('original_currency')
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->original_currency => [
                        'original_amount' => $item->total_original / 100,
                        'eur_amount' => $item->total_eur / 100,
                    ]
                ];
            })
            ->toArray();

        return [
            'total_reserved' => $totalReservedCents / 100,
            'pending_release' => $pendingReleaseCents / 100,
            'released_this_month' => $releasedThisMonthCents / 100,
            'reserves_by_currency' => $reservesByCurrency,
        ];
    }

    public function getTimelineByMerchant(int $merchantId): array
    {
        $reserves = $this->model->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->whereNotNull('release_due_date')
            ->selectRaw('DATE(release_due_date) as date, SUM(reserve_amount_eur) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();

        $labels = [];
        $data = [];

        foreach ($reserves as $reserve) {
            $labels[] = Carbon::parse($reserve->date)->format('M j');
            $data[] = (float) ($reserve->total_amount / 100);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
