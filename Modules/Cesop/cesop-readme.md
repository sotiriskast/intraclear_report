# CESOP Payment Data Reporting - Technical Implementation Guide

## Introduction

This document provides comprehensive technical guidance for implementing the XML reporting system required by the Central Electronic System of Payment Information (CESOP). As a Payment Service Provider (PSP) operating within the European Union, you are legally required to submit detailed cross-border payment data on a quarterly basis under Council Regulation (EU) 2020/283 and Council Directive (EU) 2020/284.

The CESOP system aims to combat VAT fraud by collecting and analyzing payment data across the EU. This guide explains how to structure and generate compliant XML reports using PHP/Laravel.

## Technical Requirements

### System Prerequisites

- **PHP Version**: 7.4+ recommended
- **Laravel**: 8.0+ for framework-based implementations
- **XML Extensions**: `ext-simplexml`, `ext-dom`, and `ext-libxml` must be installed
- **Memory Requirements**: Sufficient for processing large datasets (minimum 512MB recommended)
- **UUID Generation**: Library capable of generating UUID v4 (e.g., `ramsey/uuid`)
- **Schema Validation**: XML schema validation capabilities

### Required Schema Files

Three XSD schema files define the CESOP reporting format:

1. **PaymentData.xsd**: Primary schema defining the main CESOP elements
2. **commontypes.xsd**: Common data types used throughout the schema
3. **isotypes.xsd**: ISO country and currency code definitions

These files must be stored together as they reference each other via relative paths.

## CESOP XML Structure: In-Depth Analysis

The CESOP XML format follows a hierarchical structure with strictly defined elements. Understanding this hierarchy is crucial for correct implementation:

### 1. Root Element

```xml
<cesop:CESOP xmlns:cesop="urn:ec.europa.eu:taxud:fiscalis:cesop:v1" 
             xmlns:iso="urn:eu:taxud:isotypes:v1" 
             xmlns:cm="urn:eu:taxud:commontypes:v1"
             version="4.00">
    <!-- Content goes here -->
</cesop:CESOP>
```

The `version` attribute must be exactly "4.00" to match the current schema version.

### 2. Message Specification Section

The `MessageSpec` element contains metadata about the report:

```xml
<cesop:MessageSpec>
    <cesop:TransmittingCountry>FR</cesop:TransmittingCountry>
    <cesop:MessageType>PMT</cesop:MessageType>
    <cesop:MessageTypeIndic>CESOP100</cesop:MessageTypeIndic>
    <cesop:MessageRefId>10e989cb-8847-4373-9b79-ef20b743eae6</cesop:MessageRefId>
    <cesop:ReportingPeriod>
        <cm:Quarter>1</cm:Quarter>
        <cm:Year>2024</cm:Year>
    </cesop:ReportingPeriod>
    <cesop:Timestamp>2024-04-15T14:30:00Z</cesop:Timestamp>
</cesop:MessageSpec>
```

#### Element Details:

- **TransmittingCountry**: Must be a valid EU Member State code from the ISO-3166 Alpha 2 list.
- **MessageType**: Must be "PMT" for Payment Data.
- **MessageTypeIndic**: Three possible values:
    - "CESOP100": New data submission
    - "CESOP101": Corrections/deletions to previously submitted data
    - "CESOP102": No data to report for the period
- **MessageRefId**: Must be a UUID v4 format string that is globally unique.
- **ReportingPeriod**:
    - Quarter: Integer from 1-4
    - Year: Four-digit year (must be 2024 or later)
- **Timestamp**: ISO-8601 format with mandatory timezone information

### 3. Payment Data Body

The `PaymentDataBody` contains your PSP information and all reportable transaction data:

```xml
<cesop:PaymentDataBody>
    <cesop:ReportingPSP>
        <cesop:PSPId PSPIdType="BIC">YOURPSPBICXXX</cesop:PSPId>
        <cesop:Name nameType="BUSINESS">Your PSP Name</cesop:Name>
    </cesop:ReportingPSP>
    
    <!-- Payee and transaction data -->
    <cesop:ReportedPayee>
        <!-- Payee details -->
    </cesop:ReportedPayee>
</cesop:PaymentDataBody>
```

#### PSP Identification:

- **PSPId**: Your Business Identifier Code with mandatory `PSPIdType` attribute:
    - "BIC": For standard BIC codes (recommended)
    - "Other": For alternative identification systems
