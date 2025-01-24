<?php

namespace App\Services\Settlement\Reserve;

use App\Repositories\Interfaces\RollingReserveRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RollingReserveHandler
{
    private const RESERVE_PERIOD_MONTHS = 6;
    private const RESERVE_PERCENTAGE = 10;

    public function __construct(
        private readonly RollingReserveRepositoryInterface $reserveRepository
    ) {}

    public function processSettlementReserve(
        int $merchantId,
        array $transactionData,
        string $currency,
        array $dateRange
    ): array {
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
                Log::info('Rolling reserve already exists for this period', [
                    'merchant_id' => $merchantId,
                    'currency' => $currency,
                    'period' => $dateRange
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error processing rolling reserve', [
                'merchant_id' => $merchantId,
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    private function createNewReserve(
        int $merchantId,
        array $transactionData,
        string $currency,
        array $dateRange
    ): array {
        $reserveAmount = $transactionData['total_sales'] * (self::RESERVE_PERCENTAGE / 100);
        $reserveAmountEur = $transactionData['total_sales_eur'] * (self::RESERVE_PERCENTAGE / 100);
        $releaseDueDate = Carbon::parse($dateRange['end'])->addMonths(self::RESERVE_PERIOD_MONTHS);

        $reserve = [
            'merchant_id' => $merchantId,
            'period_start' => $dateRange['start'],
            'period_end' => $dateRange['end'],
            'original_amount' => $reserveAmount,
            'original_currency' => $currency,
            'reserve_amount_eur' => $reserveAmountEur,
            'exchange_rate' => $transactionData['exchange_rate'] ?? 1.0,
            'release_due_date' => $releaseDueDate,
            'status' => 'pending',
            'created_at' => now()
        ];

        return $this->reserveRepository->createReserveEntry($reserve);

    }

    private function getReleaseableReserves(int $merchantId, string $currentDate): array
    {
        try {
            $releasableReserves = $this->reserveRepository->getReleaseableFunds(
                $merchantId,
                $currentDate
            );

            if (!empty($releasableReserves)) {
                $entryIds = array_column($releasableReserves, 'id');
                $this->reserveRepository->markReserveAsReleased($entryIds);
            }

            return $releasableReserves;
        } catch (\Exception $e) {
            Log::error('Error processing releasable reserves', [
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
                ->where('settlement_period_start', $dateRange['start'])
                ->where('settlement_period_end', $dateRange['end'])
                ->exists();
        } catch (\Exception $e) {
            Log::error('Error checking reserve existence', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
