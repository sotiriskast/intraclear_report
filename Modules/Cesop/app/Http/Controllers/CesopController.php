<?php

namespace Modules\Cesop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Cesop\Services\CesopXmlValidator;
use Modules\Cesop\Services\PgpEncryptionService;
use ZipArchive;

class CesopController extends Controller
{
    /**
     * @var PgpEncryptionService
     */
    private $pgpService;

    /**
     * @var CesopXmlValidator
     */
    private $xmlValidator;

    /**
     * @var DynamicLogger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct(
        PgpEncryptionService $pgpService,
        CesopXmlValidator $xmlValidator,
        DynamicLogger $logger
    ) {
        $this->pgpService = $pgpService;
        $this->xmlValidator = $xmlValidator;
        $this->logger = $logger;
    }

    /**
     * Show the CESOP module index page
     */
    public function index()
    {
        return view('cesop::encrypt.index');
    }

    /**
     * Compress XML file to ZIP
     *
     * @param string $xmlPath Path to the XML file
     * @return string Path to the created ZIP file
     * @throws \Exception
     */
    private function compressToZip(string $xmlPath): string
    {
        // Create a new ZIP archive
        $zip = new ZipArchive();
        $zipFilename = substr(basename($xmlPath), 0, -4) . '.zip';
        $zipPath = dirname($xmlPath) . '/' . $zipFilename;

        // Create the ZIP file
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Could not create ZIP file');
        }

        // Add the XML file to the ZIP
        $zip->addFile($xmlPath, basename($xmlPath));
        $zip->close();

        return $zipPath;
    }

    /**
     * Handle XML file upload, compress, and encrypt
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'xml_file' => 'required|file|mimes:xml|max:10240', // 10MB max
            'quarter' => 'required|integer|min:1|max:4',
            'year' => 'required|integer|min:2024',
            'country_code' => 'required|string|size:2',
            'psp_id' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Store the uploaded XML file
            $xmlPath = $request->file('xml_file')->store('cesop/temp');
            $fullXmlPath = Storage::path($xmlPath);

            // Validate against CESOP schema
            $validationResult = $this->xmlValidator->validateXmlFile($fullXmlPath);

            if (!$validationResult['valid']) {
                // Display first 3 errors to the user
                $errorMessages = array_slice($validationResult['errors'], 0, 3);
                $errorMessage = 'The XML file is not valid according to the CESOP schema: ' .
                    implode('; ', $errorMessages);

                // Clean up the temporary file
                Storage::delete($xmlPath);

                return redirect()->back()
                    ->with('error', $errorMessage)
                    ->withInput();
            }

            // Compress XML to ZIP
            $zipPath = $this->compressToZip($fullXmlPath);

            // Use form inputs directly
            $quarter = $request->input('quarter');
            $year = $request->input('year');
            $countryCode = strtoupper($request->input('country_code'));
            $pspId = $request->input('psp_id');

            // Replace file extension to .pgp.zip
            $outputFilename = $this->pgpService->generateCesopFilename(
                $quarter, $year, $countryCode, $pspId
            );
            $outputFilename = str_replace('.pgp', '.pgp.zip', $outputFilename);

            // Define output path for encrypted ZIP
            $outputPath = 'cesop/encrypted/' . $outputFilename;

            // Encrypt the ZIP file (convert to Laravel storage path if needed)
            $zipStoragePath = 'cesop/temp/' . basename($zipPath);
            Storage::put($zipStoragePath, file_get_contents($zipPath));
            $encryptedPath = $this->pgpService->encryptXmlFile($zipStoragePath, $outputPath);

            // Clean up temporary files
            unlink($zipPath);
            Storage::delete($xmlPath);
            Storage::delete($zipStoragePath);

            $this->logger->log('info', "CESOP XML compressed, encrypted successfully: {$outputFilename}");

            return redirect()->route('cesop.encrypt.success')
                ->with('success', 'File compressed and encrypted successfully')
                ->with('encrypted_file', $outputFilename);

        } catch (\Exception $e) {
            $this->logger->log('error', 'CESOP compression/encryption failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Compression/Encryption failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show success page after encryption
     */
    public function success()
    {
        if (!session('encrypted_file')) {
            return redirect()->route('cesop.encrypt.index');
        }

        return view('cesop::encrypt.success', [
            'encrypted_file' => session('encrypted_file')
        ]);
    }

    /**
     * Download encrypted file
     */
    public function download($filename)
    {
        // Validate filename for security (updated to allow .pgp.zip)
        if (!preg_match('/^PMT-Q[1-4]-\d{4}-[A-Z]{2}-[A-Za-z0-9_-]+-\d+-\d+\.pgp\.zip$/', $filename)) {
            abort(400, 'Invalid filename format');
        }

        $path = 'cesop/encrypted/' . $filename;

        if (!Storage::exists($path)) {
            abort(404, 'File not found');
        }

        $this->logger->log('info', "CESOP encrypted file downloaded: {$filename}");

        return Storage::download($path, $filename);
    }
}
