<?php

namespace Modules\Cesop\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Helper\ProgressBar;

class CesopXmlGeneratorService
{
    /**
     * EU country codes list
     *
     * @var array
     */
    protected $euCountries;

    /**
     * Current reporting quarter
     *
     * @var int
     */
    protected $quarter;

    /**
     * Current reporting year
     *
     * @var int
     */
    protected $year;

    /**
     * CESOP XML namespaces
     *
     * @var string
     */
    protected $cesopNamespace;
    protected $isoNamespace;
    protected $cmNamespace;

    /**
     * Threshold for reportable transactions
     *
     * @var int
     */
    protected $threshold;

    /**
     * PSP data for the report
     *
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

        $this->cesopNamespace = 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1';
        $this->isoNamespace = 'urn:eu:taxud:isotypes:v1';
        $this->cmNamespace = 'urn:eu:taxud:commontypes:v1';
    }

    /**
     * Generate CESOP report for a specific quarter and year with the same return format as Excel
     *
     * @param array $merchantIds
     * @param array $shopIds
     * @param string|null $outputDir
     * @return array
     */
    public function generateReport(
        array $merchantIds = [],
        array $shopIds = [],
        ?string $outputDir = null
    ): array
    {
        try {
            // Set unlimited execution time again
            set_time_limit(0);

            // Create output directory if it doesn't exist or use temp directory
            if ($outputDir === null) {
                $outputDir = sys_get_temp_dir() . '/cesop_xml_' . date('Ymd_His');
            }

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Log the output directory for debugging
            Log::info('CESOP XML output directory: ' . $outputDir);

            // Calculate date range based on quarter and year
            $startMonth = (($this->quarter - 1) * 3) + 1;
            $startDate = Carbon::createFromDate($this->year, $startMonth, 1)->startOfDay();
            $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

            if ($this->progressBar) {
                $this->progressBar->setMessage('Identifying qualifying cards...');
            }

            // Get qualifying cards that exceed the threshold
            $qualifyingCards = $this->getQualifyingCards($startDate, $endDate, $merchantIds, $shopIds);

            if (empty($qualifyingCards)) {
                return [
                    'success' => false,
                    'message' => 'No qualifying transactions found.',
                    'data' => null
                ];
            }

            Log::info('Found qualifying cards', ['count' => count($qualifyingCards)]);

            if ($this->progressBar) {
                $this->progressBar->setMessage('Processing merchant data...');
                $this->progressBar->advance();
            }

            // Process merchant data
            $merchantData = $this->processMerchantData($qualifyingCards, $merchantIds);

            if ($this->progressBar) {
                $this->progressBar->setMessage('Processing transactions...');
                $this->progressBar->advance();
            }

            // Get individual transactions for qualifying cards
            $transactions = $this->getTransactionsData($startDate, $endDate, $qualifyingCards, $merchantIds, $shopIds);

            if ($transactions->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No qualifying transactions found.',
                    'data' => null
                ];
            }

            if ($this->progressBar) {
                $this->progressBar->setMessage('Generating XML document...');
                $this->progressBar->advance();
            }

            // Generate XML document
            $dom = $this->initializeXmlDocument($this->pspData, $this->quarter, $this->year);
            $root = $dom->documentElement;

            // Process transactions by merchant and create stats (same as Excel)
            $stats = $this->processTransactionsForXml($dom, $root, $transactions, $merchantData);

            if ($stats['processed_merchants'] === 0) {
                return [
                    'success' => false,
                    'message' => 'No merchants processed. No qualifying data found.',
                    'data' => null
                ];
            }

            if ($this->progressBar) {
                $this->progressBar->setMessage('Saving XML file...');
                $this->progressBar->advance();
            }

            // Save XML file with proper naming convention
            $xmlFileName = "CESOP_Q{$this->quarter}_{$this->year}_" . date('Ymd_His') . ".xml";
            $xmlPath = $outputDir . '/' . $xmlFileName;

            // Check if directory is writable
            if (!is_writable($outputDir)) {
                Log::error('Output directory is not writable: ' . $outputDir);
                return [
                    'success' => false,
                    'message' => 'Output directory is not writable: ' . $outputDir,
                    'data' => null
                ];
            }

            // Log the full path before saving
            Log::info('Attempting to save XML file to: ' . $xmlPath);

            // Save XML to file
            file_put_contents($xmlPath, $dom->saveXML());

            // Verify the file was actually created
            if (!file_exists($xmlPath)) {
                Log::error('XML file was not created at: ' . $xmlPath);
                return [
                    'success' => false,
                    'message' => 'XML file could not be saved to: ' . $xmlPath,
                    'data' => null
                ];
            }

            // Log file size for confirmation
            $fileSize = filesize($xmlPath);
            Log::info('XML file created successfully', [
                'path' => $xmlPath,
                'size' => $fileSize . ' bytes'
            ]);

            if ($this->progressBar) {
                $this->progressBar->finish();
            }

            // Return the same format as Excel service
            return [
                'success' => true,
                'message' => 'XML file generated successfully.',
                'data' => [
                    'file' => $xmlPath,
                    'stats' => $stats,
                    'full_path' => realpath($xmlPath),
                    'file_size' => $fileSize
                ]
            ];
        } catch (\Exception $e) {
            Log::error('CESOP XML generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate XML file: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Process transactions for XML generation and return stats like Excel service
     */
    protected function processTransactionsForXml(
        DOMDocument $dom,
        DOMElement $root,
        Collection $transactions,
        array $merchantData
    ): array
    {
        $transactionsByMerchant = $transactions->groupBy('merchant_id');
        $stats = [
            'merchant_count' => 0,
            'transaction_count' => 0,
            'total_amount' => 0,
            'quarter' => $this->quarter,
            'year' => $this->year,
            'date_range' => [
                'start' => Carbon::createFromDate($this->year, (($this->quarter - 1) * 3) + 1, 1)->format('Y-m-d'),
                'end' => Carbon::createFromDate($this->year, (($this->quarter - 1) * 3) + 1, 1)->addMonths(3)->subDay()->format('Y-m-d')
            ],
            'threshold' => $this->threshold,
            'eu_countries' => count($this->euCountries),
            'processed_merchants' => 0 // Added this field to match XML structure
        ];

        foreach ($transactionsByMerchant as $merchantId => $merchantTransactions) {
            $merchant = $merchantData[$merchantId] ?? null;
            if (!$merchant) {
                continue;
            }

            // Add merchant to XML as ReportedPayee
            $this->addMerchantToXml($dom, $root, $merchant, $merchantTransactions);
            $stats['processed_merchants']++;
            $stats['merchant_count']++;
            $stats['transaction_count'] += $merchantTransactions->count();
            $stats['total_amount'] += $merchantTransactions->sum('amount') / 100;

            if ($this->progressBar) {
                $this->progressBar->advance();
            }
        }

        return $stats;
    }

    /**
     * Get qualifying cards that exceed the threshold
     * Exclude domestic transactions (where merchant country matches card country)
     * Using the same logic as Excel service
     */
    protected function getQualifyingCards(
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

        // Build the query to find qualifying cards - using composite key for unique cards like Excel service
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
     * Process merchant data based on qualifying cards (same as Excel service)
     */
    protected function processMerchantData(array $qualifyingCardCharacteristics, array $merchantIds = []): array
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
    protected function getTransactionsData(
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
     * Load default PSP data from configuration (same as Excel service)
     */
    protected function loadDefaultPspData(): array
    {
        return [
            'bic' => config('cesop.psp.bic', 'ABCDEF12XXX'),
            'name' => config('cesop.psp.name', 'Intraclear Provider'),
            'country' => config('cesop.psp.country', 'CY'),
            'tax_id' => config('cesop.psp.tax_id', 'CY12345678X')
        ];
    }

    /**
     * Get merchant IBAN (placeholder method)
     */
    protected function getMerchantIban(int $accountId): ?string
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
    protected function getMerchantFromPaymentGateway(int $accountId): ?array
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
     * Initialize the XML document according to CESOP schema
     *
     * @param array $pspData
     * @param int $quarter
     * @param int $year
     * @return DOMDocument
     * @throws \DOMException
     */
    protected function initializeXmlDocument(array $pspData, int $quarter, int $year): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element with proper namespaces
        $root = $dom->createElementNS($this->cesopNamespace, 'cesop:CESOP');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cesop', $this->cesopNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso', $this->isoNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cm', $this->cmNamespace);
        $root->setAttribute('version', '4.03');

        $dom->appendChild($root);

        // Create MessageSpec section with proper namespace
        $messageSpec = $dom->createElementNS($this->cesopNamespace, 'cesop:MessageSpec');
        $root->appendChild($messageSpec);

        // All child elements now created with full namespace support
        $messageSpec->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:TransmittingCountry', $pspData['country'])
        );

        $messageSpec->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:MessageType', 'PMT')
        );

        $messageSpec->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:MessageTypeIndic', 'CESOP100')
        );

        $messageSpec->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:MessageRefId', $this->generateUuid())
        );

