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
        'JPY' => 4,
        'DEFAULT' => 6
    ];

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
                ChargebackTracking::create([
                    'merchant_id' => $merchantId,
                    'shop_id' => $shopId,
                    'transaction_id' => $data->transactionId,
                    'amount' => $data->amount * 100,
                    'currency' => $data->currency,
                    'amount_eur' => $data->amountEur * 100,
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
            // Recalculate amount_eur based on the final exchange rate
            $newAmountEur = ($chargeback->amount / 100) * $finalExchangeRate;
            // Update the chargeback
            $chargeback->update([
                'amount_eur' => (int)round($newAmountEur * 100), // Convert back to cents
                'exchange_rate' => round($finalExchangeRate, $this->getPrecisionForCurrency($currency))
            ]);
            $updated++;
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
}
