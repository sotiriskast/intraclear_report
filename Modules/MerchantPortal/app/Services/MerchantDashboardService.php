<?php

namespace Modules\MerchantPortal\Services;

use Modules\MerchantPortal\Repositories\MerchantShopRepository;
use Modules\MerchantPortal\Repositories\MerchantTransactionRepository;
use Modules\MerchantPortal\Repositories\MerchantRollingReserveRepository;

class MerchantDashboardService
{
    public function __construct(
        private MerchantShopRepository $shopRepository,
        private MerchantTransactionRepository $transactionRepository,
        private MerchantRollingReserveRepository $reserveRepository
    ) {}

    public function getDashboardData(int $merchantId): array
    {
        $recentTransactions = $this->transactionRepository->getRecentByMerchant($merchantId, 10);

        // Format transactions for display
        $recentTransactions->transform(function ($transaction) {
            $transaction->amount = $transaction->tr_amount / 100;
            $transaction->transaction_id = $transaction->payment_id;
            $transaction->created_at = $transaction->tr_date_time;
            return $transaction;
        });

        return [
            'shops' => $this->shopRepository->getByMerchant($merchantId),
            'recent_transactions' => $recentTransactions,
            'rolling_reserves' => $this->reserveRepository->getPendingByMerchant($merchantId),
            'monthly_summary' => $this->transactionRepository->getMonthlyStatsByMerchant($merchantId),
            'statistics' => $this->getStatistics($merchantId),
        ];
    }

    public function getOverviewData(int $merchantId): array
    {
        return [
            'total_shops' => $this->shopRepository->countByMerchant($merchantId),
            'total_transactions_today' => $this->transactionRepository->countTodayByMerchant($merchantId),
            'pending_reserves' => $this->reserveRepository->getTotalPendingByMerchant($merchantId),
            'monthly_volume' => $this->transactionRepository->getMonthlyVolumeByMerchant($merchantId),
        ];
    }

    private function getStatistics(int $merchantId): array
    {
        return [
            'success_rate' => $this->transactionRepository->getSuccessRateByMerchant($merchantId),
            'average_transaction' => $this->transactionRepository->getAverageAmountByMerchant($merchantId),
            'top_performing_shop' => $this->shopRepository->getTopPerformingByMerchant($merchantId),
        ];
    }
}
