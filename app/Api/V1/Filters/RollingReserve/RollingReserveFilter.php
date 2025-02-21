<?php

namespace App\Api\V1\Filters\RollingReserve;

use App\Api\V1\Filters\QueryFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RollingReserveFilter extends QueryFilter
{
    /**
     * List of valid filter parameters and their expected types
     */
    private const array ALLOWED_FILTERS = [
        'status' => 'string',
        'currency' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'per_page' => 'integer'
    ];

    public function apply(array $filters): Builder
    {
        // Check for unknown parameters
        $unknownParams = array_diff(array_keys($filters), array_keys(self::ALLOWED_FILTERS));
        if (!empty($unknownParams)) {
            $this->logger->log('warning', 'Unknown filter parameters received', [
                'unknown_parameters' => $unknownParams,
                'received_filters' => $filters
            ]);
        }

        foreach ($filters as $key => $value) {
            // Skip if parameter is not in allowed list
            if (!isset(self::ALLOWED_FILTERS[$key])) {
                continue;
            }

            try {
                match ($key) {
                    'status' => $this->applyStatusFilter($value),
                    'currency' => $this->applyCurrencyFilter($value),
                    'start_date' => $this->applyStartDateFilter($value),
                    'end_date' => $this->applyEndDateFilter($value),
                    default => null
                };
            } catch (\Exception $e) {
                $this->logger->log('warning','Error applying filter', [
                    'filter' => $key,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
                // Continue with other filters even if one fails
                continue;
            }
        }

        return $this->query;
    }

    private function applyStatusFilter(string $value): void
    {
        // Convert to lowercase for case-insensitive comparison
        $status = Str::lower($value);
        if (in_array($status, ['pending', 'released'])) {
            $this->query->where('status', $status);
        }
    }

    private function applyCurrencyFilter(string $value): void
    {
        // Convert to uppercase for consistency
        $currency = Str::upper($value);
        if (strlen($currency) === 3) {
            $this->query->where('original_currency', $currency);
        }
    }

    private function applyStartDateFilter(string $value): void
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            $this->query->where('period_start', '>=', $date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid start_date format: $value");
        }
    }

    private function applyEndDateFilter(string $value): void
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->endOfDay();
            $this->query->where('period_end', '<=', $date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid end_date format: $value");
        }
    }
}
