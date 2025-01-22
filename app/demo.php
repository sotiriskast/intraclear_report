<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Demo
{
    public function getFromDb()
    {

        try {
            $startTime = microtime(true);
            $allResults = [];
//            $merchantIds = [8026, 8027, 8028,8003,8004];
            $merchantIds = DB::connection('mysql')->table('account')->where('active',1)->pluck('id')->toArray();

            $startDate = '2024-11-11'; // Your start date
            $endDate = '2024-11-28';   // Your end date

            // Or using Carbon for more flexibility
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            // Get all data in one query for better performance


            $accounts = DB::connection('mysql')->table('account')
                ->select([
                    'account.id as account_id',
                    'account.corp_name',
                    'shop.id as shop_id',
                    'shop.owner_name as shop_owner_name',
                    'transactions.currency',
                    'customer_card.card_type',
//                    'scheme_rates.sell as exchange_rate',
                    'transactions.added', // Add the raw added field
                    DB::raw('DATE(transactions.added) as transaction_date'), // Add this line

                    DB::raw("
            SUM(CASE
                WHEN transaction_type = 'sale' AND transaction_status = 'APPROVED'
                THEN amount ELSE 0
            END) as total_sales_amount"),
                    DB::raw("
            SUM(CASE
                WHEN transaction_type = 'sale' AND transaction_status = 'DECLINED'
                THEN amount ELSE 0
            END) as total_declined_amount"),
                    DB::raw("
            SUM(CASE
                WHEN transaction_type IN ('Refund', 'Partial Refund') AND transaction_status = 'APPROVED'
                THEN amount ELSE 0
            END) as total_refunds_amount"),
                    // EUR converted amounts
//                    DB::raw("
//            SUM(CASE
//                WHEN transaction_type = 'sale' AND transaction_status = 'APPROVED'
//                THEN
//                    CASE
//                        WHEN transactions.currency = 'EUR' THEN amount
//                        ELSE amount * COALESCE(scheme_rates.sell, 1)
//                    END
//                ELSE 0
//            END) as total_sales_amount_eur"),
//                    DB::raw("
//            SUM(CASE
//                WHEN transaction_type = 'sale' AND transaction_status = 'DECLINED'
//                THEN
//                    CASE
//                        WHEN transactions.currency = 'EUR' THEN amount
//                        ELSE amount * COALESCE(scheme_rates.sell, 1)
//                    END
//                ELSE 0
//            END) as total_declined_amount_eur"),
//                    DB::raw("
//            SUM(CASE
//                WHEN transaction_type IN ('Refund', 'Partial Refund') AND transaction_status = 'APPROVED'
//                THEN
//                    CASE
//                        WHEN transactions.currency = 'EUR' THEN amount
//                        ELSE amount * COALESCE(scheme_rates.sell, 1)
//                    END
//                ELSE 0
//            END) as total_refunds_amount_eur"),
                    // Transaction counts
                    DB::raw("
            COUNT(CASE
                WHEN transaction_type = 'sale' AND transaction_status = 'APPROVED'
                THEN 1
            END) as total_sales_transaction"),
                    DB::raw("
            COUNT(CASE
                WHEN transaction_type IN ('Refund', 'Partial Refund') AND transaction_status = 'APPROVED'
                THEN 1
            END) as total_refunds_transaction"),
                    DB::raw("
            COUNT(CASE
                WHEN transaction_status = 'DECLINED'
                THEN 1
            END) as total_declined_transaction")
                ])
                ->leftJoin('shop', 'account.id', '=', 'shop.account_id')
                ->leftJoin('transactions', function ($join) {
                    $join->on('account.id', '=', 'transactions.account_id')
                        ->on('shop.id', '=', 'transactions.shop_id');
                })
                ->leftJoin('customer_card', 'transactions.card_id', '=', 'customer_card.card_id')
//                ->leftJoin('scheme_rates', function ($join) {
//                    $join->on('transactions.currency', '=', 'scheme_rates.from_currency')
//                        ->whereColumn('customer_card.card_type', '=', 'scheme_rates.brand')
//                        ->whereRaw('DATE(transactions.added) = DATE(scheme_rates.added)')
//                        ->where('scheme_rates.to_currency', '=', 'EUR');
//                })
                ->whereIn('account.id', $merchantIds)
                ->where('account.active', 1)
                ->whereBetween('transactions.added', [$startDate, $endDate])
                ->groupBy([
                    'account.id',
                    'account.corp_name',
                    'shop.id',
                    'shop.owner_name',
                    'transactions.currency',
                    'customer_card.card_type',
//                    'scheme_rates.sell',
                ])
                ->orderBy('account.id')
                ->orderBy('shop.id')
                ->orderBy('transactions.currency')
                ->get();

            // Process results
            // Get unique currencies
            $currencies = $accounts->pluck('currency')
                ->filter()
                ->unique()
                ->toArray();

            // Get exchange rates
            $exchangeRates = $this->getTransactionExchangeRates($startDate, $endDate, $currencies);

            $organized = $accounts->groupBy('account_id')->map(function ($merchantData, $merchantId) use ($exchangeRates) {
                if ($merchantData->isEmpty()) {
                    return null;
                }
                return $merchantData->groupBy('shop_id')->map(function ($shopData) use ($exchangeRates) {
                    $firstShop = $shopData->first();

                    return [
                        'account_id' => $firstShop->account_id,
                        'corp_name' => $firstShop->corp_name,
                        'shop_id' => $firstShop->shop_id,
                        'shop_owner_name' => $firstShop->shop_owner_name,
                        'transactions_by_currency' => $shopData->groupBy('currency')->map(function ($currencyData) use ($exchangeRates) {
                            $currency = $currencyData->first()->currency;

                            $totalSalesEur = 0;
                            $totalDeclinedEur = 0;
                            $totalRefundsEur = 0;

                            foreach ($currencyData as $data) {
                                if ($currency === 'EUR') {
                                    $rate = 1;
                                } else {
                                    $transactionDate = Carbon::parse($data->transaction_date)->format('Y-m-d');

                                    // Ensure we have a card type
                                    if (empty($data->card_type)) {
                                        \Log::error("Missing card_type for transaction on {$transactionDate}, currency: {$currency}");
                                        continue;
                                    }

                                    // Normalize card type to match scheme_rates.brand format
                                    $normalizedCardType = strtoupper($data->card_type);
                                    $rateKey = $currency . '_' . $normalizedCardType . '_' . $transactionDate;

                                    // Debug log
                                    \Log::debug("Looking for rate with key: {$rateKey}");

                                    if (!isset($exchangeRates[$rateKey])) {
                                        \Log::error("Missing exchange rate for {$currency} {$normalizedCardType} on {$transactionDate}");
                                        continue;
                                    }

                                    $rate = $exchangeRates[$rateKey];

                                    // Log the rate being used
                                    \Log::debug("Using rate {$rate} for {$currency} {$normalizedCardType} on {$transactionDate}");
                                }

                                // Apply rates to amounts
                                $totalSalesEur += $data->total_sales_amount * $rate;
                                $totalDeclinedEur += $data->total_declined_amount * $rate;
                                $totalRefundsEur += $data->total_refunds_amount * $rate;
                            }

                            return [
                                'currency' => $currency,
                                // Original currency amounts
                                'total_sales_amount' => $currencyData->sum('total_sales_amount') / 100,
                                'total_declined_amount' => $currencyData->sum('total_declined_amount') / 100,
                                'total_refunds_amount' => $currencyData->sum('total_refunds_amount') / 100,
                                // EUR converted amounts
                                'total_sales_amount_eur' => $totalSalesEur / 100,
                                'total_declined_amount_eur' => $totalDeclinedEur / 100,
                                'total_refunds_amount_eur' => $totalRefundsEur / 100,
                                // Transaction counts
                                'total_sales_transaction' => $currencyData->sum('total_sales_transaction'),
                                'total_refunds_transaction' => $currencyData->sum('total_refunds_transaction'),
                                'total_declined_transaction' => $currencyData->sum('total_declined_transaction'),
                                // Daily rates used
                                'daily_rates' => $currencyData->map(function($data) use ($exchangeRates, $currency) {
                                    if ($currency === 'EUR') {
                                        return [
                                            'date' => $data->transaction_date,
                                            'rate' => 1
                                        ];
                                    }
                                    $rateKey = $currency . '_' . $data->card_type . '_' . $data->transaction_date;
                                    $rate = $exchangeRates[$rateKey] ?? 1;
                                    return [
                                        'date' => $data->transaction_date,
                                        'rate' => $rate
                                    ];
                                })->unique('date')->values()
                            ];
                        })->values()
                    ];
                })->values();
            });


            foreach ($merchantIds as $i => $merchantId) {
                $allResults[$i + 1] = $organized[$merchantId] ?? [];
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime);

            return response()->json([
                'status' => 'success',
                'execution_time' => $executionTime . ' seconds',
                'execution_time_ms' => ($executionTime * 1000) . ' milliseconds',
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'data' => $organized
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTransactionExchangeRates($startDate, $endDate, $currencies)
    {
        try {
            $dailyRates = DB::connection('mysql')
                ->table('scheme_rates')
                ->select([
                    'from_currency',
                    'brand',  // This corresponds to card_type (VISA/MASTERCARD)
                    'sell as rate',
                    DB::raw('DATE(added) as rate_date')
                ])
                ->whereIn('from_currency', $currencies)
                ->where('to_currency', 'EUR')
                ->whereBetween('added', [$startDate, $endDate])
                ->orderBy('added')
                ->get();

            // Debug log to check rates
            \Log::info('Fetched rates:', [
                'count' => $dailyRates->count(),
                'sample' => $dailyRates->take(2)
            ]);

            // Create lookup array with brand-specific keys
            $ratesByDay = [];
            foreach ($dailyRates as $rate) {
                // Normalize card brand name to match customer_card.card_type
                $normalizedBrand = strtoupper($rate->brand); // Ensure consistent case
                $key = $rate->from_currency . '_' . $normalizedBrand . '_' . $rate->rate_date;
                $ratesByDay[$key] = $rate->rate;

                // Log each rate for debugging
                \Log::debug("Rate stored: {$key} = {$rate->rate}");
            }

            return $ratesByDay;
        } catch (\Exception $e) {
            \Log::error('Error fetching exchange rates: ' . $e->getMessage());
            return [];
        }
    }
    private function applyExchangeRate($amount, $rate)
    {
        if ($amount == 0) return 0;
        return $amount * $rate;
    }

}
