<?php

namespace Modules\Cesop\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CesopReportService
{
    /**
     * EU country codes list
     *
     * @var array
     */
    protected $euCountries;

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
        int $quarter,
        int $year,
        array $merchantIds = [],
        array $shopIds = [],
        int $threshold = 25,
        ?array $pspData = null
    ): array {
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

                // Group transactions by currency
                $transactionsByCurrency = $shopTransactions->groupBy('currency')
                    ->map(function ($currencyGroup) {
                        return [
                            'currency' => $currencyGroup->first()->currency,
                            'transaction_count' => $currencyGroup->sum('transaction_count'),
                            'total_amount' => $currencyGroup->sum('total_amount')
                        ];
                    })
                    ->values();

                // Add shop data to XML
                $this->addShopToXml($dom, $root, $merchant, $shop, $transactionsByCurrency);
                $stats['processed_shops']++;

                // Update stats
                foreach ($transactionsByCurrency as $currency) {
                    $stats['total_transactions'] += $currency['transaction_count'];
                    $stats['total_amount'] += $currency['total_amount'];
                }
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
        int $quarter,
        int $year,
        array $merchantIds = [],
        array $shopIds = [],
        int $threshold = 25
    ): array {
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
            ->table('transactions')
            ->join('customer_card', 'transactions.card_id', '=', 'customer_card.card_id')
            ->join('binbase', 'customer_card.first6', '=', 'binbase.bin')
            ->join('shop', 'transactions.shop_id', '=', 'shop.id')
            ->select(
                'shop.account_id as merchant_id',
                'shop.id as shop_id',
                'shop.owner_name as shop_name',
                'customer_card.card_id',
                'customer_card.first6 as bin',
                'binbase.isocountry as isocountry',
                'binbase.isoa2 as isoa2',
                'binbase.bank as binbank',
                DB::raw("YEAR(transactions.added) as year"),
                DB::raw("QUARTER(transactions.added) as quarter"),
                'transactions.bank_currency as currency',
                DB::raw("COUNT(*) as transaction_count"),
                DB::raw("SUM(transactions.bank_amount) as total_amount")
            )
            ->whereBetween('transactions.added', [$startDate, $endDate])
            ->where('transactions.transaction_status', 'APPROVED')
            ->where('transactions.transaction_type', 'Sale')
            ->whereIn('binbase.isoa2', $this->euCountries);

        // Apply merchant filter if provided
        if (!empty($merchantIds)) {
            $query->whereIn('shop.account_id', $merchantIds);
        }

        // Apply shop filter if provided
        if (!empty($shopIds)) {
            $query->whereIn('shop.id', $shopIds);
        }

        return $query->groupBy(
            'shop.account_id',
            'shop.id',
            'shop.owner_name',
            'customer_card.card_id',
            'customer_card.first6',
            'binbase.isocountry',
            'binbase.isoa2',
            'binbase.bank',
            DB::raw("YEAR(transactions.added)"),
            DB::raw("QUARTER(transactions.added)"),
            'transactions.bank_currency'
        )
            ->havingRaw("COUNT(*) > {$threshold}")
            ->orderBy('shop.account_id')
            ->orderBy('shop.id')
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
            ->select('a.id', 'a.corp_name as name', 'ad.street as address', 'ad.city', 'ad.postal_code',
                'ad.mcc1')
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
            ->select('id', 'owner_name')
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
     * Initialize the XML document
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

        // Create root element
        $root = $dom->createElementNS('urn:eu:cesop:report:v1.3', 'cesop:CESOP_Report');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'urn:eu:cesop:report:v1.3 CESOP_Report_V1.3.xsd');
        $dom->appendChild($root);

        // Create Header section
        $header = $dom->createElement('cesop:Header');
        $root->appendChild($header);

        $header->appendChild($dom->createElement('cesop:MessageSenderId', $pspData['tax_id']));
        $header->appendChild($dom->createElement('cesop:MessageRecipientId', 'EUROFISC'));
        $header->appendChild($dom->createElement('cesop:MessageType', 'CESOP'));
        $header->appendChild($dom->createElement('cesop:MessageRefId', $pspData['tax_id'] . '-' . $year . 'Q' . $quarter . '-' . Str::random(6)));
        $header->appendChild($dom->createElement('cesop:MessageDate', date('Y-m-d')));
        $header->appendChild($dom->createElement('cesop:ReportingPeriod', $year . '-Q' . $quarter));

        // Create PaymentServiceProvider section
        $psp = $dom->createElement('cesop:PaymentServiceProvider');
        $root->appendChild($psp);

        $psp->appendChild($dom->createElement('cesop:PSPId', $pspData['bic']));
        $psp->appendChild($dom->createElement('cesop:PSPName', $pspData['name']));
        $psp->appendChild($dom->createElement('cesop:CountryCode', $pspData['country']));

        return $dom;
    }

    /**
     * Add shop data to XML document
     *
     * @param DOMDocument $dom
     * @param DOMElement $root
     * @param object $merchant
     * @param object $shop
     * @param array $transactionData
     * @return void
     */
    protected function addShopToXml(DOMDocument $dom, DOMElement $root, $merchant, $shop, $transactionData)
    {
        // Create ReportedPayee section for this shop
        $payee = $dom->createElement('cesop:ReportedPayee');
        $root->appendChild($payee);

        // Use merchant data from the account table
        $payeeName = $merchant->name . " (Shop {$shop->id})";

        $payee->appendChild($dom->createElement('cesop:PayeeName', $payeeName));

        // Get address details, handling potential missing data
        $address = $dom->createElement('cesop:PayeeAddress');
        $payee->appendChild($address);

        $address->appendChild($dom->createElement('cesop:Street', $merchant->address ?? 'Not Available'));
        $address->appendChild($dom->createElement('cesop:City', $merchant->city ?? 'Not Available'));
        $address->appendChild($dom->createElement('cesop:PostCode', $merchant->postal_code ?? 'Not Available'));
        $address->appendChild($dom->createElement('cesop:CountryCode', 'GB')); // Default to GB

        // Tax ID information
        $payee->appendChild($dom->createElement('cesop:PayeeId', 'Unknown TAX ID'));

        // MCC code if available
        if (!empty($merchant->mcc1)) {
            $payee->appendChild($dom->createElement('cesop:MerchantCategoryCode', $merchant->mcc1));
        }

        // Determine if we use single or multiple currency format
        if (count($transactionData) === 1) {
            // Single currency format
            $result = $transactionData[0];

            $payee->appendChild($dom->createElement('cesop:TransactionCount', $result['transaction_count']));

            $totalAmount = $dom->createElement('cesop:TotalAmount', number_format($result['total_amount'] / 100, 2, '.', ''));
            $totalAmount->setAttribute('currency', $result['currency']);
            $payee->appendChild($totalAmount);
        } else {
            // Multiple currencies format
            foreach ($transactionData as $result) {
                $transaction = $dom->createElement('cesop:ReportedTransaction');
                $transaction->appendChild($dom->createElement('cesop:TransactionCount', $result['transaction_count']));

                $totalAmount = $dom->createElement('cesop:TotalAmount', number_format($result['total_amount'] / 100, 2, '.', ''));
                $totalAmount->setAttribute('currency', $result['currency']);
                $transaction->appendChild($totalAmount);

                $payee->appendChild($transaction);
            }
        }
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
}
