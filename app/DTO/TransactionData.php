<?php

namespace App\DTO;

readonly class TransactionData
{
    public function __construct(
        public float  $totalSalesEur,
        public float  $totalSales,
        public int    $transactionSalesCount,
        public float  $totalDeclinedSales,
        public float  $totalDeclinedSalesEur,
        public int    $transactionDeclinedCount,
        public float  $totalRefunds,
        public float  $totalRefundsEur,
        public int    $transactionRefundsCount,
        public int    $chargebackCount,
        public string $currency,
        public float  $exchangeRate
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalSalesEur: $data['total_sales_eur'] ?? 0,
            totalSales: $data['total_sales'] ?? 0,
            transactionSalesCount: $data['transaction_sales_count'] ?? 0,
            totalDeclinedSales: $data['total_declined_sales'] ?? 0,
            totalDeclinedSalesEur: $data['total_declined_sales_eur'] ?? 0,
            transactionDeclinedCount: $data['transaction_declined_count'] ?? 0,
            totalRefunds: $data['total_refunds'] ?? 0,
            totalRefundsEur: $data['total_refunds_eur'] ?? 0,
            transactionRefundsCount: $data['transaction_refunds_count'] ?? 0,
            chargebackCount: $data['chargebackCount'] ?? 0,
            currency: $data['currency'] ?? 'EUR',
            exchangeRate: $data['exchange_rate'] ?? 1.0
        );
    }

    public function toArray(): array
    {
        return [
            'total_sales_eur' => $this->totalSalesEur,
            'total_sales' => $this->totalSales,
            'transaction_sales_count' => $this->transactionSalesCount,
            'total_declined_sales' => $this->totalDeclinedSales,
            'total_declined_sales_eur' => $this->totalDeclinedSalesEur,
            'transaction_declined_count' => $this->transactionDeclinedCount,
            'total_refunds' => $this->totalRefunds,
            'total_refunds_eur' => $this->totalRefundsEur,
            'transaction_refunds_count' => $this->transactionRefundsCount,
            'chargeback_count' => $this->chargebackCount,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchangeRate
        ];
    }
}
