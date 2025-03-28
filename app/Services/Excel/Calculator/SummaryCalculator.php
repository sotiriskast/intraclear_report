<?php

namespace App\Services\Excel\Calculator;

use Illuminate\Support\Collection;
/**
 * Calculator service for settlement report summaries
 *
 * This service handles:
 * - Calculating totals for various transaction types
 * - Processing fees and reserves
 * - Converting amounts between currencies
 * - Generating final settlement amounts
 */
readonly class SummaryCalculator
{
    /**
     * Calculate total processing amount in original currency
     *
     * @param array $data Transaction data
     * @return float Total processing amount
     */
    public function getTotalProcessingAmount(array $data): float
    {
        return $data['total_sales_amount'] ?? 0;
    }
    /**
     * Calculate total processing amount in EUR
     *
     * @param array $data Transaction data
     * @return float Total processing amount in EUR
     */
    public function getTotalProcessingAmountEur(array $data): float
    {
        return $data['total_sales_amount_eur'] ?? 0;
    }
    /**
     * Calculate total fees in original currency
     *
     * @param array $data Fee data
     * @return float Total fees
     */
    public function getTotalFees(array $data): float
    {
        return $this->calculateFees($data['fees'] ?? [], $data['exchange_rate'] ?? 1);
    }
    /**
     * Calculate total fees in EUR
     *
     * @param array $data Fee data
     * @return float Total fees in EUR
     */
    public function getTotalFeesEur(array $data): float
    {
        return collect($data['fees'] ?? [])->sum('fee_amount');
    }
    /**
     * Calculate total chargebacks in original currency
     *
     * @param array $data Chargeback data
     * @return float Total chargebacks
     */
    public function getTotalChargebacks(array $data): float
    {
        return ($data['total_processing_chargeback_amount'] ?? 0) +
            ($data['total_approved_chargeback_amount'] ?? 0);
    }
    /**
     * Calculate total chargebacks in EUR
     *
     * @param array $data Chargeback data
     * @return float Total chargebacks in EUR
     */
    public function getTotalChargebacksEur(array $data): float
    {
        return ($data['total_processing_chargeback_amount_eur'] ?? 0) +
            ($data['total_approved_chargeback_amount_eur'] ?? 0);
    }
    /**
     * Calculate total refunds in original currency
     *
     * @param array $data Refund data
     * @return float Total refunds
     */
    public function getTotalRefund(array $data): float
    {
        return ($data['total_refunds_amount'] ?? 0);
    }
    /**
     * Calculate total refunds in EUR
     *
     * @param array $data Refund data
     * @return float Total refunds in EUR
     */
    public function getTotalRefundEur(array $data): float
    {
        return ($data['total_refunds_amount_eur'] ?? 0);
    }
    /**
     * Calculate Generated Reserve
     *
     * @param array $data Reserved data
     * @return float Total Rolling reserve
     */
    public function getGeneratedReserve(array $data): float
    {
        return $this->calculateReserve($data['rolling_reserve'] ?? null);
    }
    /**
     * Calculate total Generated Reserved in EUR
     *
     * @param array $data Reserved data in Eur
     * * @return float Total Rolling reserve im Eur
     * */
    public function getGeneratedReserveEur(array $data): float
    {
        return $this->calculateReserveEur($data['rolling_reserve'] ?? null);
    }
    /**
     * Calculate Gross Amount
     *
     * @param array $data data
     * @return float Total Gross Amount
     */
    public function getGrossAmount(array $data): float
    {
        return $this->getTotalProcessingAmount($data) +
            $this->getReleasedReserve($data) -
            $this->getTotalRefund($data) -
            $this->getTotalFees($data) -
            $this->getTotalChargebacks($data) -
            $this->getGeneratedReserve($data);
    }
    /**
     * Calculate Gross Amount in EUR
     *
     * @param array $data data
     * @return float Total Gross Amount in EUR
     */
    public function getGrossAmountEur(array $data): float
    {
        return $this->getTotalProcessingAmountEur($data) +
            $this->getReleasedReserveEur($data) -
            $this->getTotalRefundEur($data) -
            $this->getTotalFeesEur($data) -
            $this->getTotalChargebacksEur($data) -
            $this->getGeneratedReserveEur($data);
    }
    /**
     * Calculate total Statement
     *
     * @param array $data data
     * @return float Total Statement
     */
    public function getStatementTotal(array $data): float
    {
        return $this->getGrossAmount($data);
    }
    /**
     * Calculate total Statement in EUR
     *
     * @param array $data data
     * @return float Total Statement in EUR
     */
    public function getStatementTotalEur(array $data): float
    {
        return $this->getGrossAmountEur($data);
    }
    /**
     * Calculate total Amount
     *
     * @param array $data data
     * @return float Total Amount
     */
    public function getTotalAmount(array $data): float
    {
        return $this->getStatementTotal($data);
    }
    /**
     * Calculate total Amount in Eur
     *
     * @param array $data data
     * @return float Total Amount in EUR
     */
    public function getTotalAmountEur(array $data): float
    {
        return $this->getStatementTotalEur($data);
    }
    /**
     * Calculate total Fx Rate Fee (Now returns 0 as FX Rate functionality is removed)
     *
     * @param array $data data
     * @return float Get Fx Rate Fee
     */
    public function getFxFee(array $data): float
    {
        return 0;
    }
    /**
     * Calculate total Fx Rate Fee in EUR (Now returns 0 as FX Rate functionality is removed)
     *
     * @param array $data data
     * @return float Get Fx Rate Fee in EUR
     */
    public function getFxFeeEur(array $data): float
    {
        return 0;
    }
    /**
     * Calculate total Amount Paid (Now same as TotalAmount since FX fee is removed)
     *
     * @param array $data data
     * @return float Total Amount Paid
     */
    public function getTotalAmountPaid(array $data): float
    {
        return $this->getTotalAmount($data);
    }
    /**
     * Calculate total Amount Paid in EUR (Now same as TotalAmountEur since FX fee is removed)
     *
     * @param array $data data
     * @return float Total Amount Paid in EUR
     */
    public function getTotalAmountPaidEur(array $data): float
    {
        return $this->getTotalAmountEur($data);
    }
    /**
     * Calculate total Release Amount from rolling reserve
     *
     * @param array $data Refund data
     * @return float Total Release Amount from rolling reserve
     */
    public function getReleasedReserve(array $data): float
    {
        return $this->calculateReleasedReserve($data);
    }
    /**
     * Calculate total Release Amount from rolling reserve in EUR
     *
     * @param array $data Refund data
     * @return float Total Release Amount from rolling reserve in EUR
     */
    public function getReleasedReserveEur(array $data): float
    {
        return $this->calculateReleasedReserveEur($data);
    }
    /**
     * Calculate fees based on amount and exchange rate
     *
     * @param array $fees Fee data
     * @param float $rate Exchange rate
     * @return float Calculated fees
     */
    private function calculateFees(array $fees, float $rate): float
    {
        return collect($fees)->sum('fee_amount') / $rate;
    }
    /**
     * Calculate reserve amount
     *
     * @param mixed $reserve Reserve data (Collection or array)
     * @return float Calculated reserve amount
     */
    private function calculateReserve(mixed $reserve): float
    {
        if (empty($reserve)) return 0;

        return $reserve instanceof Collection ?
            $reserve->sum('original_amount') / 100 :
            ($reserve['original_amount'] ?? 0) / 100;
    }
    /**
     * Calculate reserve amount in eur
     *
     * @param mixed $reserve Reserve data (Collection or array)
     * @return float Calculated reserve amount in eur
     */
    private function calculateReserveEur(mixed $reserve): float
    {
        if (empty($reserve)) return 0;

        return $reserve instanceof Collection ?
            $reserve->sum('reserve_amount_eur') / 100 :
            ($reserve['reserve_amount_eur'] ?? 0) / 100;
    }
    /**
     * Calculate release amount
     *
     * @param array $data release data (Collection or array)
     * @return float Calculated reserve amount
     */
    private function calculateReleasedReserve(array $data): float
    {
        if (empty($data['releaseable_reserve'])) return 0;

        return collect($data['releaseable_reserve'])
            ->where('original_currency', $data['currency'])
            ->sum('original_amount');
    }
    /**
     * Calculate reserve amount
     *
     * @param array $data Release data (Collection or array)
     * @return float Calculated release amount in eur
     */
    private function calculateReleasedReserveEur(array $data): float
    {
        if (empty($data['releaseable_reserve'])) return 0;

        return collect($data['releaseable_reserve'])
            ->where('original_currency', $data['currency'])
            ->sum('reserve_amount_eur');
    }
}
