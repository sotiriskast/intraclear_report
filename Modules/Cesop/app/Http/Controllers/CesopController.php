<?php

namespace Modules\Cesop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Cesop\Services\PgpEncryptionService;
use Modules\Cesop\Services\XmlValidationService;

class CesopController extends Controller
{
    /**
     * @var PgpEncryptionService
     */
    private $pgpService;

    /**
     * @var XmlValidationService
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
        XmlValidationService $xmlValidator,
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
        return view('cesop::index');
    }

    /**
     * Handle XML file upload and encryption
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

            // Validate against CESOP schema
            $validationResult = $this->xmlValidator->validateXml($xmlPath);

            if (!$validationResult['isValid']) {
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

            // Extract information from XML or use form inputs
            $reportingPeriod = $this->xmlValidator->extractReportingPeriod($xmlPath);

            $quarter = $reportingPeriod ? $reportingPeriod['quarter'] : $request->input('quarter');
            $year = $reportingPeriod ? $reportingPeriod['year'] : $request->input('year');
            $countryCode = strtoupper($request->input('country_code'));
            $pspId = $request->input('psp_id');

            // Generate filename
            $outputFilename = $this->pgpService->generateCesopFilename(
                $quarter, $year, $countryCode, $pspId
            );

            // Define output path
            $outputPath = 'cesop/encrypted/' . $outputFilename;

            // Encrypt the XML file
            $encryptedPath = $this->pgpService->encryptXmlFile($xmlPath, $outputPath);

            // Clean up the temporary file
            Storage::delete($xmlPath);

            $this->logger->log('info', "CESOP XML encrypted successfully: {$outputFilename}");

            return redirect()->route('cesop.success')
                ->with('success', 'File encrypted successfully')
                ->with('encrypted_file', $outputFilename);

        } catch (\Exception $e) {
            $this->logger->log('error', 'CESOP encryption failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Encryption failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show success page after encryption
     */
    public function success()
    {
        if (!session('encrypted_file')) {
            return redirect()->route('cesop.index');
        }

        return view('cesop::success', [
            'encrypted_file' => session('encrypted_file')
        ]);
    }

    /**
     * Download encrypted file
     */
    public function download($filename)
    {
        // Validate filename for security
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
