<?php

declare(strict_types=1);

namespace App\Services\Settlement\Chargeback;

use App\DTO\ChargebackData;
use App\Enums\ChargebackStatus;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Repositories\ShopRepository;
use App\Services\DynamicLogger;
use App\Services\Settlement\Chargeback\Interfaces\ChargebackProcessorInterface;

/**
 * Service for processing individual chargeback transactions
 */
readonly class ChargebackProcessor implements ChargebackProcessorInterface
{
    public function __construct(
        private ChargebackTrackingRepositoryInterface $chargebackTrackingRepository,
        private MerchantRepository $merchantRepository,
        private ShopRepository $shopRepository,
        private DynamicLogger $logger
    ) {}

    public function processChargeback(int $merchantId, int $shopId, ChargebackData $data): void
    {
        // Get internal IDs
        $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);
        $internalShopId = $this->shopRepository->getInternalIdByExternalId($shopId, $merchantId);

        // First check if we already have this chargeback
        $existingChargeback = $this->chargebackTrackingRepository->findExistingChargeback($data->transactionId);

        if ($existingChargeback) {
            // If status has changed, update it
            if ($existingChargeback['current_status'] !== $data->status->value) {
                $this->handleStatusChange($data->transactionId, $data->status);

                $this->logger->log('info', 'Updated existing chargeback status', [
                    'merchant_id' => $merchantId,
                    'shop_id' => $shopId,
                    'transaction_id' => $data->transactionId,
                    'old_status' => $existingChargeback['current_status'],
                    'new_status' => $data->status->value,
                ]);
            }
        } elseif ($data->status === ChargebackStatus::PROCESSING) {
            // Only track new chargebacks that are in PROCESSING status
            $this->chargebackTrackingRepository->trackNewChargeback($internalMerchantId, $internalShopId, $data);

            $this->logger->log('info', 'Processing new chargeback for shop', [
                'merchant_id' => $merchantId,
                'shop_id' => $shopId,
                'transaction_id' => $data->transactionId,
                'amount' => $data->amount,
                'currency' => $data->currency,
            ]);
        }

        if ($data->status->isTerminal()) {
            $this->handleStatusChange($data->transactionId, $data->status);
        }
    }

    public function handleStatusChange(string $transactionId, ChargebackStatus $newStatus): void
    {
        $this->chargebackTrackingRepository->updateChargebackStatus($transactionId, $newStatus);
    }
}
