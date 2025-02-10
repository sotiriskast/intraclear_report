<?php

namespace App\Services\Settlement\Reserve;

use App\DTO\ReserveEntryData;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\MerchantSettingRepository;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Handles the processing and management of rolling reserves for merchant settlements
 * Manages creation of new reserves, release of existing reserves, and tracking of reserve periods
 */
class RollingReserveHandler
{
    /**
     * Number of months to hold the reserve before release
     */
    private const RESERVE_PERIOD_MONTHS = 6;

    /**
     * Percentage of transaction amount to hold in reserve
     */
    private const RESERVE_PERCENTAGE = 10;

    /**
     * Initialize the rolling reserve handler with required dependencies
     *
     * @param  RollingReserveRepositoryInterface  $reserveRepository  Repository for reserve operations
     * @param  DynamicLogger  $logger  Service for logging operations
     * @param  MerchantRepository  $merchantRepository  Repository for merchant data
     */
    public function __construct(
        private readonly RollingReserveRepositoryInterface $reserveRepository,
        private readonly DynamicLogger $logger,
        private readonly MerchantRepository $merchantRepository,
        private readonly MerchantSettingRepository $merchantSettingRepository

    ) {}

    /**
     * Process settlement reserves for a given merchant and period
     * Handles both creation of new reserves and release of existing ones
     *
     * @param  int  $merchantId  ID of the merchant account
     * @param  array  $transactionData  Transaction data including:
     *                                  - total_sales: float (in original currency)
     *                                  - total_sales_eur: float (in EUR)
     *                                  - exchange_rate: float
     * @param  string  $currency  Currency code of the transaction
     * @param  array  $dateRange  Array containing:
     *                            - start: string (Y-m-d format)
     *                            - end: string (Y-m-d format)
     * @return array Array containing:
     *               - new_reserve: RollingReserveEntry|null
     *               - released_reserves: array
     *
     * @throws \Exception If processing fails
     */
    public function processSettlementReserve(
        int $merchantId,
        array $transactionData,
        string $currency,
        array $dateRange
    ): array {
        try {
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end']);

            $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
            // Get merchant settings
            $merchantSettings = $this->merchantSettingRepository->findByMerchant($internalMerchantId);
            $reservePercentage = $merchantSettings
                ? ($merchantSettings->rolling_reserve_percentage / 100) // Convert from stored integer (1000 = 10%)
                : self::RESERVE_PERCENTAGE;

            $reservePeriodMonths = $merchantSettings
                ? ceil($merchantSettings->holding_period_days / 30) // Convert days to months
                : self::RESERVE_PERIOD_MONTHS;
            // Check and process releasable reserves for the current week
            $releasedReserves = $this->processReleasableReserves(
                $merchantId,
                $currency,
                $startDate,
                $endDate
            );
            // Create new reserve if there are transactions and no existing reserve
            $newReserve = null;
            if (! empty($transactionData['total_sales']) && ! $this->reserveExists($merchantId, $currency, $dateRange)) {
                $reserveAmount = $transactionData['total_sales'] * ($reservePercentage / 100);
                $reserveAmountEur = $transactionData['total_sales_eur'] * ($reservePercentage / 100);
                $reserveData = new ReserveEntryData(
                    merchantId: $internalMerchantId,
                    originalAmount: (int) round($reserveAmount * 100),
                    originalCurrency: $currency,
                    reserveAmountEur: (int) round($reserveAmountEur * 100),
                    exchangeRate: $currency === 'EUR' ? 1.0 : $transactionData['exchange_rate'],
                    periodStart: Carbon::parse($dateRange['start']),
                    periodEnd: Carbon::parse($dateRange['end']),
                    releaseDueDate: Carbon::parse($dateRange['end'])->addMonths($reservePeriodMonths),
                );
                $newReserve = $this->createNewReserve($reserveData);
            }

            return [
                'new_reserve' => $newReserve,
                'released_reserves' => $releasedReserves,
                'reserved_percentage' => $reservePercentage ?? 10,

            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing rolling reserve', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process and release reserves that have reached their release date
     *
     * @param  int  $merchantId  ID of the merchant account
     * @param  string  $currency  Currency code to filter reserves
     * @param  Carbon  $weekStart  Start of the week period
     * @param  Carbon  $weekEnd  End of the week period
     * @return array Array of released reserve entries with amounts converted from cents
     */
    private function processReleasableReserves(
        int $merchantId,
        string $currency,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        try {
            $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
            // Get all reserves that are due for release within this week's period
            $releasableReserves = $this->reserveRepository->getReleaseableFunds(
                $merchantId,
                $weekEnd->toDateString()
            );
            $releasableReserves = $releasableReserves->filter(function ($reserve) use ($currency) {
                return $reserve->original_currency === $currency;
            });
            if ($releasableReserves->isNotEmpty()) {
                $entryIds = $releasableReserves->pluck('id')->toArray();
                $this->reserveRepository->markReserveAsReleased($entryIds);

                $this->logger->log('info', 'Released rolling reserves', [
                    'merchant_id' => $merchantId,
                    'currency' => $currency,
                    'count' => count($entryIds),
                    'week_period' => [
                        'start' => $weekStart->toDateString(),
                        'end' => $weekEnd->toDateString(),
                    ],
                ]);
            }

            return $releasableReserves->map(function ($reserve) {
                $attributes = $reserve->getAttributes();
                $attributes['original_amount'] = $attributes['original_amount'] / 100;
                $attributes['reserve_amount_eur'] = $attributes['reserve_amount_eur'] / 100;

                return $attributes;
            })->all();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing releasable reserves', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Create a new rolling reserve entry for a transaction period
     *
     * @param  int  $merchantId  ID of the merchant account
     * @param  array  $transactionData  Transaction data including total sales amounts
     * @param  string  $currency  Currency code of the transaction
     * @param  array  $dateRange  Period start and end dates
     * @return RollingReserveEntry Newly created reserve entry
     */
    private function createNewReserve(ReserveEntryData $reserveData): RollingReserveEntry
    {
        $this->logger->log('info', 'Creating new reserve entry', [
            'merchant_id' => $reserveData->merchantId,
            'amount' => $reserveData->originalAmount / 100,
            'amount_eur' => $reserveData->reserveAmountEur / 100,
            'currency' => $reserveData->originalCurrency,
            'exchange_rate' => $reserveData->exchangeRate,
            'release_due_date' => $reserveData->releaseDueDate->toDateString(),
        ]);

        return $this->reserveRepository->createReserveEntry($reserveData->toArray());
    }

    /**
     * Check if a reserve already exists for the given period and currency
     *
     * @param  int  $merchantId  ID of the merchant account
     * @param  string  $currency  Currency code to check
     * @param  array  $dateRange  Period start and end dates
     * @return bool True if reserve exists, false otherwise
     */
    private function reserveExists(int $merchantId, string $currency, array $dateRange): bool
    {
        $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
        try {
            return DB::table('rolling_reserve_entries')
                ->where('merchant_id', $merchantId)
                ->where('original_currency', $currency)
                ->where('period_start', $dateRange['start'])
                ->where('period_end', $dateRange['end'])
                ->exists();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error checking reserve existence', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
