<?php

namespace App\Exceptions;

use Exception;

class MissingSchemeRatesException extends Exception
{
    /**
     * The missing rates' data.
     *
     * @var array
     */
    protected $missingRates;

    /**
     * The date range involved.
     *
     * @var array
     */
    protected $dateRange;

    /**
     * Create a new missing scheme rates exception instance.
     *
     * @param string $message
     * @param array $missingRates
     * @param array $dateRange
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        array $missingRates,
        array $dateRange,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->missingRates = $missingRates;
        $this->dateRange = $dateRange;
    }

    /**
     * Get the missing rates' data.
     *
     * @return array
     */
    public function getMissingRates(): array
    {
        return $this->missingRates;
    }

    /**
     * Get the date range.
     *
     * @return array
     */
    public function getDateRange(): array
    {
        return $this->dateRange;
    }
}
