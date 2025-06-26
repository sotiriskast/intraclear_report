<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\ChargebackData;
use App\Enums\ChargebackStatus;
use App\Models\ChargebackTracking;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Services\DynamicLogger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Repository implementation for chargeback tracking operations
 */
readonly class ChargebackTrackingRepository implements ChargebackTrackingRepositoryInterface
{
    private const array CURRENCY_PRECISION = [
        'JPY' => 8,
        'DEFAULT' => 8
    ];

    /**
     * Maximum safe value for monetary calculations (to prevent overflow)
     * This is well below PHP_INT_MAX and database bigint limits
     */
    private const int MAX_SAFE_AMOUNT = 9223372036854775000; // About 92 trillion cents

    /**
     * Get the appropriate decimal precision for a currency
     *
     * @param string $currency The currency code
     * @return int The precision to use
     */
    private function getPrecisionForCurrency(string $currency): int
    {
        return self::CURRENCY_PRECISION[$currency] ?? self::CURRENCY_PRECISION['DEFAULT'];
    }

    public function __construct(private MerchantRepository $merchantRepository,
                                private DynamicLogger      $logger)
    {
    }

    /**
     * Records a new chargeback transaction with shop tracking
     *
     * @throws \Exception If tracking creation fails
     */
    public function trackNewChargeback(int $merchantId, int $shopId, ChargebackData $data): void
    {
        try {
            DB::transaction(function () use ($merchantId, $shopId, $data) {
                // Safe conversion to cents with overflow protection
                $amountCents = $this->safeAmountToCents($data->amount);
                $amountEurCents = $this->safeAmountToCents($data->amountEur);

                ChargebackTracking::create([
                    'merchant_id' => $merchantId,
                    'shop_id' => $shopId,
                    'transaction_id' => $data->transactionId,
                    'amount' => $amountCents,
                    'currency' => $data->currency,
                    'amount_eur' => $amountEurCents,
                    'exchange_rate' => $data->exchangeRate,
                    'initial_status' => $data->status->value,
                    'current_status' => $data->status->value,
                    'processing_date' => $data->processedDate,
                ]);
            });

            $this->logger->log('info', 'New chargeback tracked successfully', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'transaction_id' => $data->transactionId,
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to track chargeback', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'transaction_id' => $data->transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Updates the status of an existing chargeback
     */
    public function updateChargebackStatus(string $transactionId, ChargebackStatus $newStatus): void
    {
        DB::transaction(function () use ($transactionId, $newStatus) {
            $tracking = ChargebackTracking::where('transaction_id', $transactionId)
                ->where('settled', false)
                ->lockForUpdate()
                ->first();

            if ($tracking) {
                $tracking->update([
                    'current_status' => $newStatus->value,
                    'status_changed_date' => Carbon::now(),
                ]);

                $this->logger->log('info', 'Chargeback status updated', [
                    'transaction_id' => $transactionId,
                    'shop_id' => $tracking->shop_id,
                    'new_status' => $newStatus->value,
                ]);
            }
        });
    }

    /**
     * Retrieves pending settlements for processing (backward compatibility)
     *
     * @return array<int, array>
     */
    public function getPendingSettlements(int $merchantId): array
    {
        return ChargebackTracking::query()
            ->where('merchant_id', $merchantId)
            ->where('settled', false)
            ->whereNull('status_changed_date')
            ->get()
            ->toArray();
    }

    /**
     * Retrieves pending settlements for a specific shop
     *
     * @return array<int, array>
     */
    public function getShopPendingSettlements(int $shopId): array
    {
        return ChargebackTracking::query()
            ->where('shop_id', $shopId)
            ->where('settled', false)
            ->whereNull('status_changed_date')
            ->get()
            ->toArray();
    }

    /**
     * Get status updates for tracked chargebacks
     */
    public function getChargebackByTransactionId(int $transaction_id): \stdClass
    {
        return DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->select([
                'tid as transaction_id',
                'transaction_status',
                'added as status_date',
            ])
            ->where('tid', $transaction_id)
            ->first();
    }

    /**
     * Marks multiple chargebacks as settled
     */
    public function markAsSettled(array $chargebackIds, ?CarbonInterface $settledDate = null): void
    {
        $settledDate ??= Carbon::now();

        DB::transaction(function () use ($chargebackIds, $settledDate) {
            // Check if the keys exist before using them
            if (isset($chargebackIds['approved']) && !empty($chargebackIds['approved'])) {
                ChargebackTracking::whereIn('id', $chargebackIds['approved'])
                    ->update([
                        'settled' => true,
                        'settled_date' => $settledDate,
                        'current_status' => 'APPROVED',
                        'status_changed_date' => Carbon::now(),
                    ]);

                $this->logger->log('info', 'Chargebacks marked as settled', [
                    'chargeback_count' => count($chargebackIds['approved']),
                    'settled_date' => $settledDate->toDateTimeString(),
                    'current_status' => 'APPROVED',
                    'status_changed_date' => Carbon::now(),
                ]);
            }

            if (isset($chargebackIds['declined']) && !empty($chargebackIds['declined'])) {
                ChargebackTracking::whereIn('id', $chargebackIds['declined'])
                    ->update([
                        'settled' => true,
                        'settled_date' => $settledDate,
                        'current_status' => 'DECLINED',
                        'status_changed_date' => Carbon::now(),
                    ]);

                $this->logger->log('info', 'Chargebacks marked as settled', [
                    'chargeback_count' => count($chargebackIds['declined']),
                    'settled_date' => $settledDate->toDateTimeString(),
                    'current_status' => 'DECLINED',
                    'status_changed_date' => Carbon::now(),
                ]);
            }
        });
    }

    /**
     * Retrieves chargebacks within a specified date range for a merchant
     */
    public function getChargebacksByDateRange(int $merchantId, CarbonPeriod $dateRange): array
    {
        return ChargebackTracking::query()
            ->where('merchant_id', $merchantId)
            ->whereBetween('processing_date', [$dateRange->start, $dateRange->end])
            ->get()
            ->toArray();
    }

    /**
     * Retrieves chargebacks within a specified date range for a shop
     */
    public function getShopChargebacksByDateRange(int $shopId, CarbonPeriod $dateRange): array
    {
        return ChargebackTracking::query()
            ->where('shop_id', $shopId)
            ->whereBetween('processing_date', [$dateRange->start, $dateRange->end])
            ->get()
            ->toArray();
    }

    public function findExistingChargeback(string $transactionId): ?array
    {
        $tracking = ChargebackTracking::where('transaction_id', $transactionId)
            ->first();

        return $tracking ? $tracking->toArray() : null;
    }

    /**
     * Updates chargebacks with the final exchange rate for a specific currency
     */
    public function updateChargebacksWithFinalExchangeRate(
        int    $merchantId,
        ?int   $shopId,
        string $currency,
        float  $finalExchangeRate,
        array  $dateRange
    ): int
    {
        $startDate = Carbon::parse($dateRange['start']);
        $endDate = Carbon::parse($dateRange['end']);
        $query = ChargebackTracking::query()
            ->where('merchant_id', $this->merchantRepository->getMerchantIdByAccountId($merchantId))
            ->where('currency', $currency)
            ->whereBetween('processing_date', [$startDate, $endDate]);

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        // Get chargebacks to update
        $chargebacks = $query->get();
        $updated = 0;

        foreach ($chargebacks as $chargeback) {
            try {
                // Safely calculate new amount in EUR
                $newAmountEur = $this->safeCalculateEurAmount(
                    $chargeback->amount,
                    $finalExchangeRate
                );

                // Update the chargeback with safe values
                $chargeback->update([
                    'amount_eur' => $newAmountEur,
                    'exchange_rate' => round($finalExchangeRate, $this->getPrecisionForCurrency($currency))
                ]);

                $updated++;
            } catch (\Exception $e) {
                $this->logger->log('error', 'Failed to update chargeback with final exchange rate', [
                    'chargeback_id' => $chargeback->id,
                    'transaction_id' => $chargeback->transaction_id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other chargebacks instead of failing completely
            }
        }

        $this->logger->log('info', 'Updated chargebacks with final exchange rate', [
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'currency' => $currency,
            'exchange_rate' => $finalExchangeRate,
            'chargebacks_updated' => $updated
        ]);

        return $updated;
    }

    /**
     * Safely convert an amount to cents with overflow protection
     *
     * @param float $amount Amount in base currency
     * @return int Amount in cents
     * @throws \Exception If the amount would cause overflow
     */
    private function safeAmountToCents(float $amount): int
    {
        $amountCents = (int) round($amount * 100);

        if ($amountCents > self::MAX_SAFE_AMOUNT) {
            throw new \Exception("Amount too large for safe storage: {$amount}");
        }

        return $amountCents;
    }

    /**
     * Safely calculate EUR amount from original amount and exchange rate
     *
     * @param int $originalAmountCents Original amount in cents
     * @param float $exchangeRate Exchange rate
     * @return int EUR amount in cents
     * @throws \Exception If the calculated amount would cause overflow
     */
    private function safeCalculateEurAmount(int $originalAmountCents, float $exchangeRate): int
    {
        // Convert cents to base currency
        $originalAmount = $originalAmountCents / 100;
        // Calculate EUR amount
        $eurAmount = $originalAmount / $exchangeRate;
        // Check if the result would be too large
        if ($eurAmount > (self::MAX_SAFE_AMOUNT / 100)) {
            $this->logger->log('warning', 'EUR amount calculation would exceed safe limits', [
                'original_amount_cents' => $originalAmountCents,
                'exchange_rate' => $exchangeRate,
                'calculated_eur_amount' => $eurAmount,
            ]);
            // Return the maximum safe value instead of failing
            return self::MAX_SAFE_AMOUNT;
        }
        return (int) round($eurAmount * 100);
    }
}
