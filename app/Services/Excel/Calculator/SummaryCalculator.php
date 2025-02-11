<?php

namespace App\Services\Excel\Calculator;

use Illuminate\Support\Collection;

readonly class SummaryCalculator
{
    private const FX_RATE = 0.02; // 2% default rate

    public function getTotalProcessingAmount(array $data): float
    {
        return $data['total_sales_amount'] ?? 0;
    }

    public function getTotalProcessingAmountEur(array $data): float
    {
        return $data['total_sales_amount_eur'] ?? 0;
    }

    public function getTotalFees(array $data): float
    {
        return $this->calculateFees($data['fees'] ?? [], $data['exchange_rate'] ?? 1);
    }

    public function getTotalFeesEur(array $data): float
    {
        return collect($data['fees'] ?? [])->sum('fee_amount');
    }

    public function getTotalChargebacks(array $data): float
    {
        return ($data['total_processing_chargeback_amount'] ?? 0) +
            ($data['total_approved_chargeback_amount'] ?? 0);
    }

    public function getTotalChargebacksEur(array $data): float
    {
        return ($data['total_refunds_amount_eur'] ?? 0) +
            ($data['total_processing_chargeback_amount_eur'] ?? 0) +
            ($data['total_approved_chargeback_amount_eur'] ?? 0);
    }

    public function getTotalRefund(array $data): float
    {
        return ($data['total_refunds_amount'] ?? 0);
    }

    public function getTotalRefundEur(array $data): float
    {
        return ($data['total_refunds_amount_eur'] ?? 0);
    }

    public function getGeneratedReserve(array $data): float
    {
        return $this->calculateReserve($data['rolling_reserve'] ?? null);
    }

    public function getGeneratedReserveEur(array $data): float
    {
        return $this->calculateReserveEur($data['rolling_reserve'] ?? null);
    }

    public function getGrossAmount(array $data): float
    {
        return $this->getTotalProcessingAmount($data) +
            $this->getReleasedReserve($data) +
            $this->getTotalRefund($data) -
            $this->getTotalFees($data) -
            $this->getTotalChargebacks($data) -
            $this->getGeneratedReserve($data);
    }

    public function getGrossAmountEur(array $data): float
    {
        return $this->getTotalProcessingAmountEur($data) +
            $this->getReleasedReserveEur($data) +
            $this->getTotalRefundEur($data) -
            $this->getTotalFeesEur($data) -
            $this->getTotalChargebacksEur($data) -
            $this->getGeneratedReserveEur($data);
    }

    public function getStatementTotal(array $data): float
    {
        return $this->getGrossAmount($data);
    }

    public function getStatementTotalEur(array $data): float
    {
        return $this->getGrossAmountEur($data);
    }

    public function getTotalAmount(array $data): float
    {
        return $this->getStatementTotal($data);
    }

    public function getTotalAmountEur(array $data): float
    {
        return $this->getStatementTotalEur($data);
    }

    public function getFxFee(array $data): float
    {
        return $this->getTotalAmount($data) * self::FX_RATE;
    }

    public function getFxFeeEur(array $data): float
    {
        return $this->getTotalAmountEur($data) * self::FX_RATE;
    }

    public function getTotalAmountPaid(array $data): float
    {
        return $this->getTotalAmount($data) - $this->getFxFee($data);
    }

    public function getTotalAmountPaidEur(array $data): float
    {
        return $this->getTotalAmountEur($data) - $this->getFxFeeEur($data);
    }

    public function getReleasedReserve(array $data): float
    {
        return $this->calculateReleasedReserve($data);
    }

    public function getReleasedReserveEur(array $data): float
    {
        return $this->calculateReleasedReserveEur($data);
    }

    private function calculateFees(array $fees, float $rate): float
    {
        return collect($fees)->sum('fee_amount') / $rate;
    }

    private function calculateReserve($reserve): float
    {
        if (empty($reserve)) return 0;

        return $reserve instanceof Collection ?
            $reserve->sum('original_amount') / 100 :
            ($reserve['original_amount'] ?? 0) / 100;
    }

    private function calculateReserveEur($reserve): float
    {
        if (empty($reserve)) return 0;

        return $reserve instanceof Collection ?
            $reserve->sum('reserve_amount_eur') / 100 :
            ($reserve['reserve_amount_eur'] ?? 0) / 100;
    }

    private function calculateReleasedReserve(array $data): float
    {
        if (empty($data['releaseable_reserve'])) return 0;

        return collect($data['releaseable_reserve'])
            ->where('original_currency', $data['currency'])
            ->sum('original_amount');
    }

    private function calculateReleasedReserveEur(array $data): float
    {
        if (empty($data['releaseable_reserve'])) return 0;

        return collect($data['releaseable_reserve'])
            ->where('original_currency', $data['currency'])
            ->sum('reserve_amount_eur');
    }
}
