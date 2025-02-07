<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTO\ChargebackData;
use App\Enums\ChargebackStatus;
use App\Models\ChargebackTracking;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Services\DynamicLogger;
use Carbon\{Carbon, CarbonInterface, CarbonPeriod};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repository implementation for chargeback tracking operations
 */
readonly class ChargebackTrackingRepository implements ChargebackTrackingRepositoryInterface
{
    public function __construct(
        private DynamicLogger $logger
    )
    {
    }

    /**
     * Records a new chargeback transaction
     *
     * @throws \Exception If tracking creation fails
     */
    public function trackNewChargeback(int $merchantId, ChargebackData $data): void
    {
        try {
            DB::transaction(function () use ($merchantId, $data) {
                ChargebackTracking::create([
                    'merchant_id' => $merchantId,
                    'transaction_id' => $data->transactionId,
                    'amount' => $data->amount,
                    'currency' => $data->currency,
                    'amount_eur' => $data->amountEur,
                    'exchange_rate' => $data->exchangeRate,
                    'initial_status' => $data->status->value,
                    'current_status' => $data->status->value,
                    'processing_date' => $data->processedDate
                ]);
            });

            $this->logger->log('info', 'New chargeback tracked successfully', [
                'merchant_id' => $merchantId,
                'transaction_id' => $data->transactionId
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to track chargeback', [
                'merchant_id' => $merchantId,
                'transaction_id' => $data->transactionId,
                'error' => $e->getMessage()
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
                    'status_changed_date' => Carbon::now()
                ]);

                $this->logger->log('info', 'Chargeback status updated', [
                    'transaction_id' => $transactionId,
                    'new_status' => $newStatus->value
                ]);
            }
        });
    }

    /**
     * Retrieves pending settlements for processing
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
            ChargebackTracking::whereIn('id', $chargebackIds['approved'])
                ->update([
                    'settled' => true,
                    'settled_date' => $settledDate,
                    'current_status' => 'APPROVED',
                    'status_changed_date' => Carbon::now()

                ]);
            $this->logger->log('info', 'Chargebacks marked as settled', [
                'chargeback_count' => count($chargebackIds['approved']),
                'settled_date' => $settledDate->toDateTimeString(),
                'current_status' => 'APPROVED',
                'status_changed_date' => Carbon::now()
            ]);
            ChargebackTracking::whereIn('id', $chargebackIds['declined'])
                ->update([
                    'settled' => true,
                    'settled_date' => $settledDate,
                    'current_status' => 'DECLINED',
                    'status_changed_date' => Carbon::now()

                ]);
            $this->logger->log('info', 'Chargebacks marked as settled', [
                'chargeback_count' => count($chargebackIds['declined']),
                'settled_date' => $settledDate->toDateTimeString(),
                'current_status' => 'DECLINED',
                'status_changed_date' => Carbon::now()
            ]);

        });
    }

    /**
     * Retrieves chargebacks within a specified date range
     */
    public function getChargebacksByDateRange(int $merchantId, CarbonPeriod $dateRange): array
    {
        return ChargebackTracking::query()
            ->where('merchant_id', $merchantId)
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
}
