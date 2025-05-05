<?php

namespace Modules\Cesop\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Ramsey\Uuid\Uuid;

class CesopExcelImportService
{
    /**
     * @var string
     */
    protected $cesopNamespace;

    /**
     * @var string
     */
    protected $isoNamespace;

    /**
     * @var string
     */
    protected $cmNamespace;

    /**
     * @var array
     */
    protected $euCountries;

    /**
     * @var CesopXmlValidator
     */
    protected $xmlValidator;

    /**
     * Constructor
     *
     * @param CesopXmlValidator $xmlValidator
     */
    public function __construct(CesopXmlValidator $xmlValidator)
    {
        $this->cesopNamespace = 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1';
        $this->isoNamespace = 'urn:eu:taxud:isotypes:v1';
        $this->cmNamespace = 'urn:eu:taxud:commontypes:v1';
        $this->xmlValidator = $xmlValidator;

        $this->euCountries = config('cesop.eu_countries', [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ]);
    }

    /**
     * Import Excel file and convert to CESOP XML format
     *
     * @param string $filePath Path to the Excel file
     * @param array $pspData PSP data for the report
     * @param bool $validate Whether to validate the generated XML
     * @return array Result with success status, message, and data
     */
    public function importAndConvert(string $filePath, array $pspData = null, bool $validate = true): array
    {
        try {
            // Load PSP data from config if not provided
            $pspData = $pspData ?: $this->loadPspData();

            // Load the Excel file
            $spreadsheet = IOFactory::load($filePath);

            // Parse the Excel data
            $paymentData = $this->parseExcelData($spreadsheet);

            if (empty($paymentData['merchants']) || empty($paymentData['transactions'])) {
                return [
                    'success' => false,
                    'message' => 'No valid data found in the Excel file.',
                    'data' => null
                ];
            }

            // Generate XML
            $result = $this->generateXml($paymentData, $pspData);

            // Validate the XML if requested
            if ($validate && $result['success']) {
                $validationResult = $this->xmlValidator->validateXmlString($result['data']['xml']);
                $result['validation'] = $validationResult;

                // If validation failed, update the success status
                if (!$validationResult['valid']) {
                    $result['success'] = false;
                    $result['message'] = 'XML generation succeeded but validation failed. See errors for details.';
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('CESOP Excel Import Error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to import Excel file: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Parse Excel data into structured format for XML generation
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @return array
     */
    protected function parseExcelData($spreadsheet): array
    {
        $result = [
            'merchants' => [],
            'transactions' => [],
            'quarter' => null,
            'year' => null
        ];

        // First, try to parse the configuration sheet to get reporting period
        $configSheet = null;
        try {
            $configSheet = $spreadsheet->getSheetByName('Configuration');
        } catch (\Exception $e) {
            // Configuration sheet not found, try to find it by index
            try {
                $configSheet = $spreadsheet->getSheet(0);
            } catch (\Exception $e) {
                // No configuration sheet found
            }
        }

        if ($configSheet) {
            // Try to find reporting period (quarter and year)
            for ($row = 1; $row <= 20; $row++) {
                $keyCell = $configSheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1) . $row)->getValue();

                if (is_string($keyCell)) {
                    $keyCell = trim($keyCell);

                    if (strpos(strtolower($keyCell), 'quarter') !== false) {
                        $result['quarter'] = (int)$configSheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2) . $row)->getValue();
                    } elseif (strpos(strtolower($keyCell), 'year') !== false) {
                        $result['year'] = (int)$configSheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2) . $row)->getValue();
                    }
                }
            }
        }

        // If quarter or year not found, use current values
        if (!$result['quarter'] || !$result['year']) {
            $currentDate = Carbon::now();
            $result['quarter'] = ceil($currentDate->month / 3);
            $result['year'] = $currentDate->year;
        }