- **Name**: Your PSP name with mandatory `nameType` attribute (typically "BUSINESS")

### 4. Reported Payee Information

Each `ReportedPayee` element represents a merchant receiving payments:

```xml
<cesop:ReportedPayee>
    <cesop:Name nameType="BUSINESS">Example Online Shop GmbH</cesop:Name>
    <cesop:Country>DE</cesop:Country>
    <cesop:Address legalAddressType="CESOP303">
        <cm:AddressFix>
            <cm:Street>Example Street</cm:Street>
            <cm:BuildingIdentifier>123</cm:BuildingIdentifier>
            <cm:PostCode>12345</cm:PostCode>
            <cm:City>Berlin</cm:City>
        </cm:AddressFix>
    </cesop:Address>
    <cesop:EmailAddress>contact@example.com</cesop:EmailAddress>
    <cesop:WebPage>www.example.com</cesop:WebPage>
    <cesop:TAXIdentification>
        <cm:VATId issuedBy="DE">DE123456789</cm:VATId>
    </cesop:TAXIdentification>
    <cesop:AccountIdentifier CountryCode="DE" type="IBAN">DE89370400440532013000</cesop:AccountIdentifier>
    
    <!-- Transaction elements go here -->
    
    <cesop:DocSpec>
        <cm:DocTypeIndic>CESOP1</cm:DocTypeIndic>
        <cm:DocRefId>5b5dc8b6-c19a-465d-8f25-1ce236d0f6e3</cm:DocRefId>
    </cesop:DocSpec>
</cesop:ReportedPayee>
```

#### Key Elements:

- **Name**: Business name with `nameType` attribute (typically "BUSINESS")
- **Country**: ISO-3166 Alpha 2 country code
- **Address**: Preferably in structured `AddressFix` format
- **TAXIdentification**: Most commonly the VAT number with issuing country
- **AccountIdentifier**: Typically an IBAN with country code and type attributes
- **DocSpec**:
    - `DocTypeIndic` indicates if this is new ("CESOP1"), corrected ("CESOP2"), or deleted ("CESOP3") data
    - `DocRefId` must be a unique UUID v4 for each payee record

### 5. Transaction Reporting

Each transaction under a payee is represented by:

```xml
<cesop:ReportedTransaction>
    <cesop:TransactionIdentifier>TX20240115001</cesop:TransactionIdentifier>
    <cesop:DateTime transactionDateType="CESOP701">2024-01-15T10:30:00Z</cesop:DateTime>
    <cesop:Amount currency="EUR">129.99</cesop:Amount>
    <cesop:PaymentMethod>
        <cm:PaymentMethodType>Card payment</cm:PaymentMethodType>
    </cesop:PaymentMethod>
    <cesop:InitiatedAtPhysicalPremisesOfMerchant>false</cesop:InitiatedAtPhysicalPremisesOfMerchant>
    <cesop:PayerMS PayerMSSource="IBAN">FR</cesop:PayerMS>
</cesop:ReportedTransaction>
```

#### Transaction Elements:

- **TransactionIdentifier**: Must be unique within the reporting period
- **DateTime**: Transaction timestamp with mandatory timezone and `transactionDateType` attribute:
    - "CESOP701": Execution Date (most common)
    - "CESOP702": Clearing Date
    - "CESOP703": Authorization Date
    - "CESOP704": Purchase Date
    - "CESOP709": Other Date
- **Amount**: Transaction amount with mandatory `currency` attribute in ISO-4217 format (e.g., "EUR")
- **PaymentMethod**: Method categorization from predefined list:
    - "Card payment", "Bank transfer", "Direct debit", "E-money", etc.
- **InitiatedAtPhysicalPremisesOfMerchant**: Boolean indicating physical vs. online transaction
- **PayerMS**: EU Member State code of the payer with `PayerMSSource` attribute

#### Refund Transactions:

Refunds must have:
- `IsRefund` attribute set to "true"
- Negative amount value
- Optional `CorrTransactionIdentifier` referencing the original payment

```xml
<cesop:ReportedTransaction IsRefund="true">
    <cesop:TransactionIdentifier>TX20240201003</cesop:TransactionIdentifier>
    <cesop:CorrTransactionIdentifier>TX20240115001</cesop:CorrTransactionIdentifier>
    <cesop:DateTime transactionDateType="CESOP701">2024-02-01T09:15:00Z</cesop:DateTime>
    <cesop:Amount currency="EUR">-29.99</cesop:Amount>
    <!-- other elements -->
</cesop:ReportedTransaction>
```

