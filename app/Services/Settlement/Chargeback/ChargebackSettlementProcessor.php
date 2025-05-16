<?php

declare(strict_types=1);

namespace App\Services\Settlement\Chargeback;

use App\Enums\ChargebackStatus;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackSettlementInterface;
use Carbon\Carbon;

/**
 * Service for processing chargeback settlements
 */
readonly class ChargebackSettlementProcessor implements ChargebackSettlementInterface
{
    public function __construct(
        private ChargebackTrackingRepositoryInterface $repository,
        private MerchantRepository $merchantRepository,
        private ShopRepository $shopRepository,
        private DynamicLogger $logger
    ) {}

    /**
     * Process settlements for a merchant (backward compatibility)
     */
    public function processSettlementsChargeback(int $merchantId, array $dateRange): array
    {
        $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

        $this->logger->log('info', 'Starting merchant chargeback settlement processing', [
            'merchant_id' => $merchantId,
            'date_range' => $dateRange,
        ]);

        $pendingChargebacks = $this->repository->getPendingSettlements($internalMerchantId);

        return $this->processChargebacksList($pendingChargebacks, $merchantId);
    }

    /**
     * Process settlements for a specific shop
     */
    public function processShopSettlementsChargeback(int $shopId, array $dateRange): array
    {
        $this->logger->log('info', 'Starting shop chargeback settlement processing', [
            'shop_id' => $shopId,
            'date_range' => $dateRange,
        ]);

        $pendingChargebacks = $this->repository->getShopPendingSettlements($shopId);

        return $this->processChargebacksList($pendingChargebacks, null, $shopId);
    }

    /**
     * Common logic to process a list of chargebacks
     */
    private function processChargebacksList(array $pendingChargebacks, ?int $merchantId = null, ?int $shopId = null): array
    {
        $settlements = [
            'approved_refunds' => 0.0,
            'approved_refunds_eur' => 0.0,
            'processed_count' => 0,
            'processed_ids' => [],
            'settlement_date' => Carbon::now(),
        ];

        foreach ($pendingChargebacks as $chargeback) {
            $retrieveChargebacks = $this->repository->getChargebackByTransactionId((int) $chargeback['transaction_id']);

            if ($retrieveChargebacks->transaction_status === ChargebackStatus::PROCESSING->value) {
                continue;
            }

            if ($retrieveChargebacks->transaction_status === ChargebackStatus::APPROVED->value) {
                $settlements['approved_refunds'] += $chargeback['amount'] / 100;
                $settlements['approved_refunds_eur'] += $chargeback['amount_eur'] / 100;
                $settlements['processed_ids']['approved'][] = $chargeback['id'];
            } else {
                $settlements['processed_ids']['declined'][] = $chargeback['id'];
            }
            $settlements['processed_count']++;
        }

        if (!empty($settlements['processed_ids'])) {
            $this->repository->markAsSettled(
                $settlements['processed_ids'],
                $settlements['settlement_date']
            );
        }

        $logContext = [
            'processed_count' => $settlements['processed_count'],
            'approved_refunds_eur' => $settlements['approved_refunds_eur'],
        ];

        if ($merchantId) {
            $logContext['merchant_id'] = $merchantId;
            $this->logger->log('info', 'Completed merchant chargeback settlement processing', $logContext);
        }

        if ($shopId) {
            $logContext['shop_id'] = $shopId;
            $this->logger->log('info', 'Completed shop chargeback settlement processing', $logContext);
        }

        return $settlements;
    }
}
