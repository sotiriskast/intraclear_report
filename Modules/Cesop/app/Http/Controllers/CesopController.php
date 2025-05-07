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
            'xml_file' => 'required|file|mimes:xml|max:102400', // 100MB max
            'quarter' => 'required|integer|min:1|max:4',
            'year' => 'required|integer|min:2024',
            'country_code' => 'required|string|size:2',
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

            // Get original filename
            $originalFilename = $request->file('xml_file')->getClientOriginalName();
            $filenameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);

            // Validate against CESOP schema using the FULL system path
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

            // Create ZIP with the same name as the original XML
            $zipFilename = $filenameWithoutExt . '.zip';
            $tempDir = dirname($fullXmlPath);
            $zipPath = $tempDir . '/' . $zipFilename;

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new \Exception('Could not create ZIP file');
            }
            $zip->addFile($fullXmlPath, $originalFilename);
            $zip->close();

            // Use form inputs and get PSP ID from config
            $quarter = $request->input('quarter');
            $year = $request->input('year');
            $countryCode = strtoupper($request->input('country_code'));
            $pspId = config('cesop.psp.bic', 'ITRACY2L');

            // Generate filename for the encrypted file
            $outputFilename = $this->pgpService->generateCesopFilename(
                $quarter, $year, $countryCode, $pspId
            );

            // Define output path for encrypted file
            $outputPath = 'cesop/encrypted/' . $outputFilename;

            // Convert local zip path to Laravel storage path
            $zipStoragePath = 'cesop/temp/' . $zipFilename;
            Storage::put($zipStoragePath, file_get_contents($zipPath));

            // Encrypt the ZIP file
            // Change this line in the upload method:
            // Call the method and store the path but use it in the log
            $encryptedPath = $this->pgpService->encryptFile($zipStoragePath, $outputPath);
            $this->logger->log('info', "CESOP XML zipped and encrypted successfully: {$outputFilename}, saved to: {$encryptedPath}");
            // Clean up temporary files
            unlink($zipPath);
            Storage::delete($xmlPath);
            Storage::delete($zipStoragePath);

            $this->logger->log('info', "CESOP XML zipped and encrypted successfully: {$outputFilename}");

            return redirect()->route('cesop.encrypt.success')
                ->with('success', 'File zipped and encrypted successfully')
                ->with('encrypted_file', $outputFilename);

        } catch (\Exception $e) {
            $this->logger->log('error', 'CESOP zip/encryption failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Zip/Encryption failed: ' . $e->getMessage())
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
        // Validate filename for security (now just .pgp extension)
        if (!preg_match('/^PMT-Q[1-4]-\d{4}-[A-Z]{2}-[A-Za-z0-9_-]+-\d+-\d+\.pgp$/', $filename)) {
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
