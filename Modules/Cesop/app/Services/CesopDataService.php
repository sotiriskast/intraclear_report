<?php

namespace Modules\Cesop\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Shared data service for CESOP operations
 * Contains common data retrieval and processing methods
 */
class CesopDataService
{
    /**
     * EU country codes list
     * @var array
     */
    protected $euCountries;

    /**
     * Current reporting quarter
     * @var int
     */
    protected $quarter;

    /**
     * Current reporting year
     * @var int
     */
    protected $year;

    /**
     * Threshold for reportable transactions
     * @var int
     */
    protected $threshold;

    /**
     * PSP data for the report
     * @var array
     */
    protected $pspData;

    /**
     * Progress bar instance
     * @var ProgressBar|null
     */
    protected $progressBar;

    /**
     * Constructor
     */
    public function __construct(?int $quarter = null, ?int $year = null, int $threshold = 25, ?array $pspData = null, ?ProgressBar $progressBar = null)
    {
        // Set unlimited execution time for this process
        set_time_limit(0);

        $this->euCountries = config('cesop.eu_countries', [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ]);

        // Set quarter and year (default to current if not provided)
        $now = Carbon::now();
        $this->quarter = $quarter ?? ceil($now->month / 3);
        $this->year = $year ?? $now->year;

        $this->threshold = $threshold;
        $this->pspData = $pspData ?? $this->loadDefaultPspData();
        $this->progressBar = $progressBar;
    }

