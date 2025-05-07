# CESOP Payment Data XSD Schema Documentation

## Overview

This documentation describes the XML schema structure for CESOP (Central Electronic System of Payment Information) as defined in Council Regulation (EU) 2020/283 and Council Directive (EU) 2020/284.

## Schema Files

The CESOP Payment Data message uses three XSD files:
- `PaymentData.xsd` - Main elements for Payment Data messages
- `commontypes.xsd` - Common types (VAT numbers, TAX identifiers, etc.)
- `isotypes.xsd` - ISO codes for countries, Member States, and currencies

## XML Structure

### Root Element: CESOP

The root element contains:
- `MessageSpec` - Header information (mandatory)
- Either `PaymentDataBody` or `ValidationResult` (one of these)
- `version` attribute - Schema version (currently "4.03")

### MessageSpec Structure (Article 243d)

| Element | Article Reference | Description | Type | Cardinality |
|---------|------------------|-------------|------|-------------|
| TransmittingCountry | - | Member State sending the data | iso:MSCountryCode_Type | 1..1 |
| MessageType | - | Type of message (PMT/VLD/PNG) | cesop:MessageType_Type | 1..1 |
| MessageTypeIndic | - | Message purpose (CESOP100/101/102) | cesop:MessageTypeIndic_Type | 1..1 |
| MessageRefId | - | Unique message identifier (UUID v4) | cm:UUID | 1..1 |
| CorrMessageRefId | - | Reference to related message | cm:UUID | 0..1 |
| SendingPSP | - | PSP centralizing submission | cesop:PSP_Type | 0..1 |
| ReportingPeriod | Article 243d (2a) | Quarter and year of data | cesop:ReportingPeriod_Type | 1..1 |
| Timestamp | - | Message generation time | cm:dateTimeWithRequiredTimeZone | 1..1 |

### ReportedPayee Structure (Article 243d)

| Element | Article Reference | Description | Type | Cardinality |
|---------|------------------|-------------|------|-------------|
| Name | Article 243d (1b) | Payee name | cm:Name_Type | 1..unbounded |
| Country | Article 243d (1d) | Payee's country of origin | iso:CountryCode_Type | 1..1 |
| Address | Article 243d (1f) | Payee address (if available) | cm:Address_Type | 1..unbounded* |
| EmailAddress | - | Payee email (optional) | cm:Email_Type | 0..unbounded |
| WebPage | - | Payee website (optional) | cm:WebPage_Type | 0..unbounded |
| TAXIdentification | Article 243d (1c) | VAT/tax number (if available) | cesop:TAXIdentifier_Type | 1..1* |
| AccountIdentifier | Article 243d (1d) | IBAN/other identifier | cesop:AccountIdentifier_Type | 1..unbounded* |
| ReportedTransaction | Article 243d (1g, 1h) | Payment transactions | cesop:ReportedTransaction_Type | 0..unbounded |
| Representative | Article 243d (1e) | PSP acting for payee (when needed) | cesop:Representative_Type | 0..1* |
| DocSpec | - | Document specification | cm:DocSpec_Type | 1..1 |

*Note: Elements marked with * are "optional mandatory" - required by law if available, can be empty otherwise

### ReportedTransaction Structure (Article 243d)

| Element | Article Reference | Description | Type | Cardinality |
|---------|------------------|-------------|------|-------------|
| TransactionIdentifier | Article 243d (2d) | Unique transaction reference | cm:StringMin1Max100_Type | 1..1 |
| CorrTransactionIdentifier | - | Reference to related transaction | cm:StringMin1Max100_Type | 0..1 |
| DateTime | Article 243d (2a) | Transaction date/time | cm:TransactionDate_Type | 1..unbounded |
| Amount | Article 243d (2b) | Transaction amount and currency | cm:Amount_Type | 1..1 |
| PaymentMethod | - | Method of payment | cm:PaymentMethod_Type | 0..1 |
| InitiatedAtPhysicalPremisesOfMerchant | Article 243d (2e) | Physical location flag | xs:boolean | 1..1 |
| PayerMS | Article 243d (2c) | Payer's Member State | cesop:PayerMS_Type | 1..1 |
| PSPRole | - | PSP role in transaction | cm:PSPRole_Type | 0..1 |
| IsRefund | Article 243d (1g, 1h) | Refund indicator | cm:Refund_Type | 0..1 |

## Account Identifier Types

The `AccountIdentifier` element can have these types:
- `IBAN` - International Bank Account Number
- `OBAN` - Other Bank Account Number
- `BIC` - Bank Identifier Code
- `Other` - Other identifier (use `accountIdentifierOther` attribute to specify)

For card transactions, use type="Other" with accountIdentifierOther="CardPayment".

## Important Business Rules

1. Elements must appear in the schema-defined order
2. Account identifier is mandatory but can be empty
3. Either Representative OR AccountIdentifier must be provided (not both, not neither)
4. TransactionIdentifier must be unique within the PSP and reporting period
5. For refunds, Amount should be negative and IsRefund="true"
6. At least one DateTime must be within the reporting period

## Legal References

- Council Regulation (EU) 2020/283 of 18 February 2020
- Council Directive (EU) 2020/284 of 18 February 2020
- Commission Implementing Regulation (EU) 2022/1504 of 6 April 2022

## Schema Versions

Current supported version: 4.03

Version history:
- 4.00: Initial version for CESOP go-live
- 4.01: Minor updates
- 4.02: Multiple features and fixes
- 4.03: Current version with namespace improvements

## Validation

The XML must be validated against:
1. XSD schema validation
2. Business rules (as defined in section 4 of User Guide)
3. Technical rules (as defined in section 5 of User Guide)

For more details, refer to the CESOP XSD User Guide v6.00.
