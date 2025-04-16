<?php

namespace Modules\Cesop\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class CesopXmlValidator
{
    /**
     * Path to the CESOP XSD schema file
     *
     * @var string
     */
    protected $schemaPath;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->schemaPath = config('cesop.schema_path', base_path('Modules/Cesop/resources/xsd/PaymentData.xsd'));
    }

    /**
     * Validate XML string against CESOP schema
     *
     * @param string $xmlString
     * @return array
     */
    public function validateXmlString(string $xmlString): array
    {
        // Check if schema file exists
        if (!file_exists($this->schemaPath)) {
            return [
                'valid' => false,
                'errors' => ["Schema file not found at: {$this->schemaPath}"],
                'warnings' => ["You may need to download the CESOP XSD files from the EU tax authority portal."]
            ];
        }

        // Create DOM document
        $dom = new DOMDocument();

        // Load XML with error handling
        try {
            // Suppress XML loading errors to capture them with libxml_get_errors()
            $originalErrorReporting = libxml_use_internal_errors(true);

            $loadResult = $dom->loadXML($xmlString);

            if (!$loadResult) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                libxml_use_internal_errors($originalErrorReporting);

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
                    'errors' => $errorMessages,
                    'warnings' => ['Failed to load XML string: Invalid XML format']
                ];
            }
        } catch (Exception $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($originalErrorReporting ?? false);

            return [
                'valid' => false,
                'errors' => ['Failed to load XML string: ' . $e->getMessage()],
                'warnings' => []
            ];
        }

        // Try two validation approaches

        // Approach 1: Use DOMDocument::schemaValidate directly
        try {
            $validationResult = $this->validateWithSchemaValidate($dom);
            if ($validationResult['valid']) {
                return $validationResult;
            }
        } catch (Exception $e) {
            // If schemaValidate fails completely, we'll try the second approach
            Log::warning('Schema validation failed with exception: ' . $e->getMessage());
        }

        // Approach 2: Use XMLReader if the first approach failed
        try {
            return $this->validateWithXmlReader($xmlString);
        } catch (Exception $e) {
            // If both validation methods fail, fall back to business rule validation
            Log::warning('XML validation failed with both methods: ' . $e->getMessage());

            // Perform business rule validation as a fallback
            $businessValidation = $this->validateBusinessRules($xmlString);
            $businessValidation['warnings'] = [
                'Schema validation could not be performed due to technical issues.',
                'Only basic business rule validation was performed.'
            ];

            return $businessValidation;
        }
    }

    /**
     * Validate XML using DOMDocument::schemaValidate
     *
     * @param DOMDocument $dom
     * @return array
     */
    protected function validateWithSchemaValidate(DOMDocument $dom): array
    {
        // Enable error reporting for validation
        $originalErrorReporting = libxml_use_internal_errors(true);

        // Perform validation
        $isValid = @$dom->schemaValidate($this->schemaPath);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($originalErrorReporting);

        if (!$isValid) {
            // Format validation errors
            $errorMessages = [];
            $warnings = [];

            foreach ($errors as $error) {
                if ($error->level === LIBXML_ERR_WARNING) {
                    $warnings[] = sprintf(
                        "Line %d, Column %d: %s",
                        $error->line,
                        $error->column,
                        trim($error->message)
                    );
                } else {
                    $errorMessages[] = sprintf(
                        "Line %d, Column %d: %s",
                        $error->line,
                        $error->column,
                        trim($error->message)
                    );
                }
            }

            return [
                'valid' => false,
                'errors' => $errorMessages,
                'warnings' => $warnings
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * Validate XML using XMLReader (alternative approach)
     *
     * @param string $xmlString
     * @return array
     */
    protected function validateWithXmlReader(string $xmlString): array
    {
        // Use XMLReader as an alternative validation approach
        $reader = new \XMLReader();

        // Enable error reporting for validation
        $originalErrorReporting = libxml_use_internal_errors(true);

        // Set up schema validation
        $reader->XML($xmlString);

        try {
            // Set the schema property
            if (!$reader->setSchema($this->schemaPath)) {
                throw new Exception("Could not set schema for validation");
            }

            // Read through the document to trigger validation
            $isValid = true;
            while ($reader->read()) {
                // Just reading through the document
            }

            $errors = libxml_get_errors();
            libxml_clear_errors();

            if (!empty($errors)) {
                $isValid = false;
            }

        } catch (Exception $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($originalErrorReporting);
            $reader->close();
            throw $e;
        }

        $reader->close();

        // Process any validation errors
        if (!$isValid) {
            $errorMessages = [];
            $warnings = [];

            foreach ($errors as $error) {
                if ($error->level === LIBXML_ERR_WARNING) {
                    $warnings[] = sprintf(
                        "Line %d, Column %d: %s",
                        $error->line,
                        $error->column,
                        trim($error->message)
                    );
                } else {
                    $errorMessages[] = sprintf(
                        "Line %d, Column %d: %s",
                        $error->line,
                        $error->column,
                        trim($error->message)
                    );
                }
            }

            libxml_use_internal_errors($originalErrorReporting);

            return [
                'valid' => false,
                'errors' => $errorMessages,
                'warnings' => $warnings
            ];
        }

        libxml_use_internal_errors($originalErrorReporting);

        return [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
    }

    /**
     * Validate XML file against CESOP schema
     *
     * @param string $filePath
     * @return array
     */
    public function validateXmlFile(string $filePath): array
    {
        // Check if file exists
        if (!file_exists($filePath)) {
            return [
                'valid' => false,
                'errors' => ["XML file not found at: {$filePath}"],
                'warnings' => []
            ];
        }

        // Read file content
        $xmlContent = file_get_contents($filePath);

        // Validate using string method
        return $this->validateXmlString($xmlContent);
    }

    /**
     * Perform additional business validations on CESOP XML
     *
     * @param string $xmlString
     * @return array
     */
    public function validateBusinessRules(string $xmlString): array
    {
        $dom = new DOMDocument();

        try {
            $dom->loadXML($xmlString);
        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Invalid XML format: ' . $e->getMessage()],
                'warnings' => []
            ];
        }

        $errors = [];
        $warnings = [];

        // 1. Check for required namespaces
        $root = $dom->documentElement;
        if (!$root) {
            return [
                'valid' => false,
                'errors' => ['XML document has no root element'],
                'warnings' => []
            ];
        }

        if (!$root->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cesop') ||
            !$root->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:iso') ||
            !$root->hasAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cm')) {
            $errors[] = 'Missing required namespaces (cesop, iso, cm)';
        }

        // 2. Check the version attribute
        if (!$root->hasAttribute('version') || $root->getAttribute('version') !== '4.00') {
            $errors[] = 'Invalid or missing version attribute. Must be exactly "4.00"';
        }

        // 3. Check MessageSpec fields
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cesop', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
        $xpath->registerNamespace('cm', 'urn:eu:taxud:commontypes:v1');

        // Check TransmittingCountry
        $countries = $xpath->query('//cesop:MessageSpec/cesop:TransmittingCountry');
        if ($countries->length === 0 || empty($countries->item(0)->nodeValue)) {
            $errors[] = 'Missing or empty TransmittingCountry element';
        } elseif (strlen($countries->item(0)->nodeValue) !== 2) {
            $errors[] = 'TransmittingCountry must be a 2-character ISO country code';
        }

        // Check MessageType
        $messageTypes = $xpath->query('//cesop:MessageSpec/cesop:MessageType');
        if ($messageTypes->length === 0 || $messageTypes->item(0)->nodeValue !== 'PMT') {
            $errors[] = 'Missing or invalid MessageType (must be "PMT")';
        }

        // Check MessageTypeIndic
        $messageTypeIndics = $xpath->query('//cesop:MessageSpec/cesop:MessageTypeIndic');
        if ($messageTypeIndics->length === 0) {
            $errors[] = 'Missing MessageTypeIndic element';
        } else {
            $value = $messageTypeIndics->item(0)->nodeValue;
            if (!in_array($value, ['CESOP100', 'CESOP101', 'CESOP102'])) {
                $errors[] = 'Invalid MessageTypeIndic (must be one of: CESOP100, CESOP101, CESOP102)';
            }
        }

        // Check for UUID format in MessageRefId
        $messageRefIds = $xpath->query('//cesop:MessageSpec/cesop:MessageRefId');
        if ($messageRefIds->length === 0 || empty($messageRefIds->item(0)->nodeValue)) {
            $errors[] = 'Missing or empty MessageRefId element';
        } else {
            $uuid = $messageRefIds->item(0)->nodeValue;
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                $errors[] = 'MessageRefId is not a valid UUID v4 format';
            }
        }

        // 4. Check for ReportingPSP section
        $psps = $xpath->query('//cesop:PaymentDataBody/cesop:ReportingPSP');
        if ($psps->length === 0) {
            $errors[] = 'Missing ReportingPSP element';
        } else {
            // Check PSP ID
            $pspIds = $xpath->query('//cesop:PaymentDataBody/cesop:ReportingPSP/cesop:PSPId');
            if ($pspIds->length === 0 || empty($pspIds->item(0)->nodeValue)) {
                $errors[] = 'Missing or empty PSPId element';
            } else {
                $pspIdNode = $pspIds->item(0);
                if (!$pspIdNode->hasAttribute('PSPIdType')) {
                    $errors[] = 'PSPId element is missing required PSPIdType attribute';
                }
            }
        }

        // 5. Check for at least one ReportedPayee
        $payees = $xpath->query('//cesop:PaymentDataBody/cesop:ReportedPayee');
        if ($payees->length === 0 && $messageTypeIndics->item(0)->nodeValue !== 'CESOP102') {
            $errors[] = 'Missing ReportedPayee element(s) in a data-containing message';
        }

        // 6. Check for at least one PayerMS in each transaction
        $transactions = $xpath->query('//cesop:ReportedTransaction');
        foreach ($transactions as $index => $transaction) {
            $txXpath = new \DOMXPath($dom);
            $txXpath->registerNamespace('cesop', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

            $payerMS = $txXpath->query('cesop:PayerMS', $transaction);
            if ($payerMS->length === 0) {
                $errors[] = "Transaction #" . ($index + 1) . " is missing PayerMS element";
            } else {
                $payerMSNode = $payerMS->item(0);
                if (!$payerMSNode->hasAttribute('PayerMSSource')) {
                    $errors[] = "PayerMS in transaction #" . ($index + 1) . " is missing PayerMSSource attribute";
                }
            }

            // Check for TransactionIdentifier
            $txId = $txXpath->query('cesop:TransactionIdentifier', $transaction);
            if ($txId->length === 0 || empty($txId->item(0)->nodeValue)) {
                $errors[] = "Transaction #" . ($index + 1) . " is missing TransactionIdentifier element";
            }

            // Check Amount
            $amount = $txXpath->query('cesop:Amount', $transaction);
            if ($amount->length === 0) {
                $errors[] = "Transaction #" . ($index + 1) . " is missing Amount element";
            } else {
                $amountNode = $amount->item(0);
                if (!$amountNode->hasAttribute('currency')) {
                    $errors[] = "Amount in transaction #" . ($index + 1) . " is missing currency attribute";
                }
            }
        }

        // 7. Check DocSpec for each Payee
        foreach ($payees as $index => $payee) {
            $payeeXpath = new \DOMXPath($dom);
            $payeeXpath->registerNamespace('cesop', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');
            $payeeXpath->registerNamespace('cm', 'urn:eu:taxud:commontypes:v1');

            $docSpec = $payeeXpath->query('cesop:DocSpec', $payee);
            if ($docSpec->length === 0) {
                $errors[] = "Payee #" . ($index + 1) . " is missing DocSpec element";
            } else {
                $docTypeIndic = $payeeXpath->query('cesop:DocSpec/cm:DocTypeIndic', $payee);
                if ($docTypeIndic->length === 0 || empty($docTypeIndic->item(0)->nodeValue)) {
                    $errors[] = "DocSpec in Payee #" . ($index + 1) . " is missing DocTypeIndic element";
                } else {
                    $value = $docTypeIndic->item(0)->nodeValue;
                    if (!in_array($value, ['CESOP1', 'CESOP2', 'CESOP3'])) {
                        $errors[] = "Invalid DocTypeIndic in Payee #" . ($index + 1) . " (must be one of: CESOP1, CESOP2, CESOP3)";
                    }
                }

                $docRefId = $payeeXpath->query('cesop:DocSpec/cm:DocRefId', $payee);
                if ($docRefId->length === 0 || empty($docRefId->item(0)->nodeValue)) {
                    $errors[] = "DocSpec in Payee #" . ($index + 1) . " is missing DocRefId element";
                }
            }
        }

        // Return validation results
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Set the schema path
     *
     * @param string $path
     * @return self
     */
    public function setSchemaPath(string $path): self
    {
        $this->schemaPath = $path;
        return $this;
    }
    /**
     * Extract reporting period from CESOP XML document
     *
     * @param string $xmlPath Path to the XML file
     * @return array|null Extracted reporting period or null if not found
     */
    public function extractReportingPeriod(string $xmlPath): ?array
    {
        try {
            $dom = new DOMDocument();
            $dom->load($xmlPath);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('cesop', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

            // Try to find the reporting period elements
            $quarterNodes = $xpath->query('//cesop:ReportingPeriod/cesop:Quarter');
            $yearNodes = $xpath->query('//cesop:ReportingPeriod/cesop:Year');

            if ($quarterNodes->length > 0 && $yearNodes->length > 0) {
                $quarter = (int)$quarterNodes->item(0)->nodeValue;
                $year = (int)$yearNodes->item(0)->nodeValue;

                return [
                    'quarter' => $quarter,
                    'year' => $year
                ];
            }

            return null;
        } catch (Exception $e) {
            // Log error or handle as needed
            return null;
        }
    }
}
