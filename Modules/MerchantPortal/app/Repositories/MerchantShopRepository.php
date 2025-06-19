<?php

namespace Modules\MerchantPortal\Repositories;

use App\Models\Shop;
use Modules\Decta\Models\DectaTransaction;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class MerchantShopRepository
{
    protected Shop $model;

    public function __construct(Shop $model)
    {
        $this->model = $model;
    }

    public function getByMerchant(int $merchantId): Collection
    {
        return $this->model->where('merchant_id', $merchantId)
            ->with(['settings'])
            ->get()
            ->map(function ($shop) {
                // Add computed attributes
                $shop->monthly_volume = $this->getMonthlyVolume($shop->id);
                $shop->success_rate = $this->getSuccessRate($shop->id);
                $shop->total_transactions = $this->getTotalTransactions($shop->id);
                $shop->total_volume = $this->getTotalVolume($shop->id);
                $shop->average_transaction = $this->getAverageTransaction($shop->id);
                return $shop;
            });
    }

    public function findByIdAndMerchant(int $id, int $merchantId): ?Shop
    {
        $shop = $this->model->where('id', $id)
            ->where('merchant_id', $merchantId)
            ->with(['settings', 'rollingReserves'])
            ->first();

        if ($shop) {
            // Add computed statistics
            $shop->total_transactions = $this->getTotalTransactions($shop->id);
            $shop->total_volume = $this->getTotalVolume($shop->id);
            $shop->success_rate = $this->getSuccessRate($shop->id);
            $shop->average_transaction = $this->getAverageTransaction($shop->id);
            $shop->last_transaction_at = $this->getLastTransactionDate($shop->id);

            // Load recent transactions from DectaTransaction
            $shop->recentTransactions = DectaTransaction::where('gateway_shop_id', $shop->id)
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
        return $this->model->where('merchant_id', $merchantId)->count();
    }

    public function getTopPerformingByMerchant(int $merchantId): ?Shop
    {
        $shops = $this->model->where('merchant_id', $merchantId)->get();

        $topShop = null;
        $highestVolume = 0;

        foreach ($shops as $shop) {
            $volume = $this->getMonthlyVolume($shop->id);
            if ($volume > $highestVolume) {
                $highestVolume = $volume;
                $topShop = $shop;
            }
        }

        if ($topShop) {
            $topShop->monthly_volume = $highestVolume;
        }

        return $topShop;
    }

    private function getMonthlyVolume(int $shopId): float
    {
        $totalCents = DectaTransaction::where('gateway_shop_id', $shopId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->whereMonth('tr_date_time', Carbon::now()->month)
            ->whereYear('tr_date_time', Carbon::now()->year)
            ->sum('tr_amount') ?? 0;

        return $totalCents / 100;
    }

    private function getTotalVolume(int $shopId): float
    {
        $totalCents = DectaTransaction::where('gateway_shop_id', $shopId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->sum('tr_amount') ?? 0;

        return $totalCents / 100;
    }

    private function getTotalTransactions(int $shopId): int
    {
        return DectaTransaction::where('gateway_shop_id', $shopId)->count();
    }

    private function getSuccessRate(int $shopId): float
    {
        $total = DectaTransaction::where('gateway_shop_id', $shopId)->count();

        if ($total === 0) {
            return 0;
        }

        $successful = DectaTransaction::where('gateway_shop_id', $shopId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->count();

        return ($successful / $total) * 100;
    }

    private function getAverageTransaction(int $shopId): float
    {
        $avgCents = DectaTransaction::where('gateway_shop_id', $shopId)
            ->where('status', DectaTransaction::STATUS_MATCHED)
            ->avg('tr_amount') ?? 0;

        return $avgCents / 100;
    }

    private function getLastTransactionDate(int $shopId): ?Carbon
    {
        $lastTransaction = DectaTransaction::where('gateway_shop_id', $shopId)
            ->orderBy('tr_date_time', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->tr_date_time : null;
    }
}