## Detailed PHP Implementation

### 1. Setting Up the Project

```php
// Composer dependencies
require_once 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use DOMDocument;
```

### 2. XML Generation Function

```php
function generateCESOP($reportingPeriod, $pspData, $payeesData) {
    // Create XML with proper namespaces
    $xml = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<cesop:CESOP xmlns:cesop="urn:ec.europa.eu:taxud:fiscalis:cesop:v1" ' .
        'xmlns:iso="urn:eu:taxud:isotypes:v1" ' .
        'xmlns:cm="urn:eu:taxud:commontypes:v1" ' .
        'version="4.00"></cesop:CESOP>'
    );
    
    // Add MessageSpec section
    $messageSpec = $xml->addChild('MessageSpec', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $messageSpec->addChild('TransmittingCountry', $pspData['country'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $messageSpec->addChild('MessageType', 'PMT', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $messageSpec->addChild('MessageTypeIndic', 'CESOP100', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $messageSpec->addChild('MessageRefId', Uuid::uuid4()->toString(), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add reporting period
    $period = $messageSpec->addChild('ReportingPeriod', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $period->addChild('Quarter', $reportingPeriod['quarter'], 'urn:eu:taxud:commontypes:v1');
    $period->addChild('Year', $reportingPeriod['year'], 'urn:eu:taxud:commontypes:v1');
    
    // Add timestamp with timezone
    $messageSpec->addChild('Timestamp', date('Y-m-d\TH:i:s\Z'), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Build payment data body
    $paymentDataBody = $xml->addChild('PaymentDataBody', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add PSP information
    $reportingPSP = $paymentDataBody->addChild('ReportingPSP', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $pspId = $reportingPSP->addChild('PSPId', $pspData['id'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $pspId->addAttribute('PSPIdType', $pspData['idType']);
    $pspName = $reportingPSP->addChild('Name', $pspData['name'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $pspName->addAttribute('nameType', 'BUSINESS');
    
    // Loop through payees and add them
    foreach ($payeesData as $payee) {
        addPayeeToXml($paymentDataBody, $payee);
    }
    
    return $xml->asXML();
}
```

### 3. Adding Payee and Transaction Data

```php
function addPayeeToXml($paymentDataBody, $payeeData) {
    // Create payee element
    $payee = $paymentDataBody->addChild('ReportedPayee', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add payee name
    $payeeName = $payee->addChild('Name', htmlspecialchars($payeeData['name']), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $payeeName->addAttribute('nameType', 'BUSINESS');
    
    // Add country
    $payee->addChild('Country', $payeeData['country'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add address (structured format)
    $address = $payee->addChild('Address', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $address->addAttribute('legalAddressType', 'CESOP303');
    $addressFix = $address->addChild('AddressFix', '', 'urn:eu:taxud:commontypes:v1');
    $addressFix->addChild('Street', htmlspecialchars($payeeData['address']['street']), 'urn:eu:taxud:commontypes:v1');
    $addressFix->addChild('BuildingIdentifier', htmlspecialchars($payeeData['address']['building']), 'urn:eu:taxud:commontypes:v1');
    $addressFix->addChild('PostCode', $payeeData['address']['postcode'], 'urn:eu:taxud:commontypes:v1');
    $addressFix->addChild('City', htmlspecialchars($payeeData['address']['city']), 'urn:eu:taxud:commontypes:v1');
    
    // Add optional email
    if (!empty($payeeData['email'])) {
        $payee->addChild('EmailAddress', $payeeData['email'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    }
    
    // Add optional website
    if (!empty($payeeData['website'])) {
        $payee->addChild('WebPage', $payeeData['website'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    }
    
    // Add tax identification
    $taxId = $payee->addChild('TAXIdentification', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $vatId = $taxId->addChild('VATId', $payeeData['vatId'], 'urn:eu:taxud:commontypes:v1');
    $vatId->addAttribute('issuedBy', $payeeData['country']);
    
    // Add account identifier
    $accountId = $payee->addChild('AccountIdentifier', $payeeData['accountIdentifier'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $accountId->addAttribute('CountryCode', $payeeData['country']);
    $accountId->addAttribute('type', 'IBAN');
    
    // Add transactions
    foreach ($payeeData['transactions'] as $transaction) {
        addTransactionToXml($payee, $transaction);
    }
    
    // Add document specification
    $docSpec = $payee->addChild('DocSpec', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $docSpec->addChild('DocTypeIndic', 'CESOP1', 'urn:eu:taxud:commontypes:v1');
    $docSpec->addChild('DocRefId', Uuid::uuid4()->toString(), 'urn:eu:taxud:commontypes:v1');
}
```

