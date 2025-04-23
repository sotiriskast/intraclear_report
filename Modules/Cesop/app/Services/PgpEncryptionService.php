<?php

namespace Modules\Cesop\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenPGP;
use OpenPGP_Crypt_Symmetric;
use OpenPGP_LiteralDataPacket;
use OpenPGP_Message;

class PgpEncryptionService
{
    private $publicKey;

    public function __construct()
    {
        try {
            // Load the TaxisNet PGP public key from storage
            $keyContent = Storage::disk('local')->get('taxisnet_public_key.asc');

            if (!$keyContent) {
                throw new Exception('TaxisNet public key file not found in storage.');
            }

            // Decode the armored PGP key
            $keyData = OpenPGP::unarmor($keyContent);

            if (!$keyData) {
                throw new Exception('Failed to decode the TaxisNet public key.');
            }

            // Create a message object from the key data
            $this->publicKey = OpenPGP_Message::parse($keyData);

            if (!$this->publicKey) {
                throw new Exception('Failed to parse the TaxisNet public key.');
            }
        } catch (Exception $e) {
            Log::error('PGP key initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Encrypt an XML content using the TaxisNet PGP public key
     *
     * @param string $xmlContent The XML content to encrypt
     * @return string The encrypted content
     */
    public function encryptXml(string $xmlContent): string
    {
        try {
            // Create a message object with the XML content
            $data = new OpenPGP_Message([new OpenPGP_LiteralDataPacket($xmlContent)]);

            // Encrypt the message with the public key
            $encrypted = OpenPGP_Crypt_Symmetric::encrypt($this->publicKey, $data);

            // Encode the encrypted message in armored format
            $armored = OpenPGP::enarmor($encrypted->to_bytes(), "PGP MESSAGE");

            return $armored;
        } catch (Exception $e) {
            Log::error('PGP encryption failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Encrypt an XML file and save to storage
     *
     * @param string $xmlPath Path to the XML file in Laravel storage
     * @param string $outputPath Path where to save the encrypted file
     * @return string The path to the encrypted file
     */
    public function encryptXmlFile(string $xmlPath, string $outputPath): string
    {
        try {
            $xmlContent = Storage::get($xmlPath);

            if (!$xmlContent) {
                throw new Exception("XML file not found at path: {$xmlPath}");
            }

            $encryptedContent = $this->encryptXml($xmlContent);

            // Ensure the directory exists
            $directory = dirname($outputPath);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            Storage::put($outputPath, $encryptedContent);

            return $outputPath;
        } catch (Exception $e) {
            Log::error('File encryption failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate a proper CESOP filename
     *
     * @param int $quarter Reporting quarter (1-4)
     * @param int $year Reporting year
     * @param string $countryCode Country code (ISO-3166 Alpha 2)
     * @param string $pspId PSP identifier
     * @param string $fileNumber File number in sequence
     * @param string $totalFiles Total number of files
     * @return string Formatted filename
     */
    public function generateCesopFilename(
        int $quarter,
        int $year,
        string $countryCode,
        string $pspId,
        string $fileNumber = '1',
        string $totalFiles = '1'
    ): string {
        // Validate inputs
        if ($quarter < 1 || $quarter > 4) {
            throw new Exception("Quarter must be between 1 and 4.");
        }

        if (strlen($countryCode) !== 2) {
            throw new Exception("Country code must be ISO-3166 Alpha 2 format (2 characters).");
        }

        return sprintf('PMT-Q%d-%d-%s-%s-%s-%s.pgp',
            $quarter,
            $year,
            strtoupper($countryCode),
            $pspId,
            $fileNumber,
            $totalFiles
        );
    }
}
