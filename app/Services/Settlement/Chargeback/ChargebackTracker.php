<?php

namespace App\Services\Settlement\Chargeback;

use App\Services\DynamicLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

readonly class ChargebackTracker
{
    private const MAX_PROCESSING_DAYS = 14;

    public function __construct(private DynamicLogger $logger)
    {
    }

    /**
     * Track chargebacks and their status changes
     *
     * @param int $merchantId
     * @param array $dateRange
     * @return array
     */
    public function trackChargebacks(int $merchantId, array $dateRange): array
    {
        $chargebacks = DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->select([
                'transactions.tid',
                'transactions.amount',
                'transactions.currency',
                'transactions.added',
                'transactions.transaction_status',
                'transactions.chargeback_status',
                'transactions.chargeback_updated_at'
            ])
            ->where('transactions.account_id', $merchantId)
            ->where('transactions.transaction_type', 'Chargeback')
            ->whereBetween('transactions.added', [$dateRange['start'], $dateRange['end']])
            ->get();

        $processingChargebacks = [];
        $completedChargebacks = [];
        $expiredChargebacks = [];

        foreach ($chargebacks as $chargeback) {
            $processingDays = Carbon::parse($chargeback->added)
                ->diffInDays(Carbon::parse($chargeback->chargeback_updated_at ?? $chargeback->added));

            if ($chargeback->chargeback_status === 'PROCESSING') {
                if ($processingDays > self::MAX_PROCESSING_DAYS) {
                    $expiredChargebacks[] = $chargeback;
                    $this->updateExpiredChargeback($chargeback->tid);
                } else {
                    $processingChargebacks[] = $chargeback;
                }
            } else {
                $completedChargebacks[] = $chargeback;
            }
        }

        return [
            'processing' => $processingChargebacks,
            'completed' => $completedChargebacks,
            'expired' => $expiredChargebacks
        ];
    }

    /**
     * Update expired chargeback status
     */
    private function updateExpiredChargeback(string $transactionId): void
    {
        DB::connection('payment_gateway_mysql')
            ->table('transactions')
            ->where('tid', $transactionId)
            ->update([
                'chargeback_status' => 'DECLINED',
                'chargeback_updated_at' => Carbon::now()
            ]);

        $this->logger->log('info', 'Updated expired chargeback', [
            'transaction_id' => $transactionId
        ]);
    }
}