        // Now parse the merchants sheet
        $merchantsSheet = null;
        try {
            $merchantsSheet = $spreadsheet->getSheetByName('Merchants');
        } catch (\Exception $e) {
            // Try other common names
            try {
                $merchantsSheet = $spreadsheet->getSheetByName('Payees');
            } catch (\Exception $e) {
                // Try to find by index
                try {
                    $merchantsSheet = $spreadsheet->getSheet(1);
                } catch (\Exception $e) {
                    // No merchants sheet found
                }
            }
        }

        if ($merchantsSheet) {
            // Find the header row
            $headerRow = 1;
            $headers = [];

            // Look for header row (try first 10 rows)
            for ($row = 1; $row <= 10; $row++) {
                $potentialHeaders = [];
                for ($col = 1; $col <= 20; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $header = $merchantsSheet->getCell($columnLetter . $row)->getValue();
                    if (!empty($header)) {
                        $potentialHeaders[$col] = strtolower(trim($header));
                    }
                }

                // If we found potential headers with merchant ID, name, etc., use this row
                if (in_array('merchant id', $potentialHeaders) ||
                    in_array('id', $potentialHeaders) ||
                    in_array('name', $potentialHeaders)) {
                    $headerRow = $row;
                    $headers = $potentialHeaders;
                    break;
                }
            }

            // Map headers to column indices
            $headerMap = $this->mapMerchantHeaders($headers);

            // Now read merchant data starting from the row after headers
            $currentRow = $headerRow + 1;
            $lastRow = $merchantsSheet->getHighestRow();

            while ($currentRow <= $lastRow) {
                $idCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['id'] ?? 1);
                $nameCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['name'] ?? 2);

                $merchantId = $merchantsSheet->getCell($idCol . $currentRow)->getValue();
                $merchantName = $merchantsSheet->getCell($nameCol . $currentRow)->getValue();

                // Skip empty rows
                if (empty($merchantId) && empty($merchantName)) {
                    $currentRow++;
                    continue;
                }

