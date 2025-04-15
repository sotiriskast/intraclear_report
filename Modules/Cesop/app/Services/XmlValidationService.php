<?php

namespace Modules\Cesop\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\DynamicLogger;

class XmlValidationService
{
    /**
     * @var DynamicLogger
     */
    private $logger;

    /**
     * @var string
     */
    private $xsdPath;

    /**
     * Constructor
     */
    public function __construct(DynamicLogger $logger)
    {
        $this->logger = $logger;
        $this->xsdPath = resource_path('xsd/PaymentData.xsd');
    }

    /**
     * Validate XML against CESOP schema
     *
     * @param string $xmlPath Path to the XML file in storage
     * @return array ['isValid' => bool, 'errors' => array]
     */
    public function validateXml(string $xmlPath): array
    {
        // Check if the XSD schema exists
        if (!file_exists($this->xsdPath)) {
            $this->logger->log('warning', 'XSD schema file not found: ' . $this->xsdPath);
            return [
                'isValid' => true, // Skip validation if schema not found
                'errors' => []
            ];
        }

        // Check if the XML file exists
        if (!Storage::exists($xmlPath)) {
            $this->logger->log('error', 'XML file not found: ' . $xmlPath);
            return [
                'isValid' => false,
                'errors' => ['XML file not found: ' . $xmlPath]
            ];
        }

        // Validate XML against XSD
        $xml = new \DOMDocument();

        try {
            $xml->load(Storage::path($xmlPath));

            libxml_use_internal_errors(true);
            $isValid = $xml->schemaValidate($this->xsdPath);

            $errors = [];
            if (!$isValid) {
                $libxmlErrors = libxml_get_errors();
                foreach ($libxmlErrors as $error) {
                    $errorMsg = sprintf('Line %d: %s', $error->line, $error->message);
                    $errors[] = $errorMsg;
                    $this->logger->log('error', 'XML validation error: ' . $errorMsg);
                }
                libxml_clear_errors();
            }

            return [
                'isValid' => $isValid,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            $this->logger->log('error', 'XML validation error: ' . $e->getMessage());
            return [
                'isValid' => false,
                'errors' => ['Exception: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Extract CESOP reporting period data from XML
     *
     * @param string $xmlPath Path to the XML file in storage
     * @return array|null ['quarter' => int, 'year' => int] or null if extraction fails
     */
    public function extractReportingPeriod(string $xmlPath): ?array
    {
        try {
            $xml = new \DOMDocument();
            $xml->load(Storage::path($xmlPath));

            $xml->preserveWhiteSpace = false;
            $xpath = new \DOMXPath($xml);

            // Register namespaces
            $xpath->registerNamespace('cesop', 'urn:ec.europa.eu:taxud:fiscalis:cesop:v1');

            // Extract quarter and year
            $quarterNode = $xpath->query('//cesop:ReportingPeriod/cesop:Quarter');
            $yearNode = $xpath->query('//cesop:ReportingPeriod/cesop:Year');

            if ($quarterNode->length > 0 && $yearNode->length > 0) {
                return [
                    'quarter' => (int)$quarterNode->item(0)->nodeValue,
                    'year' => (int)$yearNode->item(0)->nodeValue
                ];
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to extract reporting period: ' . $e->getMessage());
            return null;
        }
    }
}
