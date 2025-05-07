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
     * CESOP XML namespaces
     *
     * @var string
     */
    protected $cesopNamespace;
    protected $isoNamespace;
    protected $cmNamespace;

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
        $this->cesopNamespace = 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1';
        $this->isoNamespace = 'urn:eu:taxud:isotypes:v1';
        $this->cmNamespace = 'urn:eu:taxud:commontypes:v1';
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

        // Group transactions by merchant and shop for processing
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

                // Add shop data to XML
                $this->addShopToXml($dom, $root, $merchant, $shop, $transactions);
                $stats['processed_shops']++;

                // Update stats
                $stats['total_transactions'] += $shopTransactions->count();
                $stats['total_amount'] += $shopTransactions->sum('amount') / 100;
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
     * Get transaction data for the report without aggregation
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
        // Step 1: First identify the cards/bin combinations that exceed the threshold
        $qualifyingCardsQuery = DB::connection('payment_gateway_mysql')
            ->table('transactions as t')
            ->join('customer_card as cc', 't.card_id', '=', 'cc.card_id')
            ->join('binbase as bb', 'cc.first6', '=', 'bb.bin')
            ->join('shop as s', 't.shop_id', '=', 's.id')
            ->select(
                's.account_id as merchant_id',
                's.id as shop_id',
                'cc.card_id',
                DB::raw("COUNT(*) as transaction_count")
            )
            ->whereBetween('t.added', [$startDate, $endDate])
            ->where('t.transaction_status', 'APPROVED')
            ->whereIn('t.transaction_type', ['Sale', 'Refund'])
            ->whereIn('bb.isoa2', $this->euCountries);

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $qualifyingCardsQuery->whereIn('s.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $qualifyingCardsQuery->whereIn('s.id', $shopIds);
        }

        $qualifyingCards = $qualifyingCardsQuery->groupBy(
            's.account_id',
            's.id',
            'cc.card_id'
        )
            ->havingRaw("COUNT(*) > {$threshold}")
            ->pluck('cc.card_id')
            ->toArray();

        // Step 2: Now fetch all individual transactions for those qualifying cards
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
            ->whereIn('cc.card_id', $qualifyingCards);

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $query->whereIn('s.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $query->whereIn('s.id', $shopIds);
        }

        return $query
            ->orderBy('s.account_id')
            ->orderBy('s.id')
            ->orderBy('t.added')
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
    protected function getShopDetails(int $shopId): ?object
    {
        return DB::connection('payment_gateway_mysql')->table('shop')
            ->select('id', 'owner_name', 'website', 'email')
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
     * @return void
     */
    protected function addShopToXml(
        DOMDocument $dom,
        DOMElement  $root,
                    $merchant,
                    $shop,
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

        // Create ReportedPayee section for this shop
        $payee = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedPayee');
        $paymentDataBody->appendChild($payee);

        // ---- Elements must be in the specific order required by the XSD schema ----

        // 1. Name element - Use actual merchant name
        $name = $dom->createElementNS($this->cesopNamespace, 'cesop:Name', $this->safeXmlString(trim($merchant->name)));
        $name->setAttribute('nameType', 'BUSINESS');
        $payee->appendChild($name);

        // 2. Country element - Use merchant country or default to GB
        $merchantCountry = config('cesop.merchant.country', 'GB');
        $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:Country', $merchantCountry));

        // 3. Address element - Use merchant address data
        $address = $dom->createElementNS($this->cesopNamespace, 'cesop:Address');
        $address->setAttribute('legalAddressType', 'CESOP303'); // business address
        $payee->appendChild($address);

        // AddressFix element
        $addressFix = $dom->createElementNS($this->cmNamespace, 'cm:AddressFix');
        $address->appendChild($addressFix);

        // Address details - use merchant data or config defaults
        $street = !empty($merchant->address) ? $merchant->address : config('cesop.merchant.street', 'Street');
        if (!empty($street)) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:Street', $this->safeXmlString($street ?? '')));
        }

        $city = !empty($merchant->city) ? $merchant->city : config('cesop.merchant.city', 'city');
        if (!empty($city)) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:City', $this->safeXmlString($city)));
        }

        $postCode = !empty($merchant->postal_code) ? $merchant->postal_code : config('cesop.merchant.postcode', 'postcode');
        if (!empty($postCode)) {
            $addressFix->appendChild($dom->createElementNS($this->cmNamespace, 'cm:PostCode', $this->safeXmlString($postCode)));
        }

        // 4. EmailAddress (optional)
        if (!empty($shop->email)) {
            $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:EmailAddress', $this->safeXmlString($shop->email)));
        } elseif (!empty($merchant->email)) {
            $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:EmailAddress', $this->safeXmlString($merchant->email)));
        }
        // 5. WebPage (optional)
        if (!empty($shop->website)) {
            $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:WebPage', $this->safeXmlString($shop->website)));
        }
        // 6. TAXIdentification (mandatory but can be empty)
        $taxId = $dom->createElementNS($this->cesopNamespace, 'cesop:TAXIdentification');
        $payee->appendChild($taxId);

        // Try to get VAT ID from merchant data
        $vatNumber = !empty($merchant->tax_id) ? $merchant->tax_id : config('cesop.merchant.vat', '');
        $vatCountry = substr($merchantCountry, 0, 2);

        if (!empty($vatNumber)) {
            $vatId = $dom->createElementNS($this->cmNamespace, 'cm:VATId', $this->safeXmlString($vatNumber));
            $vatId->setAttribute('issuedBy', $vatCountry);
            $taxId->appendChild($vatId);
        }
        // 7. AccountIdentifier (mandatory but can be empty if not available)
        // For card transactions, use 'Other' as type with appropriate description
        $accountId = $dom->createElementNS($this->cesopNamespace, 'cesop:AccountIdentifier', '');
        $accountId->setAttribute('CountryCode', $merchantCountry);
        $accountId->setAttribute('type', 'Other');
        $accountId->setAttribute('accountIdentifierOther', 'CardPayment');
        $payee->appendChild($accountId);
        // 8. ReportedTransaction (add each individual transaction)
        foreach ($transactions as $transaction) {
            // Only process transactions for the current shop
            if ($transaction->shop_id != $shop->id) {
                continue;
            }

            $this->addIndividualTransactionToXml($dom, $payee, $transaction);
        }
        // 9. Representative (optional) - Omitted in this case
        // 10. DocSpec (mandatory - must come last)
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

        // Transaction identifier - use the real transaction ID from the database
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
        $transaction = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedTransaction');
        $transaction->setAttribute('IsRefund', $isRefund);
        $payee->appendChild($transaction);

        // Transaction identifier
        $transaction->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:TransactionIdentifier', $txId));

        // Date and time
        $dateTime = $dom->createElementNS($this->cesopNamespace, 'cesop:DateTime', $this->formatDateTime($reportDate));
        $dateTime->setAttribute('transactionDateType', 'CESOP701'); // Execution date
        $transaction->appendChild($dateTime);

        // Amount with currency
        $amountElement = $dom->createElementNS($this->cesopNamespace, 'cesop:Amount', $this->formatAmount($totalAmount));
        $amountElement->setAttribute('currency', $currency);
        $transaction->appendChild($amountElement);

        // Payment method - Card payment
        $paymentMethod = $dom->createElementNS($this->cesopNamespace, 'cesop:PaymentMethod');
        $transaction->appendChild($paymentMethod);
        $paymentMethod->appendChild($dom->createElementNS($this->cmNamespace, 'cm:PaymentMethodType', 'Card payment'));

        // Not at physical premises (e-commerce)
        $transaction->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:InitiatedAtPhysicalPremisesOfMerchant', 'false'));

        // Add payer Member State with source
        $payerMS = $dom->createElementNS($this->cesopNamespace, 'cesop:PayerMS', $payerCountry);
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
