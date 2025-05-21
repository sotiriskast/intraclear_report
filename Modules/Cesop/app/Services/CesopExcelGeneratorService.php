<?php

namespace Modules\Cesop\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Service for generating CESOP data in Excel format
 * Optimized for handling large datasets
 */
class CesopExcelGeneratorService
{
    /**
     * Progress bar instance
     * @var ProgressBar|null
     */
    protected $progressBar;
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
     * Threshold for reportable transactions (25 by default)
     * @var int
     */
    protected $threshold;

    /**
     * PSP data for the report
     * @var array
     */
    protected $pspData;

    /**
     * Batch size for processing transactions
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Spreadsheet object
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * Constructor
     */
    public function __construct(?int $quarter = null, ?int $year = null, int $threshold = 25, ?array $pspData = null, ?ProgressBar $progressBar = null

    )
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
        // Initialize spreadsheet
        $this->spreadsheet = new Spreadsheet();
    }

    /**
     * Generate Excel file for CESOP reporting with optimized processing
     */
    public function generateExcelFile(array $merchantIds = [], array $shopIds = [], string $outputDir = null): array
    {
        try {
            // Set unlimited execution time again (in case constructor setting was overridden)
            set_time_limit(0);

            // Create output directory if it doesn't exist or use temp directory
            if ($outputDir === null) {
                $outputDir = sys_get_temp_dir() . '/cesop_excel_' . date('Ymd_His');
            }

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Log the output directory for debugging
            Log::info('CESOP Excel output directory: ' . $outputDir);

            // Calculate date range for the quarter
            $dateRange = $this->getQuarterDateRange($this->quarter, $this->year);

            // Setup spreadsheet with two worksheets
            $this->setupSpreadsheet();

            // Get account worksheet and payment worksheet
            $accountsSheet = $this->spreadsheet->getSheetByName('CESOP_Accounts');
            $paymentsSheet = $this->spreadsheet->getSheetByName('CESOP_Payments');

            // Process qualifying transactions in batches
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end']);

            // Step 1: First identify all qualifying card/bin combinations that exceed the threshold
            if ($this->progressBar) {
                $this->progressBar->setMessage('Identifying qualifying cards...');
            }

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

            // Step 2: Process merchants data
            $merchantData = $this->processMerchantData($qualifyingCards, $merchantIds);

            if ($this->progressBar) {
                $this->progressBar->setMessage('Writing account data...');
                $this->progressBar->advance();
            }

            // Step 3: Write accounts data to the Accounts worksheet
            $this->writeAccountsDataToWorksheet($accountsSheet, $merchantData);

            if ($this->progressBar) {
                $this->progressBar->setMessage('Processing transactions...');
                $this->progressBar->advance();
            }

            // Step 4: Process transactions in batches and write to the Payments worksheet
            $stats = $this->processTransactionsInBatches(
                $startDate,
                $endDate,
                $qualifyingCards,
                $merchantIds,
                $shopIds,
                $paymentsSheet,
                $merchantData
            );

            if ($this->progressBar) {
                $this->progressBar->setMessage('Formatting worksheets...');
                $this->progressBar->advance();
            }

            // Step 5: Format the worksheets
            $this->formatWorksheets();

            if ($this->progressBar) {
                $this->progressBar->setMessage('Saving Excel file...');
                $this->progressBar->advance();
            }

            // Step 6: Save the Excel file
            $excelFileName = "CESOP_Q{$this->quarter}_{$this->year}_" . date('Ymd_His') . ".xlsx";
            $excelPath = $outputDir . '/' . $excelFileName;

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
            Log::info('Attempting to save Excel file to: ' . $excelPath);

            $writer = new Xlsx($this->spreadsheet);
            $writer->save($excelPath);

            // Verify the file was actually created
            if (!file_exists($excelPath)) {
                Log::error('Excel file was not created at: ' . $excelPath);
                return [
                    'success' => false,
                    'message' => 'Excel file could not be saved to: ' . $excelPath,
                    'data' => null
                ];
            }

            // Log file size for confirmation
            $fileSize = filesize($excelPath);
            Log::info('Excel file created successfully', [
                'path' => $excelPath,
                'size' => $fileSize . ' bytes'
            ]);

            if ($this->progressBar) {
                $this->progressBar->finish();
            }

            return [
                'success' => true,
                'message' => 'Excel file generated successfully.',
                'data' => [
                    'file' => $excelPath,
                    'stats' => $stats,
                    'full_path' => realpath($excelPath), // Add absolute path for clarity
                    'file_size' => $fileSize
                ]
            ];
        } catch (\Exception $e) {
            Log::error('CESOP Excel generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate Excel file: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    /**
     * Setup spreadsheet with two worksheets
     */
    protected function setupSpreadsheet(): void
    {
        // Rename the default worksheet to CESOP_Accounts
        $accountsSheet = $this->spreadsheet->getActiveSheet();
        $accountsSheet->setTitle('CESOP_Accounts');

        // Add the CESOP_Payments worksheet
        $paymentsSheet = $this->spreadsheet->createSheet();
        $paymentsSheet->setTitle('CESOP_Payments');

        // Setup headers for CESOP_Accounts worksheet
        $accountsHeaders = [
            'Bank Account number',                      // Account Number
            'Client full name',                         // Account Full Name
            'Account Name Type',                        // BUSINESS, TRADE, LEGAL, PERSON, OTHER
            'Account Identification Number',            // IBAN or other identifier
            'Account Identification Number Type',       // IBAN, OBAN, Other
            'Account Identification Number Issued By',  // ISO country code
            'Account Reported Transaction',             // Always "true"
            'Account Tax Identification Number',        // VAT or tax number
            'Account TaxID Type',                       // UNCONFIRMED_VAT, TIN, IOSS, OTHER
            'Account Tax ID Issued By',                 // ISO country code
            'Account VAT ID Identification Number',     // EU confirmed VAT number
            'Account VAT ID Issued By',                 // ISO country code
            'Account Country of Residence',             // ISO country code
            'Account Email Address',                    // Optional
            'Account Web Page',                         // Optional
            'Account Address',                          // Address of payee
            'Account Representative',                   // Yes/No
            'Account Representative ID',                // BIC or identifier
            'Account Representative Type',              // BIC/Other
            'Account Representative Name',              // Name of representative
            'Account Representative Name Type'          // BUSINESS, TRADE, LEGAL, PERSON, OTHER
        ];

        // Setup headers for CESOP_Payments worksheet
        $paymentsHeaders = [
            'Bank Account number',                      // Account Number
            'Transaction Reference Number',             // Unique transaction identifier
            'isRefund',                                 // True/False
            'DateTime',                                 // ISO 8601 format
            'Date Type',                                // CESOP701, CESOP702, etc.
            'Amount',                                   // Transaction amount (decimal)
            'Currency',                                 // ISO-4217 currency code
            'PaymentMethod',                            // Card payment, Bank transfer, etc.
            'PaymentMethodOther',                       // Custom payment method description
            'InitiatedAtPhysicalPremisesOfMerchant',    // True/False
            'Incoming/Outgoing',                        // Transaction direction
            'Payee IBAN',                               // IBAN of the payee
            'Payee Country',                            // ISO country code of payee
            'PayerMS',                                  // Member State of the payer (ISO code)
            'PayerMS Source',                           // IBAN, OBAN, Other
            'Payer IBAN',                               // IBAN of the payer if available
            'PSPRoleType',                              // PSP role classification
            'PSPRoleTypeOther'                          // Custom PSP role description
        ];

        // Write headers to worksheets
        $accountsSheet->fromArray($accountsHeaders, null, 'A1');
        $paymentsSheet->fromArray($paymentsHeaders, null, 'A1');
    }

    /**
     * Format worksheets with proper styling
     */
    protected function formatWorksheets(): void
    {
        // Format CESOP_Accounts worksheet
        $accountsSheet = $this->spreadsheet->getSheetByName('CESOP_Accounts');
        $this->formatWorksheet($accountsSheet);

        // Format CESOP_Payments worksheet
        $paymentsSheet = $this->spreadsheet->getSheetByName('CESOP_Payments');
        $this->formatWorksheet($paymentsSheet);
    }

    /**
     * Apply formatting to a worksheet
     */
    protected function formatWorksheet($worksheet): void
    {
        // Get the highest column letter
        $highestColumn = $worksheet->getHighestColumn();

        // Format header row
        $headerRange = 'A1:' . $highestColumn . '1';
        $worksheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'F2F2F2',
                ],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Set column widths
        $columnLetters = range('A', $highestColumn);
        foreach ($columnLetters as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze the top row
        $worksheet->freezePane('A2');
    }

    /**
     * Get qualifying cards that exceed the threshold
     * Exclude domestic transactions (where merchant country matches card country)
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

        // Build the query to find qualifying cards - using a composite key for unique cards
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

        // Add shop data
        $shopData = DB::connection('payment_gateway_mysql')
            ->table('shop')
            ->select('id', 'account_id', 'owner_name', 'email', 'website')
            ->whereIn('account_id', array_keys($merchantMap))
            ->get();

        foreach ($shopData as $shop) {
            if (isset($merchantMap[$shop->account_id])) {
                if (!isset($merchantMap[$shop->account_id]['shops'])) {
                    $merchantMap[$shop->account_id]['shops'] = [];
                }
                $merchantMap[$shop->account_id]['shops'][] = $shop;
            }
        }

        return $merchantMap;
    }

    /**
     * Write accounts data to worksheet
     */
    protected function writeAccountsDataToWorksheet($worksheet, array $merchants): void
    {
        $row = 2; // Start from row 2 (after headers)

        foreach ($merchants as $merchantId => $merchant) {
            // Use shop data for the main shop or default empty values
            $mainShop = isset($merchant['shops'][0]) ? $merchant['shops'][0] : null;

            // Determine appropriate values for each field
            $accountNumber = $merchant['iban'] ?? '';
            $fullName = $merchant['name'] ?? '';
            $nameType = 'BUSINESS'; // Default for merchants
            $identificationNumber = $merchant['iban'] ?? '';
            $identificationType = $identificationNumber ? 'IBAN' : '';
            $identificationIssuedBy = $merchant['iso_country'] ?? '';
            $reportedTransaction = 'true';
            $taxIdentificationNumber = $merchant['vat'] ?? '';
            $taxIdType = $taxIdentificationNumber ? 'UNCONFIRMED_VAT' : '';
            $taxIdIssuedBy = $merchant['iso_country'] ?? '';
            $vatIdNumber = $merchant['vat'] ?? '';
            $vatIdIssuedBy = $merchant['iso_country'] ?? '';
            $countryOfResidence = $merchant['iso_country'] ?? '';
            $emailAddress = $merchant['email'] ?? ($mainShop ? $mainShop->email : '');
            $webPage = $mainShop ? $mainShop->website : '';

            // Construct address from components
            $address = implode(', ', array_filter([
                $merchant['address'] ?? '',
                $merchant['city'] ?? '',
                $merchant['postal_code'] ?? ''
            ]));

            // Default to no representative
            $hasRepresentative = 'No';
            $representativeId = '';
            $representativeType = '';
            $representativeName = '';
            $representativeNameType = '';

            $data = [
                $accountNumber,
                $fullName,
                $nameType,
                $identificationNumber,
                $identificationType,
                $identificationIssuedBy,
                $reportedTransaction,
                $taxIdentificationNumber,
                $taxIdType,
                $taxIdIssuedBy,
                $vatIdNumber,
                $vatIdIssuedBy,
                $countryOfResidence,
                $emailAddress,
                $webPage,
                $address,
                $hasRepresentative,
                $representativeId,
                $representativeType,
                $representativeName,
                $representativeNameType
            ];

            // Write data to row
            $worksheet->fromArray([$data], null, "A{$row}");
            $row++;
        }
    }

    /**
     * Process transactions in batches and write to worksheet
     * Excludes domestic transactions (where merchant country matches card country)
     */
    /**
     * Process all transactions without batching and write to worksheet
     * Excludes domestic transactions (where merchant country matches card country)
     */
    /**
     * Alternative approach using IN clause with concatenated keys
     */
    protected function processTransactionsInBatches(
        Carbon $startDate,
        Carbon $endDate,
        array  $qualifyingCardCharacteristics,
        array  $merchantIds,
        array  $shopIds,
               $worksheet,
        array  $merchantData
    ): array
    {
        $totalTransactions = 0;
        $totalAmount = 0;
        $row = 2;

        if (empty($qualifyingCardCharacteristics)) {
            return [
                'merchant_count' => count($merchantData),
                'transaction_count' => 0,
                'total_amount' => 0,
                'quarter' => $this->quarter,
                'year' => $this->year,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ],
                'threshold' => $this->threshold,
                'eu_countries' => count($this->euCountries)
            ];
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
                't.tid',
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
                DB::raw("CASE WHEN t.transaction_type = 'Refund' THEN 1 ELSE 0 END as is_refund"),
                // Add the composite key as a selected field for debugging (optional)
                DB::raw("CONCAT(cc.customer_id, '_', cc.first6, '_', cc.last4, '_', cc.c_expire_month, '_', cc.c_expire_year) as card_composite_key")
            )
            ->whereBetween('t.added', [$startDate, $endDate])
            ->where('t.transaction_status', 'APPROVED')
            ->whereIn('t.transaction_type', ['Sale', 'Refund'])
            ->whereIn('bb.isoa2', $this->euCountries)
            ->whereRaw("CONCAT(cc.customer_id, '|', cc.first6, '|', cc.last4, '|', cc.c_expire_month, '|', cc.c_expire_year) IN ({$compositeKeysString})")
            ->orderBy('t.tid');

        // Apply filters
        if (!empty($merchantIds)) {
            $query->whereIn('s.account_id', $merchantIds);
        }

        if (!empty($shopIds)) {
            $query->whereIn('s.id', $shopIds);
        }

        $totalCount = $query->count();

        if ($this->progressBar && $totalCount > 0) {
            $this->progressBar->setMaxSteps($totalCount + 5);
            $this->progressBar->setMessage("Processing $totalCount transactions...");
        }

        $transactions = $query->get();

        // Process transactions (same as before)
        $batchData = [];
        foreach ($transactions as $transaction) {
            $merchant = $merchantData[$transaction->merchant_id] ?? null;
            if (!$merchant) {
                continue;
            }

            $merchantCountry = $merchant['iso_country'] ?? '';
            if (!empty($merchantCountry) && strtoupper($merchantCountry) === strtoupper($transaction->isoa2)) {
                continue;
            }

            // Build transaction row data
            $dateTime = Carbon::parse($transaction->transaction_date)->toIso8601String();
            $accountNumber = $merchant['iban'] ?? '';
            $transactionReference = 'TX-' . $transaction->merchant_id . '-' . $transaction->shop_id . '-' .
                $transaction->transaction_id . '-' . $transaction->trx_id . '-' .
                $transaction->card_id . '-' . $transaction->currency . '-' .
                substr(md5(uniqid()), 0, 8);
            $isRefund = $transaction->is_refund ? 'True' : 'False';
            $dateType = 'CESOP701';
            $amount = number_format($transaction->amount / 100, 2, '.', '');
            $currency = $transaction->currency;
            $paymentMethod = 'Card payment';
            $paymentMethodOther = '';
            $initiatedAtPhysicalPremises = 'False';
            $direction = $transaction->is_refund ? 'Outgoing' : 'Incoming';
            $payeeIban = $merchant['iban'] ?? '';
            $payeeCountry = $merchant['iso_country'] ?? 'CY';
            $payerMS = $transaction->isoa2;
            $payerMSSource = 'Other';
            $payerIban = str_replace(' ', '_', $transaction->card_composite_key) ?? '';
            $pspRoleType = 'Four party card scheme';
            $pspRoleTypeOther = '';

            $batchData[] = [
                $accountNumber, $transactionReference, $isRefund, $dateTime, $dateType,
                $amount, $currency, $paymentMethod, $paymentMethodOther, $initiatedAtPhysicalPremises,
                $direction, $payeeIban, $payeeCountry, $payerMS, $payerMSSource,
                $payerIban, $pspRoleType, $pspRoleTypeOther
            ];

            $totalTransactions++;
            $totalAmount += $transaction->amount;

            if ($this->progressBar) {
                $this->progressBar->advance();
            }
        }

        if (!empty($batchData)) {
            $worksheet->fromArray($batchData, null, "A{$row}");
        }

        if ($this->progressBar) {
            $this->progressBar->setMessage("Processed $totalTransactions transactions");
        }

        return [
            'merchant_count' => count($merchantData),
            'transaction_count' => $totalTransactions,
            'total_amount' => $totalAmount / 100,
            'quarter' => $this->quarter,
            'year' => $this->year,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'threshold' => $this->threshold,
            'eu_countries' => count($this->euCountries)
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
     * Get date range for a specific quarter and year
     */
    protected function getQuarterDateRange(int $quarter, int $year): array
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ];
    }

    /**
     * Load default PSP data from configuration
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

}
