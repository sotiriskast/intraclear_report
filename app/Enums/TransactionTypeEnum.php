<?php

namespace App\Enums;

/**
 * Enum representing all possible transaction types in the payment system
 */
enum TransactionTypeEnum: string
{
    /**
     * Sale transaction - a purchase made by a customer
     */
    case SALE = 'SALE';

    /**
     * Refund transaction - a full refund issued to a customer
     */
    case REFUND = 'REFUND';

    /**
     * Partial refund - a partial amount refunded to a customer
     */
    case PARTIAL_REFUND = 'PARTIAL REFUND';

    /**
     * Chargeback - a forced reversal initiated by the customer's bank
     */
    case CHARGEBACK = 'CHARGEBACK';

    /**
     * Payout - funds transferred to a merchant
     */
    case PAYOUT = 'PAYOUT';
}
