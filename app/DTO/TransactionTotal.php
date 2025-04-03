<?php

namespace App\DTO;

/**
 * Value object representing transaction totals for a specific currency
 */
class TransactionTotal
{
    /**
     * Create a new TransactionTotal instance with all properties
     */
    public function __construct(
        public readonly string $currency,
        public readonly float $totalSales = 0.0,
        public readonly float $totalSalesEur = 0.0,
        public readonly int $transactionSalesCount = 0,
        public readonly float $totalDeclinedSales = 0.0,
        public readonly float $totalDeclinedSalesEur = 0.0,
        public readonly int $transactionDeclinedCount = 0,
        public readonly float $totalRefunds = 0.0,
        public readonly float $totalRefundsEur = 0.0,
        public readonly int $transactionRefundsCount = 0,
        public readonly int $totalChargebackCount = 0,
        public readonly int $processingChargebackCount = 0,
        public readonly float $processingChargebackAmount = 0.0,
        public readonly float $processingChargebackAmountEur = 0.0,
        public readonly int $approvedChargebackCount = 0,
        public readonly float $approvedChargebackAmount = 0.0,
        public readonly float $approvedChargebackAmountEur = 0.0,
        public readonly int $declinedChargebackCount = 0,
        public readonly float $declinedChargebackAmount = 0.0,
        public readonly float $declinedChargebackAmountEur = 0.0,
        public readonly int $totalPayoutCount = 0,
        public readonly float $totalPayoutAmount = 0.0,
        public readonly float $totalPayoutAmountEur = 0.0,
        public readonly int $processingPayoutCount = 0,
        public readonly float $processingPayoutAmount = 0.0,
        public readonly float $processingPayoutAmountEur = 0.0,
        public readonly int $approvedPayoutCount = 0,
        public readonly float $approvedPayoutAmount = 0.0,
        public readonly float $approvedPayoutAmountEur = 0.0,
        public readonly int $declinedPayoutCount = 0,
        public readonly float $declinedPayoutAmount = 0.0,
        public readonly float $declinedPayoutAmountEur = 0.0,
        public readonly float $exchangeRate = 0.0,
        public readonly int $fxRate = 0
    ) {
    }

    /**
     * Create a new instance with default values for a currency
     */
    public static function initialize(string $currency): self
    {
        return new self($currency);
    }

    /**
     * Create a new instance with updated sale amounts
     */
    public function withAddedSale(float $amount, float $amountInEur): self
    {
        return new self(
            $this->currency,
            $this->totalSales + $amount,
            $this->totalSalesEur + $amountInEur,
            $this->transactionSalesCount + 1,
            $this->totalDeclinedSales,
            $this->totalDeclinedSalesEur,
            $this->transactionDeclinedCount,
            $this->totalRefunds,
            $this->totalRefundsEur,
            $this->transactionRefundsCount,
            $this->totalChargebackCount,
            $this->processingChargebackCount,
            $this->processingChargebackAmount,
            $this->processingChargebackAmountEur,
            $this->approvedChargebackCount,
            $this->approvedChargebackAmount,
            $this->approvedChargebackAmountEur,
            $this->declinedChargebackCount,
            $this->declinedChargebackAmount,
            $this->declinedChargebackAmountEur,
            $this->totalPayoutCount,
            $this->totalPayoutAmount,
            $this->totalPayoutAmountEur,
            $this->processingPayoutCount,
            $this->processingPayoutAmount,
            $this->processingPayoutAmountEur,
            $this->approvedPayoutCount,
            $this->approvedPayoutAmount,
            $this->approvedPayoutAmountEur,
            $this->declinedPayoutCount,
            $this->declinedPayoutAmount,
            $this->declinedPayoutAmountEur,
            $this->exchangeRate,
            $this->fxRate
        );
    }