    /**
     * Get qualifying cards that exceed the threshold
     * Exclude domestic transactions (where merchant country matches card country)
     */
    public function getQualifyingCards(
        Carbon $startDate,
        Carbon $endDate,
        array  $merchantIds = [],
        array  $shopIds = []
    ): array
    {
        // First get all the merchant countries we need to analyze
        $merchantCountryMap = [];

        // If specific merchant IDs are provided, get their countries
        if (!empty($merchantIds)) {
            $merchantsData = DB::connection('pgsql')
                ->table('merchants')
                ->select('account_id', 'iso_country_code')
                ->whereIn('account_id', $merchantIds)
                ->get();

            foreach ($merchantsData as $merchant) {
                if (!empty($merchant->iso_country_code)) {
                    $merchantCountryMap[$merchant->account_id] = strtoupper($merchant->iso_country_code);
                }
            }

            // If any merchants weren't found in PostgreSQL, try the payment gateway MySQL
            $missingMerchantIds = array_diff($merchantIds, array_keys($merchantCountryMap));
            if (!empty($missingMerchantIds)) {
                $fallbackMerchants = DB::connection('payment_gateway_mysql')
                    ->table('account as a')
                    ->leftJoin('account_details as ad', 'a.id', '=', 'ad.account_id')
                    ->select('a.id as account_id', 'ad.country')
                    ->whereIn('a.id', $missingMerchantIds)
                    ->get();

                foreach ($fallbackMerchants as $merchant) {
                    if (!empty($merchant->country)) {
                        $merchantCountryMap[$merchant->account_id] = strtoupper($merchant->country);
                    }
                }
            }
        }

        Log::info('Merchant country map generated', [
            'map' => $merchantCountryMap
        ]);

        // Build the query to find qualifying cards - using composite key for unique cards
        $qualifyingCardsQuery = DB::connection('payment_gateway_mysql')
            ->table('transactions as t')
            ->join('customer_card as cc', 't.card_id', '=', 'cc.card_id')
            ->join('binbase as bb', 'cc.first6', '=', 'bb.bin')
            ->join('shop as s', 't.shop_id', '=', 's.id')
            ->select(
                's.account_id as merchant_id',
                's.id as shop_id',
                // Create a composite identifier for unique physical cards
                'cc.customer_id',
                'cc.first6',
                'cc.last4',
                'cc.c_holder_name',
                'cc.c_expire_month',
                'cc.c_expire_year',
                'bb.isoa2 as card_country',
                DB::raw("COUNT(*) as transaction_count"),
                // Keep one card_id for reference (using MIN to get a consistent one)
                DB::raw("MIN(cc.card_id) as representative_card_id")
            )
            ->whereBetween('t.added', [$startDate, $endDate])
            ->where('t.transaction_status', 'APPROVED')
            ->whereIn('t.transaction_type', ['Sale', 'Refund'])
            ->whereIn('bb.isoa2', $this->euCountries)
            // Ensure we have the required fields for grouping
            ->whereNotNull('cc.customer_id')
            ->whereNotNull('cc.first6')
            ->whereNotNull('cc.last4')
            ->whereNotNull('cc.c_holder_name')
            ->whereNotNull('cc.c_expire_month')
            ->whereNotNull('cc.c_expire_year');

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $qualifyingCardsQuery->whereIn('s.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $qualifyingCardsQuery->whereIn('s.id', $shopIds);
        }

        // Group by the composite key that uniquely identifies a physical card
        $potentialQualifyingCards = $qualifyingCardsQuery->groupBy([
            's.account_id',
            's.id',
            'cc.customer_id',
            'cc.first6',
            'cc.last4',
            'cc.c_holder_name',
            'cc.c_expire_month',
            'cc.c_expire_year',
            'bb.isoa2'
        ])
            ->havingRaw("COUNT(*) >= {$this->threshold}")
            ->get();

        Log::info('Found potential qualifying cards', [
            'count' => count($potentialQualifyingCards)
        ]);

        // Filter out domestic transactions and collect card characteristics
        $qualifyingCardCharacteristics = [];
        foreach ($potentialQualifyingCards as $card) {
            // Skip if we have merchant country info and it matches the card country (domestic transaction)
            if (
                isset($merchantCountryMap[$card->merchant_id]) &&
                $merchantCountryMap[$card->merchant_id] === $card->card_country
            ) {
                Log::info('Excluding domestic transaction', [
                    'merchant_id' => $card->merchant_id,
                    'merchant_country' => $merchantCountryMap[$card->merchant_id],
                    'card_country' => $card->card_country,
                    'customer_id' => $card->customer_id,
                    'first6' => $card->first6,
                    'last4' => $card->last4,
                    'holder_name' => $card->c_holder_name,
                    'expire_month' => $card->c_expire_month,
                    'expire_year' => $card->c_expire_year,
                    'transaction_count' => $card->transaction_count
                ]);
                continue;
            }

            // Store the card characteristics instead of card_ids
            $qualifyingCardCharacteristics[] = [
                'customer_id' => $card->customer_id,
                'first6' => $card->first6,
                'last4' => $card->last4,
                'c_holder_name' => $card->c_holder_name,
                'c_expire_month' => $card->c_expire_month,
                'c_expire_year' => $card->c_expire_year,
                'transaction_count' => $card->transaction_count
            ];

            Log::info('Including qualifying card', [
                'merchant_id' => $card->merchant_id,
                'customer_id' => $card->customer_id,
                'first6' => $card->first6,
                'last4' => $card->last4,
                'holder_name' => $card->c_holder_name,
                'expire_month' => $card->c_expire_month,
                'expire_year' => $card->c_expire_year,
                'transaction_count' => $card->transaction_count
            ]);
        }

        Log::info('Final qualifying cards after excluding domestic transactions', [
            'count' => count($qualifyingCardCharacteristics)
        ]);

        return $qualifyingCardCharacteristics;
    }

    /**
     * Process merchant data based on qualifying cards
     */
    public function processMerchantData(array $qualifyingCardCharacteristics, array $merchantIds = []): array
    {
        if (empty($qualifyingCardCharacteristics)) {
            return [];
        }

        $cardIdSubquery = DB::connection('payment_gateway_mysql')
            ->table('customer_card as cc')
            ->select('cc.card_id')
            ->where(function ($q) use ($qualifyingCardCharacteristics) {
                foreach ($qualifyingCardCharacteristics as $card) {
                    $q->orWhere(function ($subQ) use ($card) {
                        $subQ->where('cc.customer_id', $card['customer_id'])
                            ->where('cc.first6', $card['first6'])
                            ->where('cc.last4', $card['last4'])
                            ->where('cc.c_holder_name', $card['c_holder_name'])
                            ->where('cc.c_expire_month', $card['c_expire_month'])
                            ->where('cc.c_expire_year', $card['c_expire_year']);
                    });
                }
            });

        // Get all merchant IDs from qualifying cards
        $cardMerchantQuery = DB::connection('payment_gateway_mysql')
            ->table('transactions as t')
            ->join('shop as s', 't.shop_id', '=', 's.id')
            ->whereIn('t.card_id', $cardIdSubquery)
            ->distinct()
            ->select('s.account_id');

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $cardMerchantQuery->whereIn('s.account_id', $merchantIds);
        }