        // ReportingPeriod section
        $reportingPeriod = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportingPeriod');
        $messageSpec->appendChild($reportingPeriod);

        $reportingPeriod->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:Quarter', (string)$quarter)
        );

        $reportingPeriod->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:Year', (string)$year)
        );

        $messageSpec->appendChild(
            $dom->createElementNS($this->cesopNamespace, 'cesop:Timestamp', date('Y-m-d\TH:i:s\Z'))
        );

        // PaymentDataBody section
        $paymentDataBody = $dom->createElementNS($this->cesopNamespace, 'cesop:PaymentDataBody');
        $root->appendChild($paymentDataBody);

        // ReportingPSP section
        $reportingPSP = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportingPSP');
        $paymentDataBody->appendChild($reportingPSP);

        // PSP ID with BIC attribute
        $pspId = $dom->createElementNS($this->cesopNamespace, 'cesop:PSPId', $pspData['bic']);
        $pspId->setAttribute('PSPIdType', 'BIC');
        $reportingPSP->appendChild($pspId);

        // PSP Name
        $pspName = $dom->createElementNS($this->cesopNamespace, 'cesop:Name', $this->safeXmlString($pspData['name']));
        $pspName->setAttribute('nameType', 'BUSINESS');
        $reportingPSP->appendChild($pspName);

        return $dom;
    }

    /**
     * Add merchant data to XML document as a ReportedPayee
     *
     * @param DOMDocument $dom
     * @param DOMElement $root
     * @param array $merchant
     * @param Collection $transactions
     * @return void
     */
    protected function addMerchantToXml(
        DOMDocument $dom,
        DOMElement  $root,
        array       $merchant,
        Collection  $transactions
    )
    {
        // Find the PaymentDataBody element
        $paymentDataBody = null;
        foreach ($root->childNodes as $child) {
            if ($child->nodeName === 'cesop:PaymentDataBody') {
                $paymentDataBody = $child;
                break;
            }
        }

        if (!$paymentDataBody) {
            return;
        }

        // Create ReportedPayee section for this merchant
        $payee = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedPayee');
        $paymentDataBody->appendChild($payee);

        // 1. Name element - Use actual merchant name
        $name = $dom->createElementNS($this->cesopNamespace, 'cesop:Name', $this->safeXmlString(trim($merchant['name'])));
        $name->setAttribute('nameType', 'BUSINESS');
        $payee->appendChild($name);

        // 2. Country element - Use merchant country or default
        $merchantCountry = $merchant['iso_country'] ?? config('cesop.merchant.country', 'CY');
        $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:Country', $merchantCountry));

        // 3. Address element
        $address = $dom->createElementNS($this->cesopNamespace, 'cesop:Address');
        $address->setAttribute('legalAddressType', 'CESOP303'); // business address
        $payee->appendChild($address);

        // AddressFix element
        $addressFix = $dom->createElementNS($this->cmNamespace, 'cm:AddressFix');
        $address->appendChild($addressFix);

        // Address details
        if (!empty($merchant['address'])) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:Street', $this->safeXmlString($merchant['address'])));
        }

        if (!empty($merchant['city'])) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:City', $this->safeXmlString($merchant['city'])));
        }

        if (!empty($merchant['postal_code'])) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:PostCode', $this->safeXmlString($merchant['postal_code'])));
        }

        // 4. EmailAddress (optional)
        if (!empty($merchant['email'])) {
            $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:EmailAddress', $this->safeXmlString($merchant['email'])));
        }

        // 5. TAXIdentification
        $taxId = $dom->createElementNS($this->cesopNamespace, 'cesop:TAXIdentification');
        $payee->appendChild($taxId);

        // Try to get VAT ID from merchant data
        $vatNumber = $merchant['vat'] ?? '';
        $vatCountry = substr($merchantCountry, 0, 2);

        if (!empty($vatNumber)) {
            $vatId = $dom->createElementNS($this->cmNamespace, 'cm:VATId', $this->safeXmlString($vatNumber));
            $vatId->setAttribute('issuedBy', $vatCountry);
            $taxId->appendChild($vatId);
        }

        // 6. AccountIdentifier
        $accountId = $dom->createElementNS($this->cesopNamespace, 'cesop:AccountIdentifier', $merchant['iban'] ?? '');
        $accountId->setAttribute('CountryCode', $merchantCountry);
        $accountId->setAttribute('type', 'IBAN');
        $payee->appendChild($accountId);

        // 7. ReportedTransaction (add each individual transaction)
        foreach ($transactions as $transaction) {
            // Check merchant country versus card country (exclude domestic transactions)
            $merchantCountry = $merchant['iso_country'] ?? '';
            if (!empty($merchantCountry) && strtoupper($merchantCountry) === strtoupper($transaction->isoa2)) {
                continue; // Skip domestic transactions
            }

            $this->addIndividualTransactionToXml($dom, $payee, $transaction);
        }

        // 8. DocSpec (mandatory - must come last)
        $docSpec = $dom->createElementNS($this->cesopNamespace, 'cesop:DocSpec');
        $payee->appendChild($docSpec);

        // DocTypeIndic - new data
        $docSpec->appendChild($dom->createElementNS($this->cmNamespace, 'cm:DocTypeIndic', 'CESOP2'));

        // DocRefId - unique identifier for this record
        $docSpec->appendChild($dom->createElementNS($this->cmNamespace, 'cm:DocRefId', $this->generateUuid()));
    }

    /**
     * Add an individual transaction to the XML document
     *
     * @param DOMDocument $dom
     * @param DOMElement $payee
     * @param object $transaction
     * @return void
     */
    protected function addIndividualTransactionToXml(
        DOMDocument $dom,
        DOMElement  $payee,
                    $transaction
    )
    {
        // Check if the transaction's country is an EU country
        $payerCountry = $transaction->isoa2;
        if (!in_array($payerCountry, $this->euCountries)) {
            // If not an EU country, skip this transaction
            return;
        }

        $isRefund = $transaction->is_refund ? 'true' : 'false';

        // Get transaction amount - convert to decimal from cents/smaller units
        $amount = $transaction->amount / 100;

        // Create the transaction element
        $transactionElement = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedTransaction');
        $transactionElement->setAttribute('IsRefund', $isRefund);
        $payee->appendChild($transactionElement);

        // Transaction identifier - use the same format as Excel generator
        $txId = 'TX-' .
            $transaction->merchant_id . '-' .
            $transaction->shop_id . '-' .
            $transaction->transaction_id . '-' .
            $transaction->trx_id . '-' .
            $transaction->card_id . '-' .
            $transaction->currency . '-' .
            substr(md5(uniqid()), 0, 8);
        $transactionElement->appendChild(
            $dom->createElementNS(
                $this->cesopNamespace,
                'cesop:TransactionIdentifier',
                $txId
            )
        );

        // Date and time
        $txDate = Carbon::parse($transaction->transaction_date);
        $dateTime = $dom->createElementNS(
            $this->cesopNamespace,
            'cesop:DateTime',
            $this->formatDateTime($txDate)
        );
        $dateTime->setAttribute('transactionDateType', 'CESOP701'); // Execution date
        $transactionElement->appendChild($dateTime);

        // Amount with currency
        $amountElement = $dom->createElementNS(
            $this->cesopNamespace,
            'cesop:Amount',
            $this->formatAmount($amount)
        );
        $amountElement->setAttribute('currency', $transaction->currency);
        $transactionElement->appendChild($amountElement);

        // Payment method - Card payment
        $paymentMethod = $dom->createElementNS($this->cesopNamespace, 'cesop:PaymentMethod');
        $transactionElement->appendChild($paymentMethod);
        $paymentMethod->appendChild($dom->createElementNS($this->cmNamespace, 'cm:PaymentMethodType', 'Card payment'));

        // Not at physical premises (e-commerce)
        $transactionElement->appendChild(
            $dom->createElementNS(
                $this->cesopNamespace,
                'cesop:InitiatedAtPhysicalPremisesOfMerchant',
                'false'
            )
        );

        // Add payer Member State with source
        $payerMS = $dom->createElementNS($this->cesopNamespace, 'cesop:PayerMS', $payerCountry);
        $payerMS->setAttribute('PayerMSSource', 'Other');
        $transactionElement->appendChild($payerMS);
    }

    /**
     * Generate a UUID v4 using Ramsey/UUID library if available
     *
     * @return string
     */
    protected function generateUuid(): string
    {
        // Use Ramsey UUID library if available
        if (class_exists('\\Ramsey\\Uuid\\Uuid')) {
            return Uuid::uuid4()->toString();
        }

        // Use Laravel's Str::uuid() if available
        if (method_exists(Str::class, 'uuid')) {
            return Str::uuid()->toString();
        }

        // Fallback implementation
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Format date and time with UTC timezone
     *
     * @param string|Carbon $dateTime
     * @return string
     */
    protected function formatDateTime($dateTime): string
    {
        if ($dateTime instanceof Carbon) {
            return $dateTime->toIso8601ZuluString();
        }

        return Carbon::parse($dateTime)->toIso8601ZuluString();
    }

    /**
     * Format amount with exactly 2 decimal places
     *
     * @param float $amount
     * @return string
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Safely encode strings for XML
     *
     * @param string $input
     * @return string
     */
    protected function safeXmlString(string $input): string
    {
        return htmlspecialchars($input, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get available quarters for report generation
     *
     * @param int $yearsBack Number of years to look back
     * @return array
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
     *
     * @return Collection
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
     *
     * @param int $merchantId
     * @return Collection
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
     * Get a preview of transaction data for the report
     *
     * @param int $quarter
     * @param int $year
     * @param array $merchantIds
     * @param array $shopIds
     * @param int $threshold
     * @param array|null $pspData
     * @return array
     */
    public function previewReport(
        int   $quarter,
        int   $year,
        array $merchantIds = [],
        array $shopIds = [],
        int   $threshold = 25,
        ?array $pspData = null
    ): array
    {
        $this->quarter = $quarter;
        $this->year = $year;
        $this->threshold = $threshold;

        // Calculate date range based on quarter and year
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        // Get qualifying cards that exceed the threshold
        $qualifyingCards = $this->getQualifyingCards($startDate, $endDate, $merchantIds, $shopIds);

        if (empty($qualifyingCards)) {
            return [
                'success' => false,
                'message' => 'No qualifying transactions found.',
                'data' => null
            ];
        }

        // Get transactions based on qualifying cards
        $transactions = $this->getTransactionsData($startDate, $endDate, $qualifyingCards, $merchantIds, $shopIds);

        if ($transactions->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No qualifying transactions found.',
                'data' => null
            ];
        }

        // Get merchant data
        $merchantData = $this->processMerchantData($qualifyingCards, $merchantIds);

        // Group transactions by merchant
        $summary = $transactions
            ->groupBy('merchant_id')
            ->map(function ($merchantTransactions, $merchantId) use ($merchantData) {
                $merchant = $merchantData[$merchantId] ?? null;

                if (!$merchant) {
                    return null;
                }

                // Count total transactions and amount
                $totalTransactions = $merchantTransactions->count();
                $totalAmount = $merchantTransactions->sum('amount') / 100; // Convert from cents
                $currencies = $merchantTransactions->pluck('currency')->unique()->values();

                return [
                    'merchant_id' => $merchantId,
                    'merchant_name' => $merchant['name'],
                    'transaction_count' => $totalTransactions,
                    'total_amount' => $totalAmount,
                    'currencies' => $currencies->toArray(),
                    'currency_breakdown' => $merchantTransactions->groupBy('currency')
                        ->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'amount' => $group->sum('amount') / 100
                            ];
                        })
                        ->toArray()
                ];
            })
            ->filter()
            ->values();

        return [
            'success' => true,
            'message' => 'Preview generated successfully',
            'data' => [
                'summary' => $summary,
                'period' => [
                    'quarter' => $quarter,
                    'year' => $year,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString()
                ],
                'total_merchants' => $summary->count(),
                'total_transactions' => $summary->sum('transaction_count'),
                'total_amount' => $summary->sum('total_amount'),
                'threshold' => $threshold
            ]
        ];
    }
}