    /**
     * Create a new instance with updated declined sale amounts
     */
    public function withAddedDeclinedSale(float $amount, float $amountInEur): self
    {
        return new self(
            $this->currency,
            $this->totalSales,
            $this->totalSalesEur,
            $this->transactionSalesCount,
            $this->totalDeclinedSales + $amount,
            $this->totalDeclinedSalesEur + $amountInEur,
            $this->transactionDeclinedCount + 1,
            $this->totalRefunds,
            $this->totalRefundsEur,
            $this->transactionRefundsCount,
            $this->totalChargebackCount,
            $this->processingChargebackCount,
            $this->processingChargebackAmount,
            $this->processingChargebackAmountEur,
            $this->approvedChargebackCount,
            $this->approvedChargebackAmount,
            $this->approvedChargebackAmountEur,
            $this->declinedChargebackCount,
            $this->declinedChargebackAmount,
            $this->declinedChargebackAmountEur,
            $this->totalPayoutCount,
            $this->totalPayoutAmount,
            $this->totalPayoutAmountEur,
            $this->processingPayoutCount,
            $this->processingPayoutAmount,
            $this->processingPayoutAmountEur,
            $this->approvedPayoutCount,
            $this->approvedPayoutAmount,
            $this->approvedPayoutAmountEur,
            $this->declinedPayoutCount,
            $this->declinedPayoutAmount,
            $this->declinedPayoutAmountEur,
            $this->exchangeRate,
            $this->fxRate
        );
    }

    /**
     * Create a new instance with updated refund amounts
     */
    public function withAddedRefund(float $amount, float $amountInEur): self
    {
        return new self(
            $this->currency,
            $this->totalSales,
            $this->totalSalesEur,
            $this->transactionSalesCount,
            $this->totalDeclinedSales,
            $this->totalDeclinedSalesEur,
            $this->transactionDeclinedCount,
            $this->totalRefunds + $amount,
            $this->totalRefundsEur + $amountInEur,
            $this->transactionRefundsCount + 1,
            $this->totalChargebackCount,
            $this->processingChargebackCount,
            $this->processingChargebackAmount,
            $this->processingChargebackAmountEur,
            $this->approvedChargebackCount,
            $this->approvedChargebackAmount,
            $this->approvedChargebackAmountEur,
            $this->declinedChargebackCount,
            $this->declinedChargebackAmount,
            $this->declinedChargebackAmountEur,
            $this->totalPayoutCount,
            $this->totalPayoutAmount,
            $this->totalPayoutAmountEur,
            $this->processingPayoutCount,
            $this->processingPayoutAmount,
            $this->processingPayoutAmountEur,
            $this->approvedPayoutCount,
            $this->approvedPayoutAmount,
            $this->approvedPayoutAmountEur,
            $this->declinedPayoutCount,
            $this->declinedPayoutAmount,
            $this->declinedPayoutAmountEur,
            $this->exchangeRate,
            $this->fxRate
        );
    }

    // Similarly, we can implement methods for other transaction types
    // For brevity, only the core methods are included here
    
    /**
     * Create a new instance with updated exchange rate
     */
    public function withExchangeRate(float $exchangeRate): self
    {
        return new self(
            $this->currency,
            $this->totalSales,
            $this->totalSalesEur,
            $this->transactionSalesCount,
            $this->totalDeclinedSales,
            $this->totalDeclinedSalesEur,
            $this->transactionDeclinedCount,
            $this->totalRefunds,
            $this->totalRefundsEur,
            $this->transactionRefundsCount,
            $this->totalChargebackCount,
            $this->processingChargebackCount,
            $this->processingChargebackAmount,
            $this->processingChargebackAmountEur,
            $this->approvedChargebackCount,
            $this->approvedChargebackAmount,
            $this->approvedChargebackAmountEur,
            $this->declinedChargebackCount,
            $this->declinedChargebackAmount,
            $this->declinedChargebackAmountEur,
            $this->totalPayoutCount,
            $this->totalPayoutAmount,
            $this->totalPayoutAmountEur,
            $this->processingPayoutCount,
            $this->processingPayoutAmount,
            $this->processingPayoutAmountEur,
            $this->approvedPayoutCount,
            $this->approvedPayoutAmount,
            $this->approvedPayoutAmountEur,
            $this->declinedPayoutCount,
            $this->declinedPayoutAmount,
            $this->declinedPayoutAmountEur,
            $exchangeRate,
            $this->fxRate
        );
    }

