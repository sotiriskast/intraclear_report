<?php

namespace App\DTO;

use App\Enums\ChargebackStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;


/**
 * Data Transfer Object for Chargeback information
 * Immutable class that represents a chargeback transaction
 */
readonly class ChargebackData
{
    public function __construct(
        public string $transactionId,
        public float $amount,
        public string $currency,
        public float $amountEur,
        public float $exchangeRate,
        public ChargebackStatus $status,
        public CarbonInterface $processedDate
    ) {}

    /**
     * Creates a ChargebackData instance from a transaction object
     *
     * @param object $transaction Raw transaction data
     * @param float $exchangeRate Current exchange rate
     * @return self
     */
    public static function fromTransaction(object $transaction, float $exchangeRate): self
    {
        return new self(
            transactionId: $transaction->tid,
            amount: $transaction->amount / 100, // Convert from cents to whole units
            currency: $transaction->currency,
            amountEur: ($transaction->amount / 100) * $exchangeRate,
            exchangeRate: $exchangeRate,
            status: ChargebackStatus::from($transaction->transaction_status),
            processedDate: Carbon::parse($transaction->added)
        );
    }
}
