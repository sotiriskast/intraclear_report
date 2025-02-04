<?php

namespace App\Services\Settlement\Reserve;

use App\DTO\ReserveEntryData;
use App\Models\RollingReserveEntry;
use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RollingReserveHandler
{
    private const RESERVE_PERIOD_MONTHS = 6;
    private const RESERVE_PERCENTAGE = 10;

    public function __construct(
        private readonly RollingReserveRepositoryInterface $reserveRepository,
        private readonly DynamicLogger                     $logger,
        private readonly MerchantRepository                $merchantRepository


    )
    {
    }

    public function processSettlementReserve(
        int    $merchantId,
        array  $transactionData,
        string $currency,
        array  $dateRange
    ): array
    {
        try {
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end']);

            // Check and process releasable reserves for the current week
            $releasedReserves = $this->processReleasableReserves(
                $merchantId,
                $currency,
                $startDate,
                $endDate
            );

            // Create new reserve if there are transactions and no existing reserve
            $newReserve = null;
            if (!empty($transactionData['total_sales']) && !$this->reserveExists($merchantId, $currency, $dateRange)) {
                $newReserve = $this->createNewReserve(
                    $merchantId,
                    $transactionData,
                    $currency,
                    $dateRange
                );
            }

            return [
                'new_reserve' => $newReserve,
                'released_reserves' => $releasedReserves
            ];

        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing rolling reserve', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function processReleasableReserves(
        int    $merchantId,
        string $currency,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array
    {
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
                        'end' => $weekEnd->toDateString()
                    ]
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
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function createNewReserve(
        int    $merchantId,
        array  $transactionData,
        string $currency,
        array  $dateRange,
    ): RollingReserveEntry
    {
        $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

        $reserveAmount = $transactionData['total_sales'] * (self::RESERVE_PERCENTAGE / 100);
        $reserveAmountEur = $transactionData['total_sales_eur'] * (self::RESERVE_PERCENTAGE / 100);

        $reserveData = new ReserveEntryData(
            merchantId: $internalMerchantId,
            originalAmount: (int)round($reserveAmount * 100),
            originalCurrency: $currency,
            reserveAmountEur: (int)round($reserveAmountEur * 100),
            exchangeRate: $currency === 'EUR' ? 1.0 :$transactionData['exchange_rate'],
            periodStart: Carbon::parse($dateRange['start']),
            periodEnd: Carbon::parse($dateRange['end']),
            releaseDueDate: Carbon::parse($dateRange['end'])->addMonths(self::RESERVE_PERIOD_MONTHS),
        );

        $this->logger->log('info', 'Creating new reserve entry', [
            'merchant_id' => $internalMerchantId,
            'account_id' => $merchantId,
            'amount' => $reserveAmount,
            'amount_eur' => $reserveAmountEur,
            'currency' => $currency,
            'exchange_rate' => $currency === 'EUR' ? 1.0 : $transactionData['exchange_rate'],
            'release_due_date' => $reserveData->releaseDueDate->toDateString()
        ]);

        return $this->reserveRepository->createReserveEntry($reserveData->toArray());
    }

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
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
