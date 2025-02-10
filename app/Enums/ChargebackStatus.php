<?php

namespace App\Enums;

/**
 * Enum representing possible chargeback statuses
 * Using enum ensures type safety and prevents invalid status values
 */
enum ChargebackStatus: string
{
    case PROCESSING = 'PROCESSING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';

    /**
     * Checks if the status is terminal (approved or declined)
     */
    public function isTerminal(): bool
    {
        return $this === self::APPROVED || $this === self::DECLINED;
    }
}
