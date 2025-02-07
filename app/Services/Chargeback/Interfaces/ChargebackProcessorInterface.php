<?php

namespace App\Services\Chargeback\Interfaces;

use App\DTO\ChargebackData ;
use App\Enums\ChargebackStatus;
/**
 * Interface for processing individual chargebacks
 */
interface ChargebackProcessorInterface
{
    /**
     * Processes a new chargeback transaction
     */
    public function processChargeback(int $merchantId, ChargebackData $data): void;

    /**
     * Handles status changes for existing chargebacks
     */
    public function handleStatusChange(string $transactionId, ChargebackStatus $newStatus): void;
}

