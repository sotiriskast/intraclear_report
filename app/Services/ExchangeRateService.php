<?php

namespace App\Services;

use App\DTO\ExchangeRateInfo;
use App\Enums\TransactionTypeEnum;
use App\Repositories\MerchantRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service class responsible for all exchange rate operations
 */
class ExchangeRateService
{
    /**
     * Default exchange rate markup if not found in settings
     */
    private const float DEFAULT_EXCHANGE_RATE_MARKUP = 1.01;

    /**
     * Default FX rate value
     */
    private const int DEFAULT_FX_RATE = 0;

    /**
     * Currency-specific precision settings
     */
    private const array CURRENCY_PRECISION = [
        'JPY' => 4,
        'DEFAULT' => 6
    ];

    /**
     * Create a new ExchangeRateService instance
     */
    public function __construct(
        private readonly DynamicLogger      $logger,
        private readonly MerchantRepository $merchantRepository
    ) {
    }

    /**
     * Get merchant-specific rate markup by type
     *
     * @param int $merchantId The merchant ID
     * @param string $markupType Type of markup ('exchange_rate' or 'fx_rate')
     * @return float|int The markup value
     */
    public function getMerchantRateMarkup(int $merchantId, string $markupType): float|int
    {
        try {
            $internalMerchantId = $this->merchantRepository->getMerchantIdByAccountId($merchantId);

            $column = match ($markupType) {
                'exchange_rate' => 'exchange_rate_markup',
                'fx_rate' => 'fx_rate_markup',
                default => throw new \InvalidArgumentException("Invalid markup type: $markupType")
            };

            $markup = DB::table('merchant_settings')
                ->where('merchant_id', $internalMerchantId)
                ->value($column);

            return $markup ?? match ($markupType) {
                'exchange_rate' => self::DEFAULT_EXCHANGE_RATE_MARKUP,
                'fx_rate' => self::DEFAULT_FX_RATE,
                default => throw new \InvalidArgumentException("Invalid markup type: $markupType")
            };
        } catch (Exception $e) {
            $this->logger->log('warning', "Failed to retrieve {$markupType} markup, using default", [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);

            return match ($markupType) {
                'exchange_rate' => self::DEFAULT_EXCHANGE_RATE_MARKUP,
                'fx_rate' => self::DEFAULT_FX_RATE,
                default => throw new \InvalidArgumentException("Invalid markup type: $markupType")
            };
        }
    }

    /**
     * Get the appropriate decimal precision for a currency
     *
     * @param string $currency The currency code
     * @return int The precision to use
     */
    public function getPrecisionForCurrency(string $currency): int
    {
        return self::CURRENCY_PRECISION[$currency] ?? self::CURRENCY_PRECISION['DEFAULT'];
    }

    /**
     * Retrieves exchange rates for specified currencies within a date range.
     *
     * @param array $dateRange Associative array with 'start' and 'end' date keys
     * @param array $currencies Array of currency codes to fetch rates for
     * @return array Associative array of exchange rates
     * @throws Exception
     */
    public function getExchangeRates(array $dateRange, array $currencies): array
    {
        try {
            $rates = DB::connection('payment_gateway_mysql')
                ->table('scheme_rates')
                ->select([
                    'from_currency',
                    'brand',
                    'buy',
                    'sell',
                    DB::raw('DATE(added) as rate_date'),
                ])
                ->whereIn('from_currency', $currencies)
                ->where('to_currency', 'EUR')
                ->whereBetween('added', [$dateRange['start'], $dateRange['end']])
                ->orderBy('brand', 'desc')
                ->get();

            // Create lookup array with formatted keys
            return $this->formatExchangeRates($rates);
        } catch (Exception $e) {
            $this->logger->log('error', 'Failed to retrieve exchange rates', [
                'currencies' => $currencies,
                'date_range' => $dateRange,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Format exchange rates into a lookup array
     *
     * @param Collection $rates Collection of rate records
     * @return array Formatted lookup array
     */
    private function formatExchangeRates(Collection $rates): array
    {
        $rateMap = [];

        foreach ($rates as $rate) {
            $key = $rate->from_currency . '_' . strtoupper($rate->brand) . '_' . $rate->rate_date;
            $rateMap['BUY_' . $key] = $rate->buy;
            $rateMap['SELL_' . $key] = $rate->sell;
        }

        return $rateMap;
    }

    /**
     * Determines the daily exchange rate for a specific transaction.
     * Uses 'BUY' rate for sales and 'SELL' rate for refunds/chargebacks.
     *
     * @param mixed $transaction Transaction record
     * @param array $exchangeRates Lookup of exchange rates
     * @return float Exchange rate for the transaction
     */
    public function getDailyExchangeRate(mixed $transaction, array $exchangeRates): float
    {
        // EUR always has a rate of 1.0
        if ($transaction->currency === 'EUR') {
            return 1.0;
        }

        // Normalize transaction amount if needed
        $amount = is_string($transaction->amount) ? (float)$transaction->amount : $transaction->amount;

        // Get transaction date
        $date = Carbon::parse($transaction->added)->format('Y-m-d');
        $transactionType = mb_strtoupper($transaction->transaction_type);

        // Determine rate type based on transaction type
        $rateType = $this->determineRateType($transactionType);

        // Try to get rate for specific card type
        if (!empty($transaction->card_type)) {
            $cardType = strtoupper($transaction->card_type);
            $key = $rateType . "{$transaction->currency}_{$cardType}_{$date}";

            if (isset($exchangeRates[$key])) {
                return $exchangeRates[$key];
            }

            $this->logger->log('debug', 'No specific exchange rate found for card type', [
                'currency' => $transaction->currency,
                'card_type' => $cardType,
                'date' => $date
            ]);
        }

        // Try fallback strategies
        return $this->getFallbackRate($transaction->currency, $date, $rateType, $exchangeRates, $transaction->tid ?? 'unknown');
    }

    /**
     * Determine the rate type (BUY/SELL) based on transaction type
     *
     * @param string $transactionType The transaction type
     * @return string The rate type prefix
     */
    private function determineRateType(string $transactionType): string
    {
        return match ($transactionType) {
            TransactionTypeEnum::REFUND->value,
            TransactionTypeEnum::PARTIAL_REFUND->value,
            TransactionTypeEnum::CHARGEBACK->value => 'BUY_',
            default => 'SELL_'
        };
    }

    /**
     * Get fallback exchange rate when specific card type rate is not available
     *
     * @param string $currency Currency code
     * @param string $date Transaction date
     * @param string $rateType Rate type (BUY_/SELL_)
     * @param array $exchangeRates Exchange rates lookup
     * @param string $transactionId Transaction ID for logging
     * @return float Fallback exchange rate
     */
    private function getFallbackRate(
        string $currency,
        string $date,
        string $rateType,
        array  $exchangeRates,
        string $transactionId
    ): float {
        // First fallback: try any card type for this currency and date
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_contains($rateKey, "{$currency}_") &&
                str_contains($rateKey, "_{$date}") &&
                str_starts_with($rateKey, $rateType)) {
                return $rate;
            }
        }

        // Second fallback: try any rate for this currency and rate type
        foreach ($exchangeRates as $rateKey => $rate) {
            if (str_starts_with($rateKey, $rateType . $currency . '_')) {
                return $rate;
            }
        }

        // Last resort fallback
        $this->logger->log('warning', 'Using default exchange rate (1.0) for transaction', [
            'currency' => $currency,
            'date' => $date,
            'transaction_id' => $transactionId,
        ]);

        return 1.0;
    }
}