### 4. Adding Transaction Details

```php
function addTransactionToXml($payee, $transactionData) {
    // Create transaction element with optional refund attribute
    $transaction = $payee->addChild('ReportedTransaction', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    if (isset($transactionData['isRefund']) && $transactionData['isRefund']) {
        $transaction->addAttribute('IsRefund', 'true');
    }
    
    // Add transaction identifier
    $transaction->addChild('TransactionIdentifier', $transactionData['id'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add correlated transaction if applicable (for refunds)
    if (!empty($transactionData['corrTransactionId'])) {
        $transaction->addChild('CorrTransactionIdentifier', $transactionData['corrTransactionId'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    }
    
    // Add datetime with transaction type
    $dateTime = $transaction->addChild('DateTime', $transactionData['dateTime'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $dateTime->addAttribute('transactionDateType', 'CESOP701');
    
    // Add amount with currency
    $amount = $transaction->addChild('Amount', $transactionData['amount'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $amount->addAttribute('currency', $transactionData['currency']);
    
    // Add payment method
    $paymentMethod = $transaction->addChild('PaymentMethod', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $paymentMethod->addChild('PaymentMethodType', $transactionData['paymentMethod'], 'urn:eu:taxud:commontypes:v1');
    
    // Add physical premises indicator
    $transaction->addChild('InitiatedAtPhysicalPremisesOfMerchant', 
        $transactionData['isPhysical'] ? 'true' : 'false', 
        'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    
    // Add payer member state
    $payerMS = $transaction->addChild('PayerMS', $transactionData['payerMS'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
    $payerMS->addAttribute('PayerMSSource', 'IBAN');
}
```

### 5. XML Validation Function

```php
function validateCESOP($xmlString, $schemaPath = 'PaymentData.xsd') {
    $dom = new DOMDocument();
    $dom->loadXML($xmlString);
    
    // Enable error reporting for validation
    $originalErrorReporting = libxml_use_internal_errors(true);
    
    $isValid = $dom->schemaValidate($schemaPath);
    
    if (!$isValid) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($originalErrorReporting);
        
        // Format validation errors for debugging
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = sprintf(
                "Line %d, Column %d: %s",
                $error->line,
                $error->column,
                trim($error->message)
            );
        }
        
        return [
            'valid' => false,
            'errors' => $errorMessages
        ];
    }
    
    libxml_use_internal_errors($originalErrorReporting);
    return ['valid' => true];
}
```

## File Handling and Submission

### Naming Convention

Files must follow this naming pattern:
```
PMT-Q{quarter}-{year}-{countryMS}-{pspID}-{partNumber}.xml
```

Example for Credit Agricole submitting Q1 2024 data to France:
```
PMT-Q1-2024-FR-AGRIFRPPXXX-1-1.xml
```

### File Size Management

If your data exceeds the 1GB limit, implement a splitting strategy:

```php
function splitPayeesIntoChunks($payees, $maxPayeesPerFile = 1000) {
    return array_chunk($payees, $maxPayeesPerFile);
}

// Generate multiple files
$payeeChunks = splitPayeesIntoChunks($allPayees);
$totalFiles = count($payeeChunks);

foreach ($payeeChunks as $index => $payeeChunk) {
    $partNumber = ($index + 1) . '-' . $totalFiles;
    $xml = generateCESOP($reportingPeriod, $pspData, $payeeChunk);
    
    $filename = sprintf(
        'PMT-Q%d-%d-%s-%s-%s.xml',
        $reportingPeriod['quarter'],
        $reportingPeriod['year'],
        $pspData['country'],
        $pspData['id'],
        $partNumber
    );
    
    file_put_contents($filename, $xml);
}
```

## Handling Corrections and Special Cases

### Correction Message Example

