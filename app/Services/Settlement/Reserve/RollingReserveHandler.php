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
        private DynamicLogger                              $logger,
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
        $result = [
            'new_reserve' => [],
            'released_reserves' => []
        ];

        try {
            // First, get releasable reserves - this should continue even if there's an error
            $result['released_reserves'] = $this->getReleaseableReserves($merchantId, $dateRange['start']);

            // Then attempt to create new reserve
            if (!$this->reserveExists($merchantId, $currency, $dateRange)) {
                $result['new_reserve'] = $this->createNewReserve(
                    $merchantId,
                    $transactionData,
                    $currency,
                    $dateRange
                );
            } else {
                $this->logger->log('info', 'Rolling reserve already exists for this period', [
                    'merchant_id' => $merchantId,
                    'currency' => $currency,
                    'period' => $dateRange
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing rolling reserve', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;

        }
        return $result;
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
            exchangeRate: (int)round($transactionData['exchange_rate']),
            periodStart: Carbon::parse($dateRange['start']),
            periodEnd: Carbon::parse($dateRange['end']),
            releaseDueDate: Carbon::parse($dateRange['end'])->addMonths(self::RESERVE_PERIOD_MONTHS),
        );

        $this->logger->log('info', 'Creating new reserve entry', [
            'merchant_id' => $internalMerchantId,
            'account_id' => $merchantId,
            'amount' => $reserveAmount,
            'amount_eur' => $reserveAmountEur,
        ]);

        return $this->reserveRepository->createReserveEntry($reserveData->toArray());
    }

    private function getReleaseableReserves(int $merchantId, string $currentDate): array
    {
        try {
            $releasableReserves = $this->reserveRepository->getReleaseableFunds(
                $merchantId,
                $currentDate
            );

            if (!empty($releasableReserves)) {
                // Convert collection to array and get IDs
                $entryIds = $releasableReserves->pluck('id')->toArray();
                $this->reserveRepository->markReserveAsReleased($entryIds);
            }

            // Convert collection to array before returning
            return $releasableReserves->toArray();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error processing releasable reserves', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function reserveExists(int $merchantId, string $currency, array $dateRange): bool
    {
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
