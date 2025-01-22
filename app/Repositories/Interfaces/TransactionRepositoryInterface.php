<?php

namespace App\Repositories\Interfaces;

interface TransactionRepositoryInterface
{
    public function getMerchantTransactions(int $merchantId, array $dateRange, string $currency = null);
    public function getExchangeRates(array $dateRange, array $currencies);
}