        $accountIds = $cardMerchantQuery->pluck('account_id')->toArray();

        // Fetch merchant details from PostgreSQL
        $merchants = DB::connection('pgsql')
            ->table('merchants')
            ->select(
                'id',
                'account_id',
                'name',
                'email',
                'legal_name',
                'street',
                'city',
                'postcode',
                'vat',
                'mcc1',
                'iso_country_code'
            )
            ->whereIn('account_id', $accountIds)
            ->get();

        // Create a lookup map by account_id
        $merchantMap = [];
        foreach ($merchants as $merchant) {
            // Attempt to get IBAN from database
            $iban = $this->getMerchantIban($merchant->account_id);

            $merchantMap[$merchant->account_id] = [
                'id' => $merchant->id,
                'account_id' => $merchant->account_id,
                'name' => $merchant->name ?? $merchant->legal_name,
                'email' => $merchant->email,
                'address' => $merchant->street,
                'city' => $merchant->city,
                'postal_code' => $merchant->postcode,
                'mcc1' => $merchant->mcc1,
                'iso_country' => $merchant->iso_country_code,
                'vat' => $merchant->vat,
                'iban' => $iban
            ];
        }

        // For any merchants not found in pgsql, try payment_gateway_mysql
        $missingMerchantIds = array_diff($accountIds, array_keys($merchantMap));
        if (!empty($missingMerchantIds)) {
            foreach ($missingMerchantIds as $accountId) {
                $fallbackMerchant = $this->getMerchantFromPaymentGateway($accountId);
                if ($fallbackMerchant) {
                    $merchantMap[$accountId] = $fallbackMerchant;
                }
            }
        }

