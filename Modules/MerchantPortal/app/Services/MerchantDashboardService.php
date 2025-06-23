<?php

namespace Modules\MerchantPortal\Services;

use Modules\MerchantPortal\Repositories\MerchantShopRepository;
use Modules\MerchantPortal\Repositories\MerchantTransactionRepository;
use Modules\MerchantPortal\Repositories\MerchantRollingReserveRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MerchantDashboardService
{
    public function __construct(
        private MerchantShopRepository $shopRepository,
        private MerchantTransactionRepository $transactionRepository,
        private MerchantRollingReserveRepository $reserveRepository
    ) {}

    public function getDashboardData(int $merchantId): array
    {
        try {
            // Cache dashboard data for 5 minutes
            return Cache::remember("merchant_dashboard_{$merchantId}", 300, function () use ($merchantId) {
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
                    'comprehensive_stats' => $this->transactionRepository->getComprehensiveStats($merchantId),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard data for merchant', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return $this->getEmptyDashboardData();
        }
    }

    public function getOverviewData(int $merchantId): array
    {
        try {
            return Cache::remember("merchant_overview_{$merchantId}", 300, function () use ($merchantId) {
                return [
                    'total_shops' => $this->shopRepository->countByMerchant($merchantId),
                    'total_transactions_today' => $this->transactionRepository->countTodayByMerchant($merchantId),
                    'pending_reserves' => $this->reserveRepository->getTotalPendingByMerchant($merchantId),
                    'monthly_volume' => $this->transactionRepository->getMonthlyVolumeByMerchant($merchantId),
                    'success_rate' => $this->transactionRepository->getSuccessRateByMerchant($merchantId),
                    'average_transaction' => $this->transactionRepository->getAverageAmountByMerchant($merchantId),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get overview data for merchant', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return $this->getEmptyOverviewData();
        }
    }

    public function getAnalyticsData(int $merchantId): array
    {
        try {
            return Cache::remember("merchant_analytics_{$merchantId}", 1800, function () use ($merchantId) {
                return [
                    'payment_types' => $this->transactionRepository->getTransactionsByPaymentType($merchantId),
                    'countries' => $this->transactionRepository->getTransactionsByCountry($merchantId),
                    'daily_stats' => $this->transactionRepository->getDailyStatsByMerchant($merchantId, 30),
                    'monthly_stats' => $this->transactionRepository->getMonthlyStatsByMerchant($merchantId),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get analytics data for merchant', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    private function getStatistics(int $merchantId): array
    {
        return [
            'success_rate' => $this->transactionRepository->getSuccessRateByMerchant($merchantId),
            'average_transaction' => $this->transactionRepository->getAverageAmountByMerchant($merchantId),
            'top_performing_shop' => $this->shopRepository->getTopPerformingByMerchant($merchantId),
        ];
    }

    private function getEmptyDashboardData(): array
    {
        return [
            'shops' => collect([]),
            'recent_transactions' => collect([]),
            'rolling_reserves' => collect([]),
            'monthly_summary' => ['chart_data' => array_fill(0, 12, 0), 'volume' => 0, 'count' => 0],
            'statistics' => ['success_rate' => 0, 'average_transaction' => 0, 'top_performing_shop' => null],
            'comprehensive_stats' => [
                'overview' => ['total_transactions' => 0, 'successful_transactions' => 0, 'failed_transactions' => 0, 'pending_transactions' => 0],
                'volumes' => ['total_volume' => 0, 'monthly_volume' => 0, 'average_transaction' => 0],
                'performance' => ['success_rate' => 0, 'transactions_today' => 0],
            ],
        ];
    }

    private function getEmptyOverviewData(): array
    {
        return [
            'total_shops' => 0,
            'total_transactions_today' => 0,
            'pending_reserves' => 0,
            'monthly_volume' => 0,
            'success_rate' => 0,
            'average_transaction' => 0,
        ];
    }

    /**
     * Clear all cached data for merchant
     */
    public function clearMerchantCache(int $merchantId): void
    {
        $cacheKeys = [
            "merchant_dashboard_{$merchantId}",
            "merchant_overview_{$merchantId}",
            "merchant_analytics_{$merchantId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear repository caches
        $this->transactionRepository->clearCacheForMerchant($merchantId);
    }
}