```php
// Example of generating a correction message
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
<cesop:CESOP xmlns:cesop="urn:ec.europa.eu:taxud:fiscalis:cesop:v1" 
             xmlns:iso="urn:eu:taxud:isotypes:v1" 
             xmlns:cm="urn:eu:taxud:commontypes:v1"
             version="4.00"></cesop:CESOP>');

$messageSpec = $xml->addChild('MessageSpec', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('TransmittingCountry', 'FR', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageType', 'PMT', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageTypeIndic', 'CESOP101', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageRefId', Uuid::uuid4()->toString(), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('CorrMessageRefId', $originalMessageRefId, 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

// Add reporting period (must match original)
$period = $messageSpec->addChild('ReportingPeriod', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$period->addChild('Quarter', $reportingPeriod['quarter'], 'urn:eu:taxud:commontypes:v1');
$period->addChild('Year', $reportingPeriod['year'], 'urn:eu:taxud:commontypes:v1');

// ... continue with PaymentDataBody

// For each corrected payee
$docSpec = $payee->addChild('DocSpec', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$docSpec->addChild('DocTypeIndic', 'CESOP2', 'urn:eu:taxud:commontypes:v1'); // CESOP2 for correction
$docSpec->addChild('DocRefId', Uuid::uuid4()->toString(), 'urn:eu:taxud:commontypes:v1');
$docSpec->addChild('CorrDocRefId', $originalDocRefId, 'urn:eu:taxud:commontypes:v1');
```

### "No Data to Report" Message

```php
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
<cesop:CESOP xmlns:cesop="urn:ec.europa.eu:taxud:fiscalis:cesop:v1" 
             xmlns:iso="urn:eu:taxud:isotypes:v1" 
             xmlns:cm="urn:eu:taxud:commontypes:v1"
             version="4.00"></cesop:CESOP>');

$messageSpec = $xml->addChild('MessageSpec', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('TransmittingCountry', 'FR', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageType', 'PMT', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageTypeIndic', 'CESOP102', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$messageSpec->addChild('MessageRefId', Uuid::uuid4()->toString(), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

// Add reporting period
$period = $messageSpec->addChild('ReportingPeriod', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$period->addChild('Quarter', $reportingPeriod['quarter'], 'urn:eu:taxud:commontypes:v1');
$period->addChild('Year', $reportingPeriod['year'], 'urn:eu:taxud:commontypes:v1');

$messageSpec->addChild('Timestamp', date('Y-m-d\TH:i:s\Z'), 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

// Add PaymentDataBody with just PSP info
$paymentDataBody = $xml->addChild('PaymentDataBody', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$reportingPSP = $paymentDataBody->addChild('ReportingPSP', '', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$pspId = $reportingPSP->addChild('PSPId', $pspData['id'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$pspId->addAttribute('PSPIdType', $pspData['idType']);
$pspName = $reportingPSP->addChild('Name', $pspData['name'], 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
$pspName->addAttribute('nameType', 'BUSINESS');
```

## Common Technical Challenges and Solutions

### 1. UUID Generation

For reliable UUID v4 generation:

```php
// Install via Composer: composer require ramsey/uuid
use Ramsey\Uuid\Uuid;

function generateUuidV4() {
    return Uuid::uuid4()->toString();
}
```

### 2. Handling Special Characters in XML

Always properly escape special characters:

```php
function safeXmlString($input) {
    return htmlspecialchars($input, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
```

### 3. Amount Formatting

Ensure amounts have exactly 2 decimal places:

```php
function formatAmount($amount) {
    return number_format((float)$amount, 2, '.', '');
}
```

### 4. Date Formatting with Timezone

Ensure all dates include timezone information:

```php
function formatDateTime($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('Y-m-d\TH:i:s\Z'); // UTC format
}
```

## Submission Deadlines and Compliance

- **Quarterly Reporting**: Submit by the end of the month following each quarter
- **First Reporting Period**: Q1 2024 (submission due by April 30, 2024)
- **Corrections**: Submit as soon as errors are detected
- **Record Retention**: Retain supporting documentation according to national requirements

## Additional Resources

- **Testing Environment**: Many National Tax Administrations provide a testing environment
- **Validation Tools**: Use both schema validation and business rule validation before submission
- **Technical Support**: Contact your National Tax Administration for implementation assistance

## Troubleshooting

If your XML fails validation:

1. Check all mandatory fields are present
2. Verify UUID formats are correct
3. Ensure datetime fields include timezone information
4. Confirm monetary amounts have exactly 2 decimal places
5. Validate all ISO codes (country, currency) are from the approved lists
6. Check for XML special characters that may need escaping


Ignore domestic payments.
