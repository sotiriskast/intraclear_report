<?php

namespace App\DTO;

readonly class TransactionData
{
    public function __construct(
        public float  $totalSalesEur,
        public int    $transactionCount,
        public int    $refundCount,
        public int    $chargebackCount,
        public int    $declinedCount,
        public string $currency,
        public float  $exchangeRate,
        public float  $totalSalesAmount
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['total_sales_eur'] ?? 0,
            $data['transaction_sales_count'] ?? 0,
            $data['refund_count'] ?? 0,
            $data['chargeback_count'] ?? 0,
            $data['transaction_declined_count'] ?? 0,
            $data['currency'] ?? 'EUR',
            $data['exchange_rate'] ?? 1.0,
            $data['total_sales_amount'] ?? 0
        );
    }
}
