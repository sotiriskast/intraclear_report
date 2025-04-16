<?php

namespace Modules\Cesop\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class CesopReportService
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
     * Constructor
     */
    public function __construct()
    {
        $this->euCountries = config('cesop.eu_countries', [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ]);
    }

    /**
     * Generate CESOP report for a specific quarter and year
     *
     * @param int $quarter
     * @param int $year
     * @param array $merchantIds
     * @param array $shopIds
     * @param int $threshold
     * @param array|null $pspData
     * @return array
     */
    public function generateReport(
        int    $quarter,
        int    $year,
        array  $merchantIds = [],
        array  $shopIds = [],
        int    $threshold = 25,
        ?array $pspData = null
    ): array
    {
        $this->quarter = $quarter;
        $this->year = $year;

        // Calculate date range based on quarter and year
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        // Load PSP data
        $pspData = $pspData ?: $this->loadPspData();

        // Get transactions
        $transactions = $this->getTransactionsData($startDate, $endDate, $threshold, $merchantIds, $shopIds);

        if ($transactions->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No qualifying transactions found.',
                'data' => null
            ];
        }

        // Group transactions by merchant and shop
        $transactionsByShop = $transactions->groupBy(['merchant_id', 'shop_id']);

        // Generate XML document
        $dom = $this->initializeXmlDocument($pspData, $quarter, $year);
        $root = $dom->documentElement;

        // Keep track of stats
        $stats = [
            'processed_shops' => 0,
            'processed_merchants' => 0,
            'total_transactions' => 0,
            'total_amount' => 0,
            'transaction_groups' => $transactions->count(),
            'shops' => $transactionsByShop->count()
        ];

        // Process transactions by merchant and shop
        foreach ($transactionsByShop as $merchantId => $merchantShops) {
            // Get merchant details
            $merchant = $this->getMerchantDetails($merchantId);

            if (!$merchant) {
                continue;
            }

            $stats['processed_merchants']++;

            foreach ($merchantShops as $shopId => $shopTransactions) {
                // Get shop details
                $shop = $this->getShopDetails($shopId);

                if (!$shop) {
                    continue;
                }

                // Group transactions by card and currency
                $transactionGroups = $shopTransactions->groupBy(['card_id', 'currency']);

                // Add shop data to XML
                $this->addShopToXml($dom, $root, $merchant, $shop, $shopTransactions, $transactionGroups);
                $stats['processed_shops']++;

                // Update stats
                $stats['total_transactions'] += $shopTransactions->sum('transaction_count');
                $stats['total_amount'] += $shopTransactions->sum('total_amount');
            }
        }

        if ($stats['processed_shops'] === 0) {
            return [
                'success' => false,
                'message' => 'No shops processed. No qualifying data found.',
                'data' => null
            ];
        }

        // Return the XML and stats
        return [
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => [
                'xml' => $dom->saveXML(),
                'stats' => $stats,
                'period' => [
                    'quarter' => $quarter,
                    'year' => $year,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString()
                ]
            ]
        ];
    }

    /**
     * Get transaction data for the report
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $threshold
     * @param array $merchantIds
     * @param array $shopIds
     * @return Collection
     */
    protected function getTransactionsData(Carbon $startDate, Carbon $endDate, int $threshold, array $merchantIds = [], array $shopIds = []): Collection
    {
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
                DB::raw("YEAR(t.added) as year"),
                DB::raw("QUARTER(t.added) as quarter"),
                't.bank_currency as currency',
                DB::raw("COUNT(*) as transaction_count"),
                DB::raw("SUM(t.bank_amount) as total_amount"),
                DB::raw("MIN(t.added) as first_transaction_date"),
                DB::raw("MAX(t.added) as last_transaction_date"),
                DB::raw("MAX(CASE WHEN t.transaction_type = 'Refund' THEN 1 ELSE 0 END) as is_refund")
            )
            ->whereBetween('t.added', [$startDate, $endDate])
            ->where('t.transaction_status', 'APPROVED')
            ->whereIn('t.transaction_type', ['Sale', 'Refund'])
            ->whereIn('bb.isoa2', $this->euCountries);


        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $query->whereIn('s.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $query->whereIn('s.id', $shopIds);
        }

        return $query->groupBy(
            's.account_id',
            's.id',
            's.owner_name',
            'cc.card_id',
            'cc.first6',
            'bb.isocountry',
            'bb.isoa2',
            'bb.bank',
            DB::raw("YEAR(t.added)"),
            DB::raw("QUARTER(t.added)"),
            't.bank_currency'
        )
            ->havingRaw("COUNT(*) > {$threshold}")
            ->orderBy('s.account_id')
            ->orderBy('s.id')
            ->orderBy('year')
            ->orderBy('quarter')
            ->orderByDesc('transaction_count')
            ->get();
    }

    /**
     * Get merchant details by ID
     *
     * @param int $merchantId
     * @return object|null
     */
    protected function getMerchantDetails(int $merchantId)
    {
        return DB::connection('payment_gateway_mysql')->table('account as a')
            ->select(
                'a.id',
                'a.corp_name as name',
                'a.email as email',
                'ad.street as address',
                'ad.city',
                'ad.postal_code',
                'ad.mcc1'
            )
            ->leftJoin('account_details as ad', 'a.id', '=', 'ad.account_id')
            ->where('a.id', $merchantId)
            ->where('a.active', 1)
            ->first();
    }

    /**
     * Get shop details by ID
     *
     * @param int $shopId
     * @return object|null
     */
    protected function getShopDetails(int $shopId)
    {
        return DB::connection('payment_gateway_mysql')->table('shop')
            ->select('id', 'owner_name','website','email')
            ->where('id', $shopId)
            ->where('active', 1)
            ->first();
    }

    /**
     * Load PSP data from config or defaults
     *
     * @param string|null $path Optional path to JSON file with PSP data
     * @return array
     */
    protected function loadPspData(?string $path = null): array
    {
        $defaults = [
            'bic' => config('cesop.psp.bic', 'ABCDEF12XXX'),
            'name' => config('cesop.psp.name', 'Intraclear Provider'),
            'country' => config('cesop.psp.country', 'CY'),
            'tax_id' => config('cesop.psp.tax_id', 'CY12345678X')
        ];

        if ($path && file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            return array_merge($defaults, $data);
        }

        return $defaults;
    }

    /**
     * Initialize the XML document according to CESOP schema
     *
     * @param array $pspData
     * @param int $quarter
     * @param int $year
     * @return DOMDocument
     */
    protected function initializeXmlDocument(array $pspData, int $quarter, int $year): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Define namespace URIs
        $cesopNamespace = 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1';
        $isoNamespace = 'urn:eu:taxud:isotypes:v1';
        $cmNamespace = 'urn:eu:taxud:commontypes:v1';

        // Create root element with proper namespaces
        $root = $dom->createElementNS($cesopNamespace, 'cesop:CESOP');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cesop', $cesopNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso', $isoNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cm', $cmNamespace);
        $root->setAttribute('version', '4.00');

        $dom->appendChild($root);

        // Create MessageSpec section with proper namespace
        $messageSpec = $dom->createElementNS($cesopNamespace, 'cesop:MessageSpec');
        $root->appendChild($messageSpec);

        // All child elements now created with full namespace support
        $messageSpec->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:TransmittingCountry', $pspData['country'])
        );

        $messageSpec->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:MessageType', 'PMT')
        );

        $messageSpec->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:MessageTypeIndic', 'CESOP100')
        );

        $messageSpec->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:MessageRefId', $this->generateUuid())
        );

        // ReportingPeriod section
        $reportingPeriod = $dom->createElementNS($cesopNamespace, 'cesop:ReportingPeriod');
        $messageSpec->appendChild($reportingPeriod);

        $reportingPeriod->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:Quarter', (string)$quarter)
        );

        $reportingPeriod->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:Year', (string)$year)
        );

        $messageSpec->appendChild(
            $dom->createElementNS($cesopNamespace, 'cesop:Timestamp', date('Y-m-d\TH:i:s\Z'))
        );

        // PaymentDataBody section
        $paymentDataBody = $dom->createElementNS($cesopNamespace, 'cesop:PaymentDataBody');
        $root->appendChild($paymentDataBody);

        // ReportingPSP section
        $reportingPSP = $dom->createElementNS($cesopNamespace, 'cesop:ReportingPSP');
        $paymentDataBody->appendChild($reportingPSP);

        // PSP ID with BIC attribute
        $pspId = $dom->createElementNS($cesopNamespace, 'cesop:PSPId', $pspData['bic']);
        $pspId->setAttribute('PSPIdType', 'BIC');
        $reportingPSP->appendChild($pspId);

        // PSP Name
        $pspName = $dom->createElementNS($cesopNamespace, 'cesop:Name', $this->safeXmlString($pspData['name']));
        $pspName->setAttribute('nameType', 'BUSINESS');
        $reportingPSP->appendChild($pspName);

        return $dom;
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
     * Add shop data to XML document as a ReportedPayee
     *
     * @param DOMDocument $dom
     * @param DOMElement $root
     * @param object $merchant
     * @param object $shop
     * @param Collection $transactions
     * @param Collection $transactionGroups
     * @return void
     */
    protected function addShopToXml(
        DOMDocument $dom,
        DOMElement  $root,
                    $merchant,
                    $shop,
        Collection  $transactions,
        Collection  $transactionGroups
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

        // Create ReportedPayee section for this shop
        $payee = $dom->createElement('cesop:ReportedPayee');
        $paymentDataBody->appendChild($payee);

        // Name element - Use actual merchant name
        $name = $dom->createElement('cesop:Name', $this->safeXmlString(trim($merchant->name)));
        $name->setAttribute('nameType', 'BUSINESS');
        $payee->appendChild($name);

        // Country element - Use merchant country or default to GB
        $merchantCountry = config('cesop.merchant.country', 'GB');
        $payee->appendChild($dom->createElement('cesop:Country', $merchantCountry));

        // Address element - Use merchant address data
        $address = $dom->createElement('cesop:Address');
        $address->setAttribute('legalAddressType', 'CESOP303'); // business address
        $payee->appendChild($address);

        // AddressFix element
        $addressFix = $dom->createElement('cm:AddressFix');
        $address->appendChild($addressFix);

        // Address details - use merchant data or config defaults
        $street = !empty($merchant->address) ? $merchant->address : config('cesop.merchant.street', '');
        if (!empty($street)) {
            $addressFix->appendChild($dom->createElement('cm:Street', $this->safeXmlString($street??'')));
        }

        // Add building identifier if available
        $buildingIdentifier = !empty($merchant->building) ? $merchant->building : '';
        if (!empty($buildingIdentifier)) {
            $addressFix->appendChild($dom->createElement('cm:BuildingIdentifier', $this->safeXmlString($buildingIdentifier)));
        }

        $city = !empty($merchant->city) ? $merchant->city : config('cesop.merchant.city', '');
        if (!empty($city)) {
            $addressFix->appendChild($dom->createElement('cm:City', $this->safeXmlString($city)));
        }

        $postCode = !empty($merchant->postal_code) ? $merchant->postal_code : config('cesop.merchant.postcode', '');
        if (!empty($postCode)) {
            $addressFix->appendChild($dom->createElement('cm:PostCode', $this->safeXmlString($postCode)));
        }

        // Add country code to address
        $address = $dom->createElement('cesop:Address');
        $address->setAttribute('legalAddressType', 'CESOP303'); // business address
        $payee->appendChild($address);

        $address->appendChild($dom->createElement('cm:CountryCode', $merchantCountry));

// AddressFix element
        $addressFix = $dom->createElement('cm:AddressFix');
        $address->appendChild($addressFix);

        // Add email if available
        if (!empty($merchant->email)) {
            $payee->appendChild($dom->createElement('cesop:EmailAddress', $this->safeXmlString($merchant->email)));
        }

        // Add website if available
        if (!empty($merchant->website)) {
            $payee->appendChild($dom->createElement('cesop:WebPage', $this->safeXmlString($merchant->website)));
        }

        // TAX identification - Use merchant VAT or tax_id if available
        $taxId = $dom->createElement('cesop:TAXIdentification');
        $payee->appendChild($taxId);

        // Try to get VAT ID from merchant data
        $vatNumber = !empty($merchant->tax_id) ? $merchant->tax_id : config('cesop.merchant.vat', '');
        $vatCountry = substr($merchantCountry, 0, 2);

        if (!empty($vatNumber)) {
            $vatId = $dom->createElement('cm:VATId', $this->safeXmlString($vatNumber));
            $vatId->setAttribute('issuedBy', $vatCountry);
            $taxId->appendChild($vatId);
        }

        // Account identifier with attributes
        // Use merchant/shop IBAN if available or leave empty
        $accountNumber = !empty($shop->iban) ? $shop->iban : (!empty($merchant->iban) ? $merchant->iban : '');
        $accountId = $dom->createElement('cesop:AccountIdentifier', $this->safeXmlString($accountNumber));
        $accountId->setAttribute('CountryCode', $merchantCountry);
        $accountId->setAttribute('type', 'IBAN');
        $payee->appendChild($accountId);

        // Process transactions by card and currency
        foreach ($transactionGroups as $cardId => $currencyGroups) {
            foreach ($currencyGroups as $currency => $cardTransactions) {
                $this->addTransactionToXml($dom, $payee, $merchant, $shop, $cardTransactions, $currency);
            }
        }

        // Add DocSpec element for this payee
        $docSpec = $dom->createElement('cesop:DocSpec');
        $payee->appendChild($docSpec);

        // DocTypeIndic - new data
        $docSpec->appendChild($dom->createElement('cm:DocTypeIndic', 'CESOP1'));

        // DocRefId - unique identifier for this record
        $docSpec->appendChild($dom->createElement('cm:DocRefId', $this->generateUuid()));
    }

    /**
     * Add a transaction to the XML document
     *
     * @param DOMDocument $dom
     * @param DOMElement $payee
     * @param object $merchant
     * @param object $shop
     * @param Collection $cardTransactions
     * @param string $currency
     * @return void
     */
    protected function addTransactionToXml(
        DOMDocument $dom,
        DOMElement  $payee,
                    $merchant,
                    $shop,
        Collection  $cardTransactions,
        string      $currency
    )
    {

        // Get a representative transaction for data we need
        $representativeTransaction = $cardTransactions->first();
        $isRefund = $representativeTransaction->is_refund ? 'true' : 'false';

        // Check if the transaction's country is an EU country
        $payerCountry = $representativeTransaction->isoa2;
        if (!in_array($payerCountry, $this->euCountries)) {
            // If not an EU country, skip this transaction
            return;
        }

        // Get transaction total - convert to decimal from cents/smaller units
        $totalAmount = $cardTransactions->sum('total_amount') / 100;

        // Get count of transactions
        $transactionCount = $cardTransactions->sum('transaction_count');

        // Generate a unique identifier for this transaction group
        $txId = 'TX-' . $merchant->id . '-' . $shop->id . '-' .
            $representativeTransaction->card_id . '-' .
            $currency . '-' . substr(md5(uniqid()), 0, 8);

        // Get a representative date - the midpoint of the reporting quarter
        $startMonth = (($this->quarter - 1) * 3) + 1;
        $reportDate = Carbon::createFromDate($this->year, $startMonth, 15)->startOfDay();

        // If we have actual transaction dates, use the last transaction date instead
        if (!empty($representativeTransaction->last_transaction_date)) {
            $reportDate = Carbon::parse($representativeTransaction->last_transaction_date);
        }

        // Create the transaction element
        $transaction = $dom->createElement('cesop:ReportedTransaction');
        $transaction->setAttribute('IsRefund', $isRefund);
        $payee->appendChild($transaction);

        // Transaction identifier
        $transaction->appendChild($dom->createElement('cesop:TransactionIdentifier', $txId));

        // Date and time
        $dateTime = $dom->createElement('cesop:DateTime', $this->formatDateTime($reportDate));
        $dateTime->setAttribute('transactionDateType', 'CESOP701'); // Execution date
        $transaction->appendChild($dateTime);

        // Amount with currency
        $amountElement = $dom->createElement('cesop:Amount', $this->formatAmount($totalAmount));
        $amountElement->setAttribute('currency', $currency);
        $transaction->appendChild($amountElement);

        // Payment method - Card payment
        $paymentMethod = $dom->createElement('cesop:PaymentMethod');
        $transaction->appendChild($paymentMethod);
        $paymentMethod->appendChild($dom->createElement('cm:PaymentMethodType', 'Card payment'));

        // Not at physical premises (e-commerce)
        $transaction->appendChild($dom->createElement('cesop:InitiatedAtPhysicalPremisesOfMerchant', 'false'));

        // Add payer Member State with source
        $payerMS = $dom->createElement('cesop:PayerMS', $payerCountry);
        $payerMS->setAttribute('PayerMSSource', 'Other');
        $transaction->appendChild($payerMS);
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
     * @return array
     */
    public function previewReport(
        int   $quarter,
        int   $year,
        array $merchantIds = [],
        array $shopIds = [],
        int   $threshold = 25
    ): array
    {
        // Calculate date range based on quarter and year
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        // Get transaction summaries
        $transactions = $this->getTransactionsData($startDate, $endDate, $threshold, $merchantIds, $shopIds);

        if ($transactions->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No qualifying transactions found.',
                'data' => null
            ];
        }

        // Group transactions by merchant and shop
        $summary = $transactions
            ->groupBy(['merchant_id', 'shop_id'])
            ->map(function ($merchantShops, $merchantId) {
                $merchant = $this->getMerchantDetails($merchantId);

                if (!$merchant) {
                    return null;
                }

                $shopsSummary = [];

                foreach ($merchantShops as $shopId => $shopTransactions) {
                    $shop = $this->getShopDetails($shopId);

                    if (!$shop) {
                        continue;
                    }

                    $totalCount = $shopTransactions->sum('transaction_count');
                    $totalAmount = $shopTransactions->sum('total_amount');
                    $currencies = $shopTransactions->pluck('currency')->unique()->values();

                    $shopsSummary[] = [
                        'shop_id' => $shopId,
                        'shop_name' => $shop->owner_name,
                        'transaction_count' => $totalCount,
                        'total_amount' => $totalAmount,
                        'currencies' => $currencies->toArray(),
                        'currency_breakdown' => $shopTransactions->groupBy('currency')
                            ->map(function ($group) {
                                return [
                                    'count' => $group->sum('transaction_count'),
                                    'amount' => $group->sum('total_amount')
                                ];
                            })
                            ->toArray()
                    ];
                }

                return [
                    'merchant_id' => $merchantId,
                    'merchant_name' => $merchant->name,
                    'shops' => $shopsSummary,
                    'total_shops' => count($shopsSummary),
                    'total_transactions' => collect($shopsSummary)->sum('transaction_count'),
                    'total_amount' => collect($shopsSummary)->sum('total_amount')
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
                'total_shops' => $summary->sum('total_shops'),
                'total_transactions' => $summary->sum('total_transactions'),
                'total_amount' => $summary->sum('total_amount')
            ]
        ];
    }
}
