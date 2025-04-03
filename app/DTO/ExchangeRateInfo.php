<?php

namespace App\DTO;

/**
 * Value object representing exchange rate information
 */
class ExchangeRateInfo
{
    /**
     * @param string $currency The currency code
     * @param float $rate The exchange rate value
     * @param string $rateType The rate type (BUY or SELL)
     * @param string|null $cardType Optional card type
     * @param string|null $date Optional rate date
     */
    public function __construct(
        public readonly string $currency,
        public readonly float $rate,
        public readonly string $rateType,
        public readonly ?string $cardType = null,
        public readonly ?string $date = null
    ) {
    }
}
