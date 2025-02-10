<?php

declare(strict_types=1);

namespace App\Services\Chargeback;

use App\DTO\ChargebackData;
use App\Enums\ChargebackStatus;
use App\Repositories\Interfaces\ChargebackTrackingRepositoryInterface;
use App\Repositories\MerchantRepository;
use App\Services\Chargeback\Interfaces\ChargebackProcessorInterface;
use App\Services\DynamicLogger;

/**
 * Service for processing individual chargeback transactions
 */
readonly class ChargebackProcessor implements ChargebackProcessorInterface
{
    public function __construct(
        private ChargebackTrackingRepositoryInterface $chargebackTrackingRepository,
        private MerchantRepository $merchantRepository,
        private DynamicLogger $logger
    ) {}

    public function processChargeback(int $merchantId, ChargebackData $data): void
    {
        $merchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

        // First check if we already have this chargeback
        $existingChargeback = $this->chargebackTrackingRepository->findExistingChargeback($data->transactionId);

        if ($existingChargeback) {
            // If status has changed, update it
            if ($existingChargeback['current_status'] !== $data->status->value) {
                $this->handleStatusChange($data->transactionId, $data->status);

                $this->logger->log('info', 'Updated existing chargeback status', [
                    'merchant_id' => $merchantId,
                    'transaction_id' => $data->transactionId,
                    'old_status' => $existingChargeback['current_status'],
                    'new_status' => $data->status->value,
                ]);
            }
        } elseif ($data->status === ChargebackStatus::PROCESSING) {
            // Only track new chargebacks that are in PROCESSING status
            $this->chargebackTrackingRepository->trackNewChargeback($merchantId, $data);

            $this->logger->log('info', 'Processing new chargeback', [
                'merchant_id' => $merchantId,
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
