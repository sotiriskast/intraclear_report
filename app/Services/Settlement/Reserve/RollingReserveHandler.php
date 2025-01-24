<?php

namespace App\Services\Settlement\Reserve;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RollingReserveHandler
{
    private const RESERVE_PERIOD_MONTHS = 6;

    public function calculateNewReserve(array $transactionData, float $reservePercentage): array
    {
        $reserveAmount = $transactionData['total_sales'] * ($reservePercentage / 100);
        $reserveAmountEur = $transactionData['total_sales_eur'] * ($reservePercentage / 100);
        $releaseDate = Carbon::now()->addMonths(self::RESERVE_PERIOD_MONTHS);

        return [
            'original_amount' => $reserveAmount,
            'reserve_amount_eur' => $reserveAmountEur,
            'percentage' => $reservePercentage,
            'release_date' => $releaseDate->format('Y-m-d')
        ];
    }

    public function getReleaseableReserves(int $merchantId, string $currentDate): array
    {
        return DB::table('rolling_reserve_entries')
            ->where('merchant_id', $merchantId)
            ->where('status', 'pending')
            ->where('release_date', '<=', $currentDate)
            ->whereNull('released_at')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'original_amount' => $entry->original_amount,
                    'reserve_amount_eur' => $entry->reserve_amount_eur,
                    'transaction_date' => $entry->transaction_date,
                    'release_date' => $entry->release_date
                ];
            })
            ->toArray();
    }

    public function createReserveEntry(
        int $merchantId,
        float $amount,
        float $amountEur,
        string $currency,
        float $exchangeRate
    ): void {
        DB::table('rolling_reserve_entries')->insert([
            'merchant_id' => $merchantId,
            'original_amount' => $amount,
            'original_currency' => $currency,
            'reserve_amount_eur' => $amountEur,
            'exchange_rate' => $exchangeRate,
            'transaction_date' => now(),
            'release_date' => Carbon::now()->addMonths(self::RESERVE_PERIOD_MONTHS),
            'status' => 'pending',
            'created_at' => now()
        ]);
    }

    public function markReserveAsReleased(array $entryIds): void
    {
        DB::table('rolling_reserve_entries')
            ->whereIn('id', $entryIds)
            ->update([
                'status' => 'released',
                'released_at' => now(),
                'updated_at' => now()
            ]);
    }
}