        return $merchantMap;
    }

    /**
     * Get transaction data for the report (individual transactions, not aggregated)
     * Using the same composite key approach as Excel service
     */
    public function getTransactionsData(
        Carbon $startDate,
        Carbon $endDate,
        array  $qualifyingCardCharacteristics,
        array  $merchantIds = [],
        array  $shopIds = []
    ): Collection
    {
        if (empty($qualifyingCardCharacteristics)) {
            return collect();
        }

        // Create composite keys for qualifying cards
        $compositeKeys = [];
        foreach ($qualifyingCardCharacteristics as $card) {
            // Create a unique key by concatenating all card attributes
            $key = $card['customer_id'] . '|' .
                $card['first6'] . '|' .
                $card['last4'] . '|' .
                $card['c_expire_month'] . '|' .
                $card['c_expire_year'];
            $compositeKeys[] = $key;
        }

        $compositeKeysString = "'" . implode("','", array_map('addslashes', $compositeKeys)) . "'";

        $query = DB::connection('payment_gateway_mysql')
            ->table('transactions as t')
            ->join('customer_card as cc', 't.card_id', '=', 'cc.card_id')
            ->join('binbase as bb', 'cc.first6', '=', 'bb.bin')
            ->join('shop as s', 't.shop_id', '=', 's.id')
            ->select(
                's.account_id as merchant_id',
                's.id as shop_id',
                's.owner_name as shop_name',
                'cc.card_id',
                'cc.first6 as bin',
                'bb.isocountry as isocountry',
                'bb.isoa2 as isoa2',
                'bb.bank as binbank',
                't.tid as transaction_id',
                't.trx_id as trx_id',
                't.added as transaction_date',
                't.bank_currency as currency',
                't.bank_amount as amount',
                DB::raw("CASE WHEN t.transaction_type = 'Refund' THEN 1 ELSE 0 END as is_refund")
            )
            ->whereBetween('t.added', [$startDate, $endDate])
            ->where('t.transaction_status', 'APPROVED')
            ->whereIn('t.transaction_type', ['Sale', 'Refund'])
            ->whereIn('bb.isoa2', $this->euCountries)
            ->whereRaw("CONCAT(cc.customer_id, '|', cc.first6, '|', cc.last4, '|', cc.c_expire_month, '|', cc.c_expire_year) IN ({$compositeKeysString})")
            ->orderBy('s.account_id')
            ->orderBy('s.id')
            ->orderBy('t.added');

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $query->whereIn('s.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $query->whereIn('s.id', $shopIds);
        }

        return $query->get();
    }

    /**
     * Get merchant IBAN (placeholder method)
     */
    public function getMerchantIban(int $accountId): ?string
    {
        // Try to get from bank_accounts table if it exists
        try {
            $bankAccount = DB::connection('pgsql')
                ->table('bank_accounts')
                ->where('merchant_id', $accountId)
                ->first();

            if ($bankAccount && isset($bankAccount->iban)) {
                return $bankAccount->iban;
            }
        } catch (\Exception $e) {
            // Table might not exist, continue with fallback
        }

        // Fallback: Generate a placeholder IBAN
        $country = 'CY'; // Default to Cyprus
        $checkDigits = '10'; // Dummy check digits
        $bankCode = 'BANK';
        $accountNumber = str_pad($accountId, 10, '0', STR_PAD_LEFT);

        return $country . $checkDigits . $bankCode . $accountNumber;
    }

    /**
     * Get merchant data from payment_gateway_mysql as fallback
     */
    public function getMerchantFromPaymentGateway(int $accountId): ?array
    {
        $pgMerchant = DB::connection('payment_gateway_mysql')
            ->table('account as a')
            ->leftJoin('account_details as ad', 'a.id', '=', 'ad.account_id')
            ->select(
                'a.tid',
                'a.corp_name as name',
                'a.email as email',
                'ad.street as address',
                'ad.city',
                'ad.postal_code',
                'ad.mcc1',
                'ad.country as iso_country',
                DB::raw('COALESCE(ad.vat_id, "") as vat')
            )
            ->where('a.id', $accountId)
            ->first();

        if ($pgMerchant) {
            $pgMerchantArray = (array)$pgMerchant;
            $pgMerchantArray['iban'] = $this->getMerchantIban($accountId);
            return $pgMerchantArray;
        }

        return null;
    }

    /**
     * Load default PSP data from configuration
     */
    public function loadDefaultPspData(): array
    {
        return [
            'bic' => config('cesop.psp.bic', 'ABCDEF12XXX'),
            'name' => config('cesop.psp.name', 'Intraclear Provider'),
            'country' => config('cesop.psp.country', 'CY'),
            'tax_id' => config('cesop.psp.tax_id', 'CY12345678X')
        ];
    }

    /**
     * Get available quarters for report generation
     */
    public function getAvailableQuarters(int $yearsBack = 3): array
    {
        $quarters = [];
        $currentYear = Carbon::now()->year;
        $currentQuarter = ceil(Carbon::now()->month / 3);

        for ($year = $currentYear; $year > $currentYear - $yearsBack; $year--) {
            $maxQuarters = ($year == $currentYear) ? $currentQuarter - 1 : 4;

            for ($quarter = $maxQuarters; $quarter >= 1; $quarter--) {
                $quarters[] = [
                    'year' => $year,
                    'quarter' => $quarter,
                    'label' => "Q{$quarter} {$year}"
                ];
            }
        }

        return $quarters;
    }

    /**
     * Get available merchants
     */
    public function getAvailableMerchants(): Collection
    {
        return DB::connection('payment_gateway_mysql')->table('account as a')
            ->select('a.id', 'a.corp_name as name')
            ->where('a.active', 1)
            ->orderBy('a.corp_name')
            ->get();
    }

    /**
     * Get shops for a specific merchant
     */
    public function getShopsForMerchant(int $merchantId): Collection
    {
        return DB::connection('payment_gateway_mysql')->table('shop')
            ->select('id', 'owner_name')
            ->where('account_id', $merchantId)
            ->where('active', 1)
            ->orderBy('owner_name')
            ->get();
    }

    /**
     * Get date range for a specific quarter and year
     */
    public function getQuarterDateRange(int $quarter, int $year): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ];
    }

    // Getters for properties
    public function getQuarter(): int
    {
        return $this->quarter;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function getPspData(): array
    {
        return $this->pspData;
    }

    public function getEuCountries(): array
    {
        return $this->euCountries;
    }

    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }
}