                // Get other merchant fields
                $countryCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['country'] ?? 3);
                $addressCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['address'] ?? 4);
                $cityCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['city'] ?? 5);
                $postalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['postal_code'] ?? 6);
                $emailCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['email'] ?? 7);
                $websiteCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['website'] ?? 8);
                $vatCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['vat'] ?? 9);

                // Create merchant object
                $merchant = [
                    'id' => $merchantId,
                    'name' => $merchantName,
                    'country' => $merchantsSheet->getCell($countryCol . $currentRow)->getValue() ?: 'CY',
                    'address' => $merchantsSheet->getCell($addressCol . $currentRow)->getValue() ?: '',
                    'city' => $merchantsSheet->getCell($cityCol . $currentRow)->getValue() ?: '',
                    'postal_code' => $merchantsSheet->getCell($postalCol . $currentRow)->getValue() ?: '',
                    'email' => $merchantsSheet->getCell($emailCol . $currentRow)->getValue() ?: '',
                    'website' => $merchantsSheet->getCell($websiteCol . $currentRow)->getValue() ?: '',
                    'vat' => $merchantsSheet->getCell($vatCol . $currentRow)->getValue() ?: ''
                ];

                $result['merchants'][$merchantId] = $merchant;
                $currentRow++;
            }
        }

        // Parse the transactions sheet
        $transactionsSheet = null;
        try {
            $transactionsSheet = $spreadsheet->getSheetByName('Transactions');
        } catch (\Exception $e) {
            // Try other common names
            try {
                $transactionsSheet = $spreadsheet->getSheetByName('Payments');
            } catch (\Exception $e) {
                // Try to find by index
                try {
                    $transactionsSheet = $spreadsheet->getSheet(2);
                } catch (\Exception $e) {
                    // No transactions sheet found
                }
            }
        }

        if ($transactionsSheet) {
            // Find the header row
            $headerRow = 1;
            $headers = [];

            // Look for header row (try first 10 rows)
            for ($row = 1; $row <= 10; $row++) {
                $potentialHeaders = [];
                for ($col = 1; $col <= 20; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $header = $transactionsSheet->getCell($columnLetter . $row)->getValue();
                    if (!empty($header)) {
                        $potentialHeaders[$col] = strtolower(trim($header));
                    }
                }

                // If we found potential headers with transaction ID, amount, etc., use this row
                if (in_array('transaction id', $potentialHeaders) ||
                    in_array('amount', $potentialHeaders) ||
                    in_array('date', $potentialHeaders)) {
                    $headerRow = $row;
                    $headers = $potentialHeaders;
                    break;
                }
            }

            // Map headers to column indices
            $headerMap = $this->mapTransactionHeaders($headers);

            // Now read transaction data starting from the row after headers
            $currentRow = $headerRow + 1;
            $lastRow = $transactionsSheet->getHighestRow();

            while ($currentRow <= $lastRow) {
                $merchantIdCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['merchant_id'] ?? 1);
                $transactionIdCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['transaction_id'] ?? 2);
                $amountCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['amount'] ?? 3);

                $merchantId = $transactionsSheet->getCell($merchantIdCol . $currentRow)->getValue();
                $transactionId = $transactionsSheet->getCell($transactionIdCol . $currentRow)->getValue();
                $amount = $transactionsSheet->getCell($amountCol . $currentRow)->getValue();

                // Skip empty rows
                if (empty($transactionId) && empty($amount)) {
                    $currentRow++;
                    continue;
                }

                // Get date, currency, refund status, and payer country
                $dateCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['date'] ?? 4);
                $currencyCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['currency'] ?? 5);
                $isRefundCol = isset($headerMap['is_refund']) ?
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['is_refund']) : null;
                $payerCountryCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerMap['payer_country'] ?? 6);

                // Get date cell and parse it
                $dateCell = $transactionsSheet->getCell($dateCol . $currentRow);
                $dateValue = $dateCell->getValue();

                // Try to convert Excel date to Carbon
                if (is_numeric($dateValue)) {
                    // Excel date
                    $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                } else {
                    // String date, try to parse
                    try {
                        $dateValue = Carbon::parse($dateValue);
                    } catch (\Exception $e) {
                        // Default to now if date parsing fails
                        $dateValue = Carbon::now();
                    }
                }

                // Get currency and convert to string
                $currency = $transactionsSheet->getCell($currencyCol . $currentRow)->getValue();
                if (empty($currency)) {
                    $currency = 'EUR'; // Default currency
                }

                // Get refund status
                $isRefund = false;
                if ($isRefundCol !== null) {
                    $refundValue = $transactionsSheet->getCell($isRefundCol . $currentRow)->getValue();
                    $isRefund = in_array(strtolower(trim($refundValue ?? '')), ['yes', 'true', '1', 'y', 't']);
                }

                // Get payer country
                $payerCountry = $transactionsSheet->getCell($payerCountryCol . $currentRow)->getValue();
                if (empty($payerCountry) || strlen($payerCountry) != 2) {
                    $payerCountry = 'FR'; // Default to France if not specified
                }

                // Create transaction object
                $transaction = [
                    'merchant_id' => $merchantId,
                    'transaction_id' => $transactionId,
                    'date' => $dateValue,
                    'amount' => $amount,
                    'currency' => $currency,
                    'is_refund' => $isRefund,
                    'payer_country' => $payerCountry
                ];

                $result['transactions'][] = $transaction;
                $currentRow++;
            }
        }

        return $result;
    }

    /**
     * Map merchant headers to standard fields
     *
     * @param array $headers
     * @return array
     */
    protected function mapMerchantHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $col => $header) {
            if (preg_match('/(merchant\s+)?id$|^id$/i', $header)) {
                $map['id'] = $col;
            } elseif (preg_match('/name/i', $header)) {
                $map['name'] = $col;
            } elseif (preg_match('/country/i', $header)) {
                $map['country'] = $col;
            } elseif (preg_match('/street|address/i', $header)) {
                $map['address'] = $col;
            } elseif (preg_match('/city/i', $header)) {
                $map['city'] = $col;
            } elseif (preg_match('/postal|zip/i', $header)) {
                $map['postal_code'] = $col;
            } elseif (preg_match('/email/i', $header)) {
                $map['email'] = $col;
            } elseif (preg_match('/website|url/i', $header)) {
                $map['website'] = $col;
            } elseif (preg_match('/vat|tax/i', $header)) {
                $map['vat'] = $col;
            }
        }

        return $map;
    }

    /**
     * Map transaction headers to standard fields
     *
     * @param array $headers
     * @return array
     */
    protected function mapTransactionHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $col => $header) {
            if (preg_match('/(merchant\s+)?id$|^shop.+id$/i', $header)) {
                $map['merchant_id'] = $col;
            } elseif (preg_match('/transaction\s+id/i', $header)) {
                $map['transaction_id'] = $col;
            } elseif (preg_match('/amount/i', $header)) {
                $map['amount'] = $col;
            } elseif (preg_match('/date|time/i', $header)) {
                $map['date'] = $col;
            } elseif (preg_match('/currency/i', $header)) {
                $map['currency'] = $col;
            } elseif (preg_match('/refund/i', $header)) {
                $map['is_refund'] = $col;
            } elseif (preg_match('/payer.+country/i', $header)) {
                $map['payer_country'] = $col;
            }
        }

        return $map;
    }

    // The rest of the methods remain the same as in your previous code

    /**
     * Generate XML from parsed Excel data
     *
     * @param array $paymentData
     * @param array $pspData
     * @return array
     */
    protected function generateXml(array $paymentData, array $pspData): array
    {
        // Initialize stats
        $stats = [
            'processed_merchants' => 0,
            'total_transactions' => 0,
            'total_amount' => 0
        ];

        // Extract period info
        $quarter = $paymentData['quarter'];
        $year = $paymentData['year'];

        // Generate XML document
        $dom = $this->initializeXmlDocument($pspData, $quarter, $year);
        $root = $dom->documentElement;

        // Find PaymentDataBody element
        $paymentDataBody = null;
        foreach ($root->childNodes as $child) {
            if ($child->nodeName === 'cesop:PaymentDataBody') {
                $paymentDataBody = $child;
                break;
            }
        }

        if (!$paymentDataBody) {
            return [
                'success' => false,
                'message' => 'Failed to initialize XML document: PaymentDataBody not found.',
                'data' => null
            ];
        }

        // Group transactions by merchant
        $transactionsByMerchant = [];
        foreach ($paymentData['transactions'] as $transaction) {
            $merchantId = $transaction['merchant_id'];
            if (!isset($transactionsByMerchant[$merchantId])) {
                $transactionsByMerchant[$merchantId] = [];
            }
            $transactionsByMerchant[$merchantId][] = $transaction;
        }

        // Process each merchant
        foreach ($transactionsByMerchant as $merchantId => $transactions) {
            // Skip if merchant not found in merchants data
            if (!isset($paymentData['merchants'][$merchantId])) {
                continue;
            }

            $merchant = $paymentData['merchants'][$merchantId];

            // Add merchant (payee) to XML
            $this->addMerchantToXml($dom, $paymentDataBody, $merchant, $transactions);

            $stats['processed_merchants']++;
            $stats['total_transactions'] += count($transactions);
            $stats['total_amount'] += array_sum(array_column($transactions, 'amount'));
        }

        if ($stats['processed_merchants'] === 0) {
            return [
                'success' => false,
                'message' => 'No merchants processed. Check that merchant IDs in transactions match merchant data.',
                'data' => null
            ];
        }

        // Calculate date range based on quarter and year
        $startMonth = (($quarter - 1) * 3) + 1;
        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = (clone $startDate)->addMonths(3)->subDay()->endOfDay();

        // Return the XML and stats
        return [
            'success' => true,
            'message' => 'XML generated successfully from Excel data',
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

    // Include the rest of your methods...
    /**
     * Initialize the XML document according to CESOP schema
     */
    protected function initializeXmlDocument(array $pspData, int $quarter, int $year): DOMDocument
    {
        // Implementation remains the same as in your previous code
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element with proper namespaces
        $root = $dom->createElementNS($this->cesopNamespace, 'cesop:CESOP');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cesop', $this->cesopNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso', $this->isoNamespace);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cm', $this->cmNamespace);
        $root->setAttribute('version', '4.03');

        $dom->appendChild($root);

        // Create MessageSpec section
        $messageSpec = $dom->createElementNS($this->cesopNamespace, 'cesop:MessageSpec');
        $root->appendChild($messageSpec);

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
     * Add merchant data to XML as ReportedPayee
     */
    protected function addMerchantToXml(DOMDocument $dom, DOMElement $paymentDataBody, array $merchant, array $transactions): void
    {
        // Implementation remains the same as in your previous code
        // Create ReportedPayee section for this merchant
        $payee = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedPayee');
        $paymentDataBody->appendChild($payee);

        // 1. Name element
        $name = $dom->createElementNS($this->cesopNamespace, 'cesop:Name', $this->safeXmlString(trim($merchant['name'])));
        $name->setAttribute('nameType', 'BUSINESS');
        $payee->appendChild($name);

        // 2. Country element
        $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:Country', $merchant['country']));

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

        // 5. WebPage (optional)
        if (!empty($merchant['website'])) {
            $payee->appendChild($dom->createElementNS($this->cesopNamespace, 'cesop:WebPage', $this->safeXmlString($merchant['website'])));
        }

        // 6. TAXIdentification (mandatory but can be empty)
        $taxId = $dom->createElementNS($this->cesopNamespace, 'cesop:TAXIdentification');
        $payee->appendChild($taxId);

        // Try to get VAT ID from merchant data
        if (!empty($merchant['vat'])) {
            $vatId = $dom->createElementNS($this->cmNamespace, 'cm:VATId', $this->safeXmlString($merchant['vat']));
            $vatId->setAttribute('issuedBy', substr($merchant['country'], 0, 2));
            $taxId->appendChild($vatId);
        }

        // 7. AccountIdentifier (mandatory but can be empty if not available)
        $accountId = $dom->createElementNS($this->cesopNamespace, 'cesop:AccountIdentifier', '');
        $accountId->setAttribute('CountryCode', $merchant['country']);
        $accountId->setAttribute('type', 'Other');
        $accountId->setAttribute('accountIdentifierOther', 'CardPayment');
        $payee->appendChild($accountId);

        // 8. Add transactions
        foreach ($transactions as $transaction) {
            $this->addTransactionToXml($dom, $payee, $transaction);
        }

        // 9. DocSpec (mandatory - must come last)
        $docSpec = $dom->createElementNS($this->cesopNamespace, 'cesop:DocSpec');
    }
    /**
     * Add an individual transaction to the XML document
     *
     * @param DOMDocument $dom
     * @param DOMElement $payee
     * @param array $transaction
     * @return void
     */
    protected function addTransactionToXml(DOMDocument $dom, DOMElement $payee, array $transaction): void
    {
        // Check if the transaction's country is an EU country
        $payerCountry = $transaction['payer_country'];
        if (!in_array($payerCountry, $this->euCountries)) {
            // If not an EU country, skip this transaction
            return;
        }

        $isRefund = $transaction['is_refund'] ? 'true' : 'false';

        // Create the transaction element
        $transactionElement = $dom->createElementNS($this->cesopNamespace, 'cesop:ReportedTransaction');
        $transactionElement->setAttribute('IsRefund', $isRefund);
        $payee->appendChild($transactionElement);

        // Transaction identifier
        $transactionElement->appendChild(
            $dom->createElementNS(
                $this->cesopNamespace,
                'cesop:TransactionIdentifier',
                $transaction['transaction_id']
            )
        );

        // Date and time
        $txDate = $transaction['date'];
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
            $this->formatAmount((float)$transaction['amount'])
        );
        $amountElement->setAttribute('currency', $transaction['currency']);
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
     * Generate a UUID v4
     *
     * @return string
     */
    protected function generateUuid(): string
    {
        // Use Ramsey UUID library if available
        if (class_exists('\\Ramsey\\Uuid\\Uuid')) {
            return Uuid::uuid4()->toString();
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
}