    /**
     * Create a new instance with updated FX rate
     */
    public function withFxRate(int $fxRate): self
    {
        return new self(
            $this->currency,
            $this->totalSales,
            $this->totalSalesEur,
            $this->transactionSalesCount,
            $this->totalDeclinedSales,
            $this->totalDeclinedSalesEur,
            $this->transactionDeclinedCount,
            $this->totalRefunds,
            $this->totalRefundsEur,
            $this->transactionRefundsCount,
            $this->totalChargebackCount,
            $this->processingChargebackCount,
            $this->processingChargebackAmount,
            $this->processingChargebackAmountEur,
            $this->approvedChargebackCount,
            $this->approvedChargebackAmount,
            $this->approvedChargebackAmountEur,
            $this->declinedChargebackCount,
            $this->declinedChargebackAmount,
            $this->declinedChargebackAmountEur,
            $this->totalPayoutCount,
            $this->totalPayoutAmount,
            $this->totalPayoutAmountEur,
            $this->processingPayoutCount,
            $this->processingPayoutAmount,
            $this->processingPayoutAmountEur,
            $this->approvedPayoutCount,
            $this->approvedPayoutAmount,
            $this->approvedPayoutAmountEur,
            $this->declinedPayoutCount,
            $this->declinedPayoutAmount,
            $this->declinedPayoutAmountEur,
            $this->exchangeRate,
            $fxRate
        );
    }

    /**
     * Convert this TransactionTotal to an array representation
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_sales' => $this->totalSales,
            'total_sales_eur' => $this->totalSalesEur,
            'transaction_sales_count' => $this->transactionSalesCount,
            'total_declined_sales' => $this->totalDeclinedSales,
            'total_declined_sales_eur' => $this->totalDeclinedSalesEur,
            'transaction_declined_count' => $this->transactionDeclinedCount,
            'total_refunds' => $this->totalRefunds,
            'total_refunds_eur' => $this->totalRefundsEur,
            'transaction_refunds_count' => $this->transactionRefundsCount,
            'total_chargeback_count' => $this->totalChargebackCount,
            'processing_chargeback_count' => $this->processingChargebackCount,
            'processing_chargeback_amount' => $this->processingChargebackAmount,
            'processing_chargeback_amount_eur' => $this->processingChargebackAmountEur,
            'approved_chargeback_count' => $this->approvedChargebackCount,
            'approved_chargeback_amount' => $this->approvedChargebackAmount,
            'approved_chargeback_amount_eur' => $this->approvedChargebackAmountEur,
            'declined_chargeback_count' => $this->declinedChargebackCount,
            'declined_chargeback_amount' => $this->declinedChargebackAmount,
            'declined_chargeback_amount_eur' => $this->declinedChargebackAmountEur,
            'total_payout_count' => $this->totalPayoutCount,
            'total_payout_amount' => $this->totalPayoutAmount,
            'total_payout_amount_eur' => $this->totalPayoutAmountEur,
            'processing_payout_count' => $this->processingPayoutCount,
            'processing_payout_amount' => $this->processingPayoutAmount,
            'processing_payout_amount_eur' => $this->processingPayoutAmountEur,
            'approved_payout_count' => $this->approvedPayoutCount,
            'approved_payout_amount' => $this->approvedPayoutAmount,
            'approved_payout_amount_eur' => $this->approvedPayoutAmountEur,
            'declined_payout_count' => $this->declinedPayoutCount,
            'declined_payout_amount' => $this->declinedPayoutAmount,
            'declined_payout_amount_eur' => $this->declinedPayoutAmountEur,
            'exchange_rate' => $this->exchangeRate,
            'fx_rate' => $this->fxRate,
        ];
    }
}
