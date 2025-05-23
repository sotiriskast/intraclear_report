<?php

namespace App\DTO;

readonly class TransactionData
{
    public const TOTAL_SALES_EUR = 'total_sales_eur';
    public const TOTAL_SALES = 'total_sales';
    public const TRANSACTION_SALES_COUNT = 'transaction_sales_count';
    public const TOTAL_DECLINED_SALES = 'total_declined_sales';
    public const TOTAL_DECLINED_SALES_EUR = 'total_declined_sales_eur';
    public const TRANSACTION_DECLINED_COUNT = 'transaction_declined_count';
    public const TOTAL_REFUNDS = 'total_refunds';
    public const TOTAL_REFUNDS_EUR = 'total_refunds_eur';
    public const TRANSACTION_REFUNDS_COUNT = 'transaction_refunds_count';
    public const TOTAL_CHARGEBACK_COUNT = 'total_chargeback_count';
    public const PROCESSING_CHARGEBACK_COUNT = 'processing_chargeback_count';
    public const PROCESSING_CHARGEBACK_AMOUNT = 'processing_chargeback_amount';
    public const PROCESSING_CHARGEBACK_AMOUNT_EUR = 'processing_chargeback_amount_eur';
    public const APPROVED_CHARGEBACK_COUNT = 'approved_chargeback_count';
    public const APPROVED_CHARGEBACK_AMOUNT = 'approved_chargeback_amount';
    public const DECLINED_CHARGEBACK_COUNT = 'declined_chargeback_count';
    public const DECLINED_CHARGEBACK_AMOUNT = 'declined_chargeback_amount';
    public const TOTAL_PAYOUT_COUNT = 'total_payout_count';
    public const PROCESSING_PAYOUT_AMOUNT = 'total_payout_amount';
    public const PROCESSING_PAYOUT_AMOUNT_EUR = 'total_payout_amount_eur';
    public const PROCESSING_PAYOUT_COUNT = 'processing_payout_count';
    public const APPROVED_PAYOUT_AMOUNT = 'approved_payout_amount';
    public const APPROVED_PAYOUT_AMOUNT_EUR = 'approved_payout_amount_eur';
    public const DECLINED_PAYOUT_COUNT = 'declined_payout_count';
    public const DECLINED_PAYOUT_AMOUNT = 'declined_payout_amount';
    public const DECLINED_PAYOUT_AMOUNT_EUR = 'declined_payout_amount_eur';
    public const CURRENCY = 'currency';
    public const EXCHANGE_RATE = 'exchange_rate';
    public const FX_RATE = 'fx_rate';

    public function __construct(
        public float  $totalSalesEur = 0,
        public float  $totalSales = 0,
        public int    $transactionSalesCount = 0,
        public float  $totalDeclinedSales = 0,
        public float  $totalDeclinedSalesEur = 0,
        public int    $transactionDeclinedCount = 0,
        public float  $totalRefunds = 0,
        public float  $totalRefundsEur = 0,
        public int    $transactionRefundsCount = 0,
        public int    $totalChargebackCount = 0,
        public int    $processingChargebackCount = 0,
        public float  $processingChargebackAmount = 0,
        public float  $processingChargebackAmountEur = 0,
        public int    $approvedChargebackCount = 0,
        public float  $approvedChargebackAmount = 0,
        public int    $declinedChargebackCount = 0,
        public float  $declinedChargebackAmount = 0,
        public int    $totalPayoutCount = 0,
        public int    $processingPayoutCount = 0,
        public float  $processingPayoutAmount = 0,
        public float  $processingPayoutAmountEur = 0,
        public float  $approvedPayoutAmount = 0,
        public float  $approvedPayoutAmountEur = 0,
        public float  $declinedPayoutCount = 0,
        public float  $declinedPayoutAmount = 0,
        public float  $declinedPayoutAmountEur = 0,

        public string $currency = 'EUR',
        public float  $exchangeRate = 1.0,
        public float  $fxRate = 0,
    )
    {
    }

    public static function initialize(string $currency): self
    {
        return new self(currency: $currency);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalSalesEur: $data[self::TOTAL_SALES_EUR] ?? 0,
            totalSales: $data[self::TOTAL_SALES] ?? 0,
            transactionSalesCount: $data[self::TRANSACTION_SALES_COUNT] ?? 0,
            totalDeclinedSales: $data[self::TOTAL_DECLINED_SALES] ?? 0,
            totalDeclinedSalesEur: $data[self::TOTAL_DECLINED_SALES_EUR] ?? 0,
            transactionDeclinedCount: $data[self::TRANSACTION_DECLINED_COUNT] ?? 0,
            totalRefunds: $data[self::TOTAL_REFUNDS] ?? 0,
            totalRefundsEur: $data[self::TOTAL_REFUNDS_EUR] ?? 0,
            transactionRefundsCount: $data[self::TRANSACTION_REFUNDS_COUNT] ?? 0,
            totalChargebackCount: $data[self::TOTAL_CHARGEBACK_COUNT] ?? 0,
            processingChargebackCount: $data[self::PROCESSING_CHARGEBACK_COUNT] ?? 0,
            processingChargebackAmount: $data[self::PROCESSING_CHARGEBACK_AMOUNT] ?? 0,
            processingChargebackAmountEur: $data[self::PROCESSING_CHARGEBACK_AMOUNT_EUR] ?? 0,
            approvedChargebackCount: $data[self::APPROVED_CHARGEBACK_COUNT] ?? 0,
            approvedChargebackAmount: $data[self::APPROVED_CHARGEBACK_AMOUNT] ?? 0,
            declinedChargebackCount: $data[self::DECLINED_CHARGEBACK_COUNT] ?? 0,
            declinedChargebackAmount: $data[self::DECLINED_CHARGEBACK_AMOUNT] ?? 0,
            totalPayoutCount: $data[self::TOTAL_PAYOUT_COUNT] ?? 0,
            processingPayoutCount: $data[self::PROCESSING_PAYOUT_COUNT] ?? 0,
            processingPayoutAmount: $data[self::PROCESSING_PAYOUT_AMOUNT] ?? 0,
            processingPayoutAmountEur: $data[self::PROCESSING_PAYOUT_AMOUNT_EUR] ?? 0,
            approvedPayoutAmount: $data[self::APPROVED_PAYOUT_AMOUNT] ?? 0,
            approvedPayoutAmountEur: $data[self::APPROVED_PAYOUT_AMOUNT_EUR] ?? 0,
            declinedPayoutCount: $data[self::DECLINED_PAYOUT_COUNT] ?? 0,
            declinedPayoutAmount: $data[self::DECLINED_PAYOUT_AMOUNT] ?? 0,
            declinedPayoutAmountEur: $data[self::DECLINED_PAYOUT_AMOUNT_EUR] ?? 0,
            currency: $data[self::CURRENCY] ?? 'EUR',
            exchangeRate: $data[self::EXCHANGE_RATE] ?? 1.0,
            fxRate: $data[self::FX_RATE] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            self::TOTAL_SALES_EUR => $this->totalSalesEur,
            self::TOTAL_SALES => $this->totalSales,
            self::TRANSACTION_SALES_COUNT => $this->transactionSalesCount,
            self::TOTAL_DECLINED_SALES => $this->totalDeclinedSales,
            self::TOTAL_DECLINED_SALES_EUR => $this->totalDeclinedSalesEur,
            self::TRANSACTION_DECLINED_COUNT => $this->transactionDeclinedCount,
            self::TOTAL_REFUNDS => $this->totalRefunds,
            self::TOTAL_REFUNDS_EUR => $this->totalRefundsEur,
            self::TRANSACTION_REFUNDS_COUNT => $this->transactionRefundsCount,
            self::TOTAL_CHARGEBACK_COUNT => $this->totalChargebackCount,
            self::PROCESSING_CHARGEBACK_COUNT => $this->processingChargebackCount,
            self::PROCESSING_CHARGEBACK_AMOUNT => $this->processingChargebackAmount,
            self::PROCESSING_CHARGEBACK_AMOUNT_EUR => $this->processingChargebackAmountEur,
            self::APPROVED_CHARGEBACK_COUNT => $this->approvedChargebackCount,
            self::APPROVED_CHARGEBACK_AMOUNT => $this->approvedChargebackAmount,
            self::DECLINED_CHARGEBACK_COUNT => $this->declinedChargebackCount,
            self::DECLINED_CHARGEBACK_AMOUNT => $this->declinedChargebackAmount,
            self::TOTAL_PAYOUT_COUNT => $this->totalPayoutCount,
            self::PROCESSING_PAYOUT_COUNT => $this->processingPayoutCount,
            self::PROCESSING_PAYOUT_AMOUNT => $this->processingPayoutAmount,
            self::PROCESSING_PAYOUT_AMOUNT_EUR => $this->processingPayoutAmountEur,
            self::APPROVED_PAYOUT_AMOUNT => $this->approvedPayoutAmount,
            self::APPROVED_PAYOUT_AMOUNT_EUR => $this->approvedPayoutAmountEur,
            self::DECLINED_PAYOUT_COUNT => $this->declinedPayoutCount,
            self::DECLINED_PAYOUT_AMOUNT => $this->declinedPayoutAmount,
            self::DECLINED_PAYOUT_AMOUNT_EUR => $this->declinedPayoutAmountEur,
            self::CURRENCY => $this->currency,
            self::EXCHANGE_RATE => $this->exchangeRate,
            self::FX_RATE => $this->fxRate,
        ];
    }
}
