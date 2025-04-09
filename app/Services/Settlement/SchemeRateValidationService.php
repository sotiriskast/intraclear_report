<?php

namespace App\Services\Settlement;

use App\Exceptions\MissingSchemeRatesException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MissingSchemeRatesAlert;
use App\Services\DynamicLogger;

class SchemeRateValidationService
{
    /**
     * Card brands that must be present for each currency
     */
    private const array REQUIRED_CARD_BRANDS = ['VISA', 'MASTERCARD'];

    /**
     * @var DynamicLogger
     */
    private $logger;

    /**
     * @param DynamicLogger $logger
     */
    public function __construct(DynamicLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validate scheme rates for the given date range and currencies
     *
     * @param array $dateRange Associative array with 'start' and 'end' date keys
     * @param array $currencies List of currency codes to validate
     * @param string|array|null $recipients Email recipients for alerts (null uses config)
     * @return array Validation result with status and missing dates
     */
    public function validateSchemeRates(array $dateRange, array $currencies, $recipients = null): array
    {
        try {
            $startDate = Carbon::parse($dateRange['start'])->startOfDay();
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();

            // Generate a list of all dates in the range
            $allDates = collect(CarbonPeriod::create($startDate, $endDate)->toArray())
                ->map(fn($date) => $date->format('Y-m-d'))
                ->toArray();

            $missingRates = [];

            foreach ($currencies as $currency) {
                if ($currency === 'EUR') {
                    continue; // Skip EUR as it always has a rate of 1.0
                }

                $currencyMissing = [];

                // Check for each required card brand
                foreach (self::REQUIRED_CARD_BRANDS as $cardBrand) {
                    // Get dates that have scheme rates for this currency and card brand
                    // Note: payment_gateway_mysql will always be MariaDB, so we don't need the conditional logic here
                    $existingDates = DB::connection('payment_gateway_mysql')
                        ->table('scheme_rates')
                        ->select(DB::raw('DISTINCT DATE(added) as rate_date'))
                        ->where('from_currency', $currency)
                        ->where('to_currency', 'EUR')
                        ->where('brand', $cardBrand)
                        ->whereBetween('added', [$startDate, $endDate])
                        ->pluck('rate_date')
                        ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                        ->toArray();

                    // Find missing dates for this card brand
                    $missingDates = array_diff($allDates, $existingDates);

                    if (!empty($missingDates)) {
                        $currencyMissing[$cardBrand] = $missingDates;
                    }
                }

                if (!empty($currencyMissing)) {
                    $missingRates[$currency] = $currencyMissing;
                }
            }

            // If there are missing rates, send an alert and throw exception
            if (!empty($missingRates)) {
                $this->sendMissingRatesAlert($missingRates, $dateRange, $recipients);

                // Format error message
                $missingDetails = [];
                foreach ($missingRates as $curr => $brandData) {
                    $brandDetails = [];
                    foreach ($brandData as $brand => $dates) {
                        $brandDetails[] = $brand . " (" . count($dates) . " dates missing)";
                    }
                    $missingDetails[] = $curr . ": " . implode(", ", $brandDetails);
                }

                $errorMessage = "Missing scheme rates detected: " . implode("; ", $missingDetails);

                throw new MissingSchemeRatesException(
                    $errorMessage,
                    $missingRates,
                    $dateRange
                );
            }

            return [
                'status' => true,
                'missing_rates' => []
            ];

        } catch (MissingSchemeRatesException $e) {
            // Re-throw the missing scheme rates exception
            throw $e;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to validate scheme rates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'date_range' => $dateRange,
                'currencies' => $currencies
            ]);

            throw $e;
        }
    }
    /**
     * Send alert email for missing scheme rates
     *
     * @param array $missingRates Array of missing rates by currency and card brand
     * @param array $dateRange Date range that was validated
     * @param array|string|null $recipients Email recipients (null uses config)
     */
    private function sendMissingRatesAlert(array $missingRates, array $dateRange, array|string|null $recipients = null): void
    {
        try {
            // Get recipients from config if not specified
            if ($recipients === null) {
                $recipients = collect(config('settlement.report_recipients', []))
                    ->filter()
                    ->values()
                    ->toArray();
            }

            if (empty($recipients)) {
                $this->logger->log('warning', 'No recipients configured for missing scheme rates alert');
                return;
            }

            Mail::to($recipients)->send(new MissingSchemeRatesAlert(
                $missingRates,
                $dateRange
            ));

            $this->logger->log('info', 'Sent missing scheme rates alert', [
                'recipients' => $recipients,
                'currencies' => array_keys($missingRates),
                'date_range' => $dateRange
            ]);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to send missing scheme rates alert', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
