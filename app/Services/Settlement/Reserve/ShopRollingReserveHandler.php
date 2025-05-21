<?php

namespace App\Services\Settlement\Reserve;

use App\DTO\ReserveEntryData;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Repositories\ShopRepository;
use App\Repositories\ShopSettingRepository;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Handles the processing and management of rolling reserves for shop settlements
 */
class ShopRollingReserveHandler
{
    /**
     * Number of months to hold the reserve before release
     */
    private const RESERVE_PERIOD_MONTHS = 6;

    /**
     * Percentage of transaction amount to hold in reserve
     */
    private const RESERVE_PERCENTAGE = 10;

    public function __construct(
        private readonly RollingReserveRepositoryInterface $reserveRepository,
        private readonly DynamicLogger $logger,
        private readonly ShopRepository $shopRepository,
        private readonly ShopSettingRepository $shopSettingRepository
    ) {}

    /**
     * Process settlement reserves for a given shop and period
     */
    public function processShopSettlementReserve(
        int $shopId,
        array $transactionData,
        string $currency,
        array $dateRange
    ): array {
        try {
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end']);

            // Get shop settings
            $shopSettings = $this->shopSettingRepository->findByShop($shopId);
            $reservePercentage = $shopSettings
                ? ($shopSettings->rolling_reserve_percentage / 100)
                : self::RESERVE_PERCENTAGE;

            $reservePeriodMonths = $shopSettings
                ? ceil($shopSettings->holding_period_days / 30)
                : self::RESERVE_PERIOD_MONTHS;

            // Check and process releasable reserves for the current week
            $releasedReserves = $this->processReleasableReserves(
                $shopId,
                $currency,
                $startDate,
                $endDate
            );

            // Create new reserve if there are transactions and no existing reserve
            $newReserve = null;
            if (!empty($transactionData['total_sales']) && !$this->reserveExists($shopId, $currency, $dateRange)) {
                $reserveAmount = $transactionData['total_sales'] * ($reservePercentage / 100);
                $reserveAmountEur = $transactionData['total_sales_eur'] * ($reservePercentage / 100);

                $reserveData = new ReserveEntryData(
                    merchantId: $this->getMerchantIdFromShop($shopId),
                    originalAmount: (int) round($reserveAmount * 100),
                    originalCurrency: $currency,
                    reserveAmountEur: (int) round($reserveAmountEur * 100),
                    exchangeRate: $currency === 'EUR' ? 1.0 : $transactionData['exchange_rate'],
                    periodStart: Carbon::parse($dateRange['start']),
                    periodEnd: Carbon::parse($dateRange['end']),
                    releaseDueDate: Carbon::parse($dateRange['end'])->addMonths($reservePeriodMonths),
                );

                $newReserve = $this->createNewReserve($reserveData, $shopId);
            }

            return [
                'new_reserve' => $newReserve,
                'released_reserves' => $releasedReserves,
                'reserved_percentage' => $reservePercentage ?? 10,
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing shop rolling reserve', [
                'shop_id' => $shopId,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process and release reserves that have reached their release date for a shop
     */
    private function processReleasableReserves(
        int $shopId,
        string $currency,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        try {
            // Get all reserves that are due for release within this week's period for this shop
            $releasableReserves = DB::table('rolling_reserve_entries')
                ->where('shop_id', $shopId)
                ->where('status', 'pending')
                ->where('original_currency', $currency)
                ->where('release_due_date', '<=', $weekEnd->toDateString())
                ->whereNull('released_at')
                ->get();

            if ($releasableReserves->isNotEmpty()) {
                $entryIds = $releasableReserves->pluck('id')->toArray();
                $this->reserveRepository->markReserveAsReleased($entryIds);

                $this->logger->log('info', 'Released shop rolling reserves', [
                    'shop_id' => $shopId,
                    'currency' => $currency,
                    'count' => count($entryIds),
                    'week_period' => [
                        'start' => $weekStart->toDateString(),
                        'end' => $weekEnd->toDateString(),
                    ],
                ]);
            }

            return $releasableReserves->map(function ($reserve) {
                return array_merge((array) $reserve, [
                    'original_amount' => $reserve->original_amount / 100,
                    'reserve_amount_eur' => $reserve->reserve_amount_eur / 100,
                ]);
            })->toArray();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing releasable shop reserves', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Create a new rolling reserve entry for a shop transaction period
     */
    private function createNewReserve(ReserveEntryData $reserveData, int $shopId): RollingReserveEntry
    {
        $this->logger->log('info', 'Creating new shop reserve entry', [
            'shop_id' => $shopId,
            'amount' => $reserveData->originalAmount / 100,
            'amount_eur' => $reserveData->reserveAmountEur / 100,
            'currency' => $reserveData->originalCurrency,
            'exchange_rate' => $reserveData->exchangeRate,
            'release_due_date' => $reserveData->releaseDueDate->toDateString(),
        ]);

        // Add shop_id to the data
        $dataArray = $reserveData->toArray();
        $dataArray['shop_id'] = $shopId;

        return $this->reserveRepository->createReserveEntry($dataArray);
    }

    /**
     * Check if a reserve already exists for the given shop and period
     */
    private function reserveExists(int $shopId, string $currency, array $dateRange): bool
    {
        try {
            return DB::table('rolling_reserve_entries')
                ->where('shop_id', $shopId)
                ->where('original_currency', $currency)
                ->where('period_start', $dateRange['start'])
                ->where('period_end', $dateRange['end'])
                ->exists();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error checking shop reserve existence', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get merchant ID from shop ID
     */
    private function getMerchantIdFromShop(int $shopId): int
    {
        $shop = $this->shopRepository->find($shopId);
        if (!$shop) {
            throw new \Exception("Shop not found: {$shopId}");
        }
        return $shop->merchant_id;
    }
}